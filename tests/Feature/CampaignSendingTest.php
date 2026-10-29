<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\EmailEvent;
use App\Models\NewsletterList;
use App\Models\Subscription;
use App\Services\Mail\AudienceBuilder;
use App\Services\Mail\SendResult;
use App\Services\SuppressionService;

class CampaignSendingTest extends CampaignTestCase
{
    public function test_prospection_campaign_applies_compliance_rules_and_sends(): void
    {
        $this->contact('ok@pro.test', ['first_name' => 'Alice']);
        $this->contact('particulier@perso.test', ['audience_type' => 'b2c', 'legal_basis' => 'interet_legitime']);
        $this->contact('consenti@perso.test', ['audience_type' => 'b2c', 'legal_basis' => 'consentement']);
        $this->contact('oppose@pro.test');
        $this->contact('invalide@pro.test');
        $this->contact('ok@pro.test'); // même adresse sur une deuxième fiche
        app(SuppressionService::class)->suppress('oppose@pro.test', 'prospection', 'opposition', 'test');
        app(SuppressionService::class)->suppress('invalide@pro.test', 'all', 'rebond_definitif', 'test');

        $campaign = $this->campaign();
        $this->launch($campaign)->assertRedirect(route('pm.campaigns.show', $campaign))->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(['ok@pro.test', 'consenti@perso.test'], $this->provider->recipients());
        $reasons = CampaignRecipient::where('status', 'exclu')->pluck('exclusion_reason', 'email');
        $this->assertSame('b2c_sans_consentement', $reasons['particulier@perso.test']);
        $this->assertSame('opposition', $reasons['oppose@pro.test']);
        $this->assertSame('adresse_invalide', $reasons['invalide@pro.test']);
        $this->assertSame(5, CampaignRecipient::count(), 'adresse en double : un seul destinataire');
        $this->assertSame('terminee', $campaign->fresh()->status);
        $this->assertSame(2, EmailEvent::where('type', 'accepte')->count());

        $message = collect($this->provider->sent)->firstWhere('toEmail', 'ok@pro.test');
        $this->assertSame('Bonjour Alice', $message->subject);
        $this->assertStringContainsString('Se désinscrire', $message->html, 'pied de page de désinscription ajouté');
        $this->assertStringStartsWith('<http', $message->headers['List-Unsubscribe']);
        $this->assertSame('List-Unsubscribe=One-Click', $message->headers['List-Unsubscribe-Post']);
        $this->assertNotEmpty($message->text);
    }

    public function test_variables_are_escaped(): void
    {
        $this->contact('x@pro.test', ['first_name' => '<script>alert(1)</script>']);
        $this->launch($this->campaign());

        $this->assertStringNotContainsString('<script>', $this->provider->sent[0]->html);
        $this->assertStringContainsString('&lt;script&gt;', $this->provider->sent[0]->html);
    }

    public function test_preflight_blocks_untested_account_and_empty_audience(): void
    {
        $campaign = $this->campaign();
        $this->launch($campaign)->assertSessionHasErrors('campaign');

        $this->contact('a@pro.test');
        $this->account->forceFill(['last_test_result' => 'Connexion refusée'])->save();
        $this->launch($campaign)->assertSessionHasErrors('campaign');
        $this->assertSame('brouillon', $campaign->fresh()->status);
        $this->assertSame([], $this->provider->sent);
    }

    public function test_compliance_confirmation_is_required(): void
    {
        $this->contact('a@pro.test');
        $campaign = $this->campaign();
        $this->actingAs($this->marketer)->post(route('pm.campaigns.schedule', $campaign), ['when' => 'now'])->assertSessionHasErrors('confirm_compliance');
        $this->assertSame('brouillon', $campaign->fresh()->status);
    }

    public function test_scheduled_campaign_starts_on_time_via_scheduler(): void
    {
        $this->contact('a@pro.test');
        $campaign = $this->campaign();
        $this->launch($campaign, ['when' => 'later', 'scheduled_at' => now()->addHours(2)->format('Y-m-d H:i')])->assertSessionHasNoErrors();
        $this->assertSame('programmee', $campaign->fresh()->status);
        $this->assertNotNull($campaign->fresh()->template_version_id, 'version figée à la programmation');

        $this->artisan('pm:campaigns-run')->assertSuccessful();
        $this->assertSame([], $this->provider->sent);

        $this->travel(3)->hours();
        $this->artisan('pm:campaigns-run')->assertSuccessful();
        $this->assertSame(['a@pro.test'], $this->provider->recipients());
        $this->assertSame('terminee', $campaign->fresh()->status);
    }

