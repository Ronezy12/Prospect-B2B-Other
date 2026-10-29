<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\AutomationLog;
use App\Models\AutomationRun;
use App\Models\CampaignRecipient;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Sector;
use App\Models\Task;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Automation\AutomationEngine;
use App\Services\Mail\DeliveryTracker;
use App\Services\SuppressionService;
use Illuminate\Support\Facades\URL;

class AutomationTest extends CampaignTestCase
{
    private function automation(string $preset): Automation
    {
        $this->actingAs($this->marketer)->post(route('pm.automations.store'), ['preset' => $preset])->assertRedirect();
        $automation = Automation::latest('id')->firstOrFail();
        $this->actingAs($this->marketer)->post(route('pm.automations.activate', $automation))->assertSessionHas('success');

        return $automation->fresh();
    }

    private ?User $admin = null;

    private function admin(): User
    {
        return $this->admin ??= $this->userWithRole('administrateur');
    }

    /** Les fiches sont créées par un administrateur : le rôle Marketing ne gère pas les prospects. */
    private function newCompany(string $name = 'Agence Test'): Company
    {
        $this->actingAs($this->admin())->post(route('pm.companies.store'), ['name' => $name, 'status' => 'nouveau'])->assertRedirect();

        return Company::where('name', $name)->firstOrFail();
    }

    public function test_new_prospect_creates_a_qualification_task(): void
    {
        $automation = $this->automation('nouveau_prospect');
        $company = $this->newCompany();

        $task = Task::where('company_id', $company->id)->firstOrFail();
        $this->assertSame('Qualifier le nouveau prospect', $task->title);
        $this->assertSame($this->admin()->id, $task->assignee_id, 'tâche attribuée au responsable de la fiche');
        $run = AutomationRun::firstOrFail();
        $this->assertSame('termine', $run->status);
        $this->assertSame($automation->active_version_id, $run->automation_version_id);
        $this->assertSame('ok', $run->logs()->first()->result);
    }

    public function test_frequency_limit_and_event_deduplication(): void
    {
        $automation = $this->automation('changement_statut');
        $company = $this->companyFor($this->marketer);
        $engine = app(AutomationEngine::class);

        $this->assertSame(1, $engine->fire('company.status_changed', $company, 'evt-1'));
        $this->assertSame(0, $engine->fire('company.status_changed', $company, 'evt-1'), 'même événement : pas de seconde exécution');
        $this->assertSame(0, $engine->fire('company.status_changed', $company, 'evt-2'), 'limite : 1 fois par fiche sur 30 jours');

        $automation->update(['max_runs_per_subject' => 5]);
        $this->assertSame(1, $engine->fire('company.status_changed', $company, 'evt-3'));
        $this->assertSame(2, UserNotification::count());
    }