    public function test_template_changes_after_scheduling_do_not_alter_the_campaign(): void
    {
        $this->contact('a@pro.test');
        $campaign = $this->campaign();
        $this->launch($campaign, ['when' => 'later', 'scheduled_at' => now()->addHour()->format('Y-m-d H:i')]);
        $campaign->template->addVersion(['subject' => 'Nouvel objet', 'html' => '<p>Autre</p>'], $this->marketer->id);

        $this->travel(2)->hours();
        $this->artisan('pm:campaigns-run');
        $this->assertSame('Bonjour Jean', $this->provider->sent[0]->subject);
    }

    public function test_pause_resume_and_cancel(): void
    {
        $this->contact('a@pro.test');
        $this->contact('b@pro.test');
        $campaign = $this->campaign();
        $this->launch($campaign, ['when' => 'later', 'scheduled_at' => now()->addHour()->format('Y-m-d H:i')]);

        $this->actingAs($this->marketer)->post(route('pm.campaigns.pause', $campaign))->assertSessionHasNoErrors();
        $this->travel(2)->hours();
        $this->artisan('pm:campaigns-run');
        $this->assertSame([], $this->provider->sent, 'une campagne en pause ne part pas');

        $this->actingAs($this->marketer)->post(route('pm.campaigns.resume', $campaign));
        $this->assertSame('programmee', $campaign->fresh()->status);
        $this->actingAs($this->marketer)->post(route('pm.campaigns.cancel', $campaign))->assertSessionHasNoErrors();
        $this->artisan('pm:campaigns-run');
        $this->assertSame([], $this->provider->sent);
        $this->assertSame('annulee', $campaign->fresh()->status);
    }

    public function test_cancel_during_sending_stops_remaining_recipients(): void
    {
        foreach (range(1, 3) as $i) {
            $this->contact("p{$i}@pro.test");
        }
        $campaign = $this->campaign();
        $campaign->forceFill(['status' => 'en_cours', 'template_version_id' => $campaign->template->latestVersion->id, 'launched_by' => $this->marketer->id, 'started_at' => now()])->save();
        app(AudienceBuilder::class)->freeze($campaign, $this->marketer);

        $this->actingAs($this->marketer)->post(route('pm.campaigns.cancel', $campaign));
        $this->artisan('pm:campaigns-run');

        $this->assertSame([], $this->provider->sent);
        $this->assertSame(3, CampaignRecipient::where('status', 'annule')->count());
    }

    public function test_temporary_errors_are_retried_and_permanent_errors_are_not(): void
    {
        $this->contact('temp@pro.test');
        $this->contact('perm@pro.test');
        $this->provider->results = [SendResult::temporary('421 Service indisponible'), SendResult::permanent('550 Boîte inexistante')];

        $campaign = $this->campaign();
        $this->launch($campaign);

        $temp = CampaignRecipient::where('email', 'temp@pro.test')->first();
        $this->assertSame('en_attente', $temp->status);
        $this->assertSame(1, $temp->attempts);
        $this->assertNotNull($temp->next_attempt_at);
        $this->assertSame('echec', CampaignRecipient::where('email', 'perm@pro.test')->value('status'));
        $this->assertSame('en_cours', $campaign->fresh()->status);

        $this->travel(10)->minutes();
        $this->artisan('pm:campaigns-run');
        $this->assertSame('accepte', $temp->fresh()->status);
        $this->assertSame(2, $temp->fresh()->attempts);
        $this->assertSame('terminee', $campaign->fresh()->status);
        $this->assertSame(3, count($this->provider->sent), 'le refus définitif n\'est pas renvoyé');
    }

    public function test_hourly_quota_is_respected(): void
    {
        foreach (range(1, 5) as $i) {
            $this->contact("q{$i}@pro.test");
        }
        $this->account->update(['hourly_limit' => 2]);
        $this->account->forceFill(['last_tested_at' => now(), 'last_test_result' => 'ok'])->save();

        $campaign = $this->campaign();
        $this->launch($campaign);
        $this->assertCount(2, $this->provider->sent);
        $this->assertSame(3, CampaignRecipient::where('status', 'en_attente')->count());

        $this->travel(61)->minutes();
        $this->artisan('pm:campaigns-run');
        $this->assertCount(4, $this->provider->sent);
    }