    public function test_status_change_notifies_owner(): void
    {
        $this->automation('changement_statut');
        $company = $this->companyFor($this->admin(), ['name' => 'Garage']);
        $this->actingAs($this->admin())->put(route('pm.companies.update', $company), ['name' => 'Garage', 'status' => 'qualifie', 'owner_id' => $this->admin()->id]);

        $notification = UserNotification::where('user_id', $this->admin()->id)->firstOrFail();
        $this->assertStringContainsString('Garage', $notification->title);
        $this->actingAs($this->marketer)->get(route('pm.notifications.open', $notification))->assertNotFound();
        $this->actingAs($this->admin())->get(route('pm.notifications.open', $notification))->assertRedirect(route('pm.companies.show', $company, false));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_delay_then_condition_creates_followup_only_if_still_relevant(): void
    {
        $this->automation('relance_pertinente');
        $silent = $this->newCompany('Silencieuse');
        $replied = $this->newCompany('Répondante');

        $this->assertSame(2, AutomationRun::where('status', 'en_attente')->count());
        $this->assertSame(0, Task::count());

        // Un contact de la seconde entreprise répond entre-temps.
        $contact = new Contact(['last_name' => 'R', 'email' => 'r@pro.test', 'audience_type' => 'b2b', 'legal_basis' => 'interet_legitime']);
        $contact->company_id = $replied->id;
        $contact->save();
        $this->launch($this->campaign());
        CampaignRecipient::where('email', 'r@pro.test')->update(['replied_at' => now()->addDay()]);

        $this->artisan('pm:automations-run');
        $this->assertSame(0, Task::count(), 'le délai n\'est pas écoulé');

        $this->travel(3)->days();
        $this->travel(1)->minutes();
        $this->artisan('pm:automations-run');

        $this->assertSame(['Silencieuse'], Task::with('company')->get()->pluck('company.name')->all());
        $this->assertSame(2, AutomationRun::where('status', 'termine')->count());
    }

    public function test_paused_automation_does_not_fire_or_resume_and_stop_cancels_runs(): void
    {
        $automation = $this->automation('relance_pertinente');
        $company = $this->newCompany('A');
        $this->actingAs($this->marketer)->post(route('pm.automations.pause', $automation));

        $this->newCompany('B');
        $this->assertSame(1, AutomationRun::count(), 'en pause : pas de nouveau déclenchement');

        $this->travel(4)->days();
        $this->artisan('pm:automations-run');
        $this->assertSame(0, Task::count(), 'en pause : les exécutions attendent');

        $this->actingAs($this->marketer)->post(route('pm.automations.stop', $automation))->assertSessionHas('success');
        $this->assertSame('arrete', AutomationRun::first()->status);
        $this->artisan('pm:automations-run');
        $this->assertSame(0, Task::count());
    }

    public function test_reply_suspends_followups(): void
    {
        $this->automation('relance_pertinente');
        $this->automation('reponse_recue');
        $company = $this->newCompany('Prospect');
        $contact = new Contact(['last_name' => 'P', 'email' => 'p@pro.test', 'audience_type' => 'b2b', 'legal_basis' => 'interet_legitime']);
        $contact->company_id = $company->id;
        $contact->save();
        $relance = new Task(['title' => 'Relance manuelle', 'type' => 'relance', 'priority' => 'normale', 'due_at' => now()->addDay()]);
        $relance->company_id = $company->id;
        $relance->save();
        $this->launch($this->campaign());
        $recipient = CampaignRecipient::where('email', 'p@pro.test')->firstOrFail();

        app(DeliveryTracker::class)->record($recipient, 'reponse', now(), 'imap', 'imap:1:99', ['subject' => 'Re: offre']);

        $this->assertSame('annule', $relance->fresh()->status);
        $this->assertSame('arrete', AutomationRun::whereHas('automation', fn ($q) => $q->where('trigger', 'company.created'))->value('status'));
        $this->assertTrue(UserNotification::where('title', 'like', 'Réponse reçue%')->exists());
    }

    public function test_unsubscribe_notifies_and_blocks(): void
    {
        $this->automation('desabonnement');
        $this->contact('stop@pro.test');
        $this->launch($this->campaign());
        $recipient = CampaignRecipient::firstOrFail();

        $this->post(URL::signedRoute('unsubscribe.store', ['recipient' => $recipient->id]))->assertOk();

        $this->assertTrue(app(SuppressionService::class)->isSuppressed('stop@pro.test', 'prospection'));
        $this->assertTrue(UserNotification::where('title', 'like', 'Désinscription%')->exists());
    }

    public function test_won_opportunity_records_conversion(): void
    {
        $this->automation('opportunite_gagnee');
        $company = $this->companyFor($this->marketer, ['sector_id' => Sector::where('slug', 'agences-immobilieres')->value('id')]);
        $admin = $this->userWithRole('administrateur');
        $pipeline = $company->sector->pipeline;
        $this->actingAs($admin)->post(route('pm.opportunities.store'), [
            'company_id' => $company->id, 'pipeline_id' => $pipeline->id, 'stage_id' => $pipeline->stages()->first()->id, 'title' => 'Contrat', 'priority' => 'normale',
        ]);
        $opportunity = Opportunity::firstOrFail();

        $this->actingAs($admin)->postJson(route('pm.opportunities.move', $opportunity), ['stage_id' => $pipeline->stages()->where('type', 'won')->value('id')])->assertOk();

        $this->assertSame('client', $company->fresh()->lifecycle);
        $this->assertDatabaseHas('pm_audit_logs', ['action' => 'company.converted', 'subject_id' => $company->id]);
    }

    public function test_test_mode_changes_nothing_and_skips_delays(): void
    {
        $this->actingAs($this->marketer)->post(route('pm.automations.store'), ['preset' => 'relance_pertinente']);
        $automation = Automation::firstOrFail();
        $company = $this->companyFor($this->marketer);

        $this->actingAs($this->marketer)->post(route('pm.automations.test', $automation), ['company_id' => $company->id])->assertSessionHas('success');

        $run = AutomationRun::firstOrFail();
        $this->assertTrue($run->is_test);
        $this->assertSame('termine', $run->status);
        $this->assertSame(0, Task::count());
        $this->assertTrue($run->logs()->where('result', 'simule')->where('message', 'like', 'Créerait la tâche%')->exists());
        $this->assertSame('brouillon', $automation->fresh()->status, 'un test n\'active pas le scénario');
    }

    public function test_versioning_and_validation(): void
    {
        $automation = $this->automation('nouveau_prospect');
        $v1 = $automation->active_version_id;

        $this->actingAs($this->marketer)->put(route('pm.automations.update', $automation), [
            'name' => 'X', 'trigger' => 'company.created', 'max_runs_per_subject' => 1, 'frequency_days' => 30,
            'steps' => [['type' => 'condition', 'field' => 'company.has_owner', 'operator' => 'is_true', 'then' => 's9', 'else' => 'end']],
        ])->assertSessionHasErrors('steps');

        $this->actingAs($this->marketer)->put(route('pm.automations.update', $automation), [
            'name' => 'Nouveau prospect v2', 'trigger' => 'company.created', 'max_runs_per_subject' => 1, 'frequency_days' => 30,
            'steps' => [['type' => 'action', 'action' => 'create_task', 'title' => 'Appeler sous 48 h', 'due_days' => 2]],
        ])->assertRedirect();
        $automation->refresh();
        $this->assertSame(2, $automation->current_version);
        $this->assertSame($v1, $automation->active_version_id, 'la nouvelle version n\'est pas publiée automatiquement');

        $this->newCompany('Avant publication');
        $this->assertSame('Qualifier le nouveau prospect', Task::latest('id')->value('title'));

        $this->actingAs($this->marketer)->post(route('pm.automations.activate', $automation));
        $this->newCompany('Après publication');
        $this->assertSame('Appeler sous 48 h', Task::latest('id')->value('title'));

        $this->actingAs($this->marketer)->put(route('pm.automations.update', $automation), [
            'name' => 'Y', 'trigger' => 'email.replied', 'max_runs_per_subject' => 1, 'frequency_days' => 30,
            'steps' => [['type' => 'action', 'action' => 'stop_followups']],
        ])->assertSessionHasErrors('trigger');
    }

    public function test_infinite_loop_is_stopped(): void
    {
        $automation = Automation::create(['name' => 'Boucle', 'trigger' => 'company.created']);
        $automation->forceFill(['created_by' => $this->marketer->id])->save();
        $version = $automation->addVersion(['steps' => [
            ['id' => 's1', 'type' => 'condition', 'field' => 'company.has_owner', 'operator' => 'is_true', 'then' => 's1', 'else' => 's1'],
        ]], $this->marketer->id);
        $automation->forceFill(['status' => 'active', 'active_version_id' => $version->id])->save();

        app(AutomationEngine::class)->fire('company.created', $this->companyFor($this->marketer), 'created');

        $run = AutomationRun::firstOrFail();
        $this->assertSame('erreur', $run->status);
        $this->assertStringContainsString('Trop d\'étapes', $run->error);
    }

    public function test_send_email_action_respects_opposition(): void
    {
        $template = $this->template();
        $automation = Automation::create(['name' => 'Mail', 'trigger' => 'company.status_changed', 'max_runs_per_subject' => 5]);
        $automation->forceFill(['created_by' => $this->marketer->id])->save();
        $version = $automation->addVersion(['steps' => [['id' => 's1', 'type' => 'action', 'action' => 'send_email', 'template_id' => $template->id, 'mail_account_id' => $this->account->id]]], $this->marketer->id);
        $automation->forceFill(['status' => 'active', 'active_version_id' => $version->id])->save();

        $ok = $this->contact('ok@pro.test');
        $opposed = $this->contact('non@pro.test');
        app(SuppressionService::class)->suppress('non@pro.test', 'all', 'opposition', 'test');

        app(AutomationEngine::class)->fire('company.status_changed', $ok->company, 'a');
        app(AutomationEngine::class)->fire('company.status_changed', $opposed->company, 'b');

        $this->assertSame(['ok@pro.test'], $this->provider->recipients());
        $this->assertTrue(AutomationLog::where('message', 'like', 'E-mail non envoyé%')->exists());
    }

    public function test_permissions_and_pages(): void
    {
        $automation = $this->automation('nouveau_prospect');
        foreach ([route('pm.automations.index'), route('pm.automations.show', $automation), route('pm.automations.edit', $automation)] as $url) {
            $this->actingAs($this->marketer)->get($url)->assertOk();
        }
        $this->actingAs($this->userWithRole('commercial'))->get(route('pm.automations.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('lecture_seule'))->post(route('pm.automations.store'))->assertForbidden();
    }

    public function test_simple_switches_create_activate_and_pause_rules(): void
    {
        $this->actingAs($this->marketer)->get(route('pm.automations.simple'))->assertOk()->assertSee('ajoute un prospect', false);
        $this->actingAs($this->marketer)->post(route('pm.automations.toggle', 'nouveau_prospect'), ['on' => 1])->assertSessionHas('success');
        $automation = Automation::where('preset', 'nouveau_prospect')->firstOrFail();
        $this->assertSame('active', $automation->status);

        $company = $this->newCompany('Agence Interrupteur');
        $this->assertTrue(Task::where('company_id', $company->id)->exists(), 'la règle allumée agit');

        $this->actingAs($this->marketer)->post(route('pm.automations.toggle', 'nouveau_prospect'), ['on' => 0]);
        $this->assertSame('en_pause', $automation->fresh()->status);
        $this->actingAs($this->marketer)->post(route('pm.automations.toggle', 'nouveau_prospect'), ['on' => 1]);
        $this->assertSame(1, Automation::where('preset', 'nouveau_prospect')->count(), 'pas de doublon');
        $this->actingAs($this->marketer)->post(route('pm.automations.toggle', 'inconnu'), ['on' => 1])->assertNotFound();
        $this->actingAs($this->userWithRole('commercial'))->post(route('pm.automations.toggle', 'nouveau_prospect'), ['on' => 0])->assertForbidden();
    }
}