    public function test_opposition_recorded_after_freeze_is_honoured_at_send_time(): void
    {
        $this->contact('tardif@pro.test');
        $campaign = $this->campaign();
        $campaign->forceFill(['status' => 'en_cours', 'template_version_id' => $campaign->template->latestVersion->id, 'launched_by' => $this->marketer->id, 'started_at' => now()])->save();
        app(AudienceBuilder::class)->freeze($campaign, $this->marketer);
        app(SuppressionService::class)->suppress('tardif@pro.test', 'all', 'opposition', 'test');

        $this->artisan('pm:campaigns-run');
        $this->assertSame([], $this->provider->sent);
        $this->assertSame('opposition', CampaignRecipient::first()->exclusion_reason);
    }

    public function test_interrupted_sends_are_flagged_for_review_not_resent(): void
    {
        $this->contact('a@pro.test');
        $campaign = $this->campaign();
        $campaign->forceFill(['status' => 'en_cours', 'template_version_id' => $campaign->template->latestVersion->id, 'launched_by' => $this->marketer->id, 'started_at' => now()])->save();
        app(AudienceBuilder::class)->freeze($campaign, $this->marketer);
        CampaignRecipient::query()->update(['status' => 'envoi', 'locked_at' => now()->subHour()]);

        $this->artisan('pm:campaigns-run');
        $this->assertSame('a_verifier', CampaignRecipient::first()->status);
        $this->assertSame([], $this->provider->sent);
    }

    public function test_newsletter_reaches_only_active_subscribers(): void
    {
        $list = NewsletterList::create(['name' => 'Lettre']);
        $abonne = $this->contact('abonne@pro.test');
        $desabonne = $this->contact('desabonne@pro.test');
        $this->contact('inconnu@pro.test');
        Subscription::create(['list_id' => $list->id, 'contact_id' => $abonne->id, 'consent_source' => 'Formulaire', 'consented_at' => now()]);
        Subscription::create(['list_id' => $list->id, 'contact_id' => $desabonne->id, 'status' => 'desabonne', 'consent_source' => 'Formulaire', 'consented_at' => now()]);

        $this->launch($this->campaign('newsletter', ['list_id' => $list->id]));

        $this->assertSame(['abonne@pro.test'], $this->provider->recipients());
    }

    public function test_operational_messages_ignore_marketing_opposition_but_not_invalid_addresses(): void
    {
        $this->contact('oppose@pro.test');
        $this->contact('invalide@pro.test');
        app(SuppressionService::class)->suppress('oppose@pro.test', 'all', 'opposition', 'test');
        app(SuppressionService::class)->suppress('invalide@pro.test', 'all', 'rebond_definitif', 'test');

        $this->launch($this->campaign('operationnel'));

        $this->assertSame(['oppose@pro.test'], $this->provider->recipients());
        $this->assertStringNotContainsString('Se désinscrire', $this->provider->sent[0]->html);
        $this->assertArrayNotHasKey('List-Unsubscribe', $this->provider->sent[0]->headers);
    }

    public function test_campaign_permissions(): void
    {
        $commercial = $this->userWithRole('commercial');
        $this->actingAs($commercial)->get(route('pm.campaigns.index'))->assertForbidden();
        $this->actingAs($commercial)->get(route('pm.templates.index'))->assertForbidden();

        $manager = $this->userWithRole('responsable_commercial');
        $campaign = $this->campaign();
        $this->actingAs($manager)->get(route('pm.campaigns.show', $campaign))->assertOk();
        $this->actingAs($manager)->post(route('pm.campaigns.schedule', $campaign), ['when' => 'now', 'confirm_compliance' => '1'])->assertForbidden();
        $this->actingAs($manager)->get(route('pm.campaigns.create'))->assertForbidden();
    }

    public function test_draft_lifecycle_pages_and_duplication(): void
    {
        $campaign = $this->campaign();
        foreach ([route('pm.campaigns.index'), route('pm.campaigns.create'), route('pm.campaigns.create', ['type' => 'newsletter']), route('pm.campaigns.show', $campaign), route('pm.campaigns.edit', $campaign)] as $url) {
            $this->actingAs($this->marketer)->get($url)->assertOk();
        }
        $this->actingAs($this->marketer)->post(route('pm.campaigns.duplicate', $campaign))->assertRedirect();
        $this->assertSame(2, Campaign::count());

        $this->contact('a@pro.test');
        $this->launch($campaign);
        $this->actingAs($this->marketer)->get(route('pm.campaigns.edit', $campaign))->assertRedirect(route('pm.campaigns.show', $campaign));
        $this->actingAs($this->marketer)->put(route('pm.campaigns.update', $campaign), ['name' => 'X'])->assertStatus(409);
    }
}
