<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\CampaignRecipient;
use App\Models\Company;
use App\Models\Contact;
use App\Models\PrivacyRequest;
use App\Models\Suppression;
use App\Models\User;
use App\Services\SuppressionService;

class ComplianceTest extends CampaignTestCase
{
    private User $dpo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dpo = $this->userWithRole('conformite');
    }

    public function test_search_and_export_everything_about_an_address(): void
    {
        $contact = $this->contact('claire@agence.fr', ['first_name' => 'Claire', 'last_name' => 'Morel']);
        $this->launch($this->campaign());

        $this->actingAs($this->dpo)->get(route('pm.compliance.requests', ['email' => 'Claire@Agence.fr']))->assertOk()
            ->assertSee('fiche(s) contact')->assertSee('Claire Morel');
        $response = $this->actingAs($this->dpo)->post(route('pm.compliance.export'), ['email' => 'claire@agence.fr']);
        $response->assertOk();
        $json = json_decode($response->streamedContent(), true);
        $this->assertSame('Claire', $json['contacts'][0]['prenom']);
        $this->assertCount(1, $json['emails_recus']);
        $this->assertSame('privacy.exported', AuditLog::latest('id')->value('action'));
    }

    public function test_erasure_removes_personal_data_and_blocks_reimport(): void
    {
        $contact = $this->contact('claire@agence.fr', ['first_name' => 'Claire', 'last_name' => 'Morel', 'phone' => '0410203040']);
        $this->launch($this->campaign());
        Activity::create(['subject_type' => $contact->company->getMorphClass(), 'subject_id' => $contact->company_id, 'type' => 'note', 'body' => 'Appel avec Claire Morel (claire@agence.fr)', 'occurred_at' => now()]);

        $this->actingAs($this->dpo)->post(route('pm.compliance.erase'), ['email' => 'claire@agence.fr', 'confirm_email' => 'autre@x.fr'])->assertSessionHasErrors('confirm_email');
        $this->actingAs($this->dpo)->post(route('pm.compliance.erase'), ['email' => 'claire@agence.fr', 'confirm_email' => 'claire@agence.fr', 'note' => 'Demande n°1'])->assertSessionHas('success');

        $erased = Contact::withTrashed()->findOrFail($contact->id);
        $this->assertTrue($erased->trashed());
        $this->assertNull($erased->email);
        $this->assertNull($erased->first_name);
        $this->assertNull($erased->phone);
        $this->assertFalse(CampaignRecipient::where('email', 'claire@agence.fr')->exists());
        $this->assertStringNotContainsString('claire@agence.fr', Activity::latest('id')->where('type', 'note')->value('body'));
        $this->assertStringNotContainsString('Claire Morel', Activity::where('type', 'note')->value('body'));
        $this->assertTrue(app(SuppressionService::class)->isSuppressed('claire@agence.fr'), 'jamais réintroduite');
        $this->assertDatabaseMissing('pm_suppressions', ['note' => 'claire@agence.fr']);
    }

    public function test_requests_register_with_deadline(): void
    {
        $this->actingAs($this->dpo)->post(route('pm.compliance.requests.store'), ['type' => 'acces', 'email' => 'x@y.fr', 'received_at' => now()->subDays(40)->toDateString()])->assertSessionHas('success');
        $request = PrivacyRequest::firstOrFail();
        $this->assertNotSame('x@y.fr', $request->email_hash);
        $this->assertTrue($request->isLate());
        $this->actingAs($this->dpo)->get(route('pm.compliance.requests'))->assertSee('en retard');
        $this->actingAs($this->dpo)->put(route('pm.compliance.requests.update', $request), ['status' => 'traitee'])->assertSessionHasErrors('outcome');
        $this->actingAs($this->dpo)->put(route('pm.compliance.requests.update', $request), ['status' => 'traitee', 'outcome' => 'Export envoyé par e-mail'])->assertSessionHas('success');
        $this->assertSame($this->dpo->id, $request->fresh()->handled_by);
    }

    public function test_lifting_an_opposition_requires_justification_and_is_traced(): void
    {
        app(SuppressionService::class)->suppress('pierre@garage.fr', 'all', 'opposition', 'test');
        $suppression = Suppression::firstOrFail();
        $this->actingAs($this->dpo)->get(route('pm.compliance.suppressions', ['email' => 'pierre@garage.fr']))->assertOk()->assertSee('Lever');
        $this->actingAs($this->dpo)->post(route('pm.compliance.suppressions.lift', $suppression), ['justification' => 'oui', 'confirm' => '1'])->assertSessionHasErrors('justification');
        $this->actingAs($this->dpo)->post(route('pm.compliance.suppressions.lift', $suppression), ['justification' => 'Nouveau consentement écrit reçu le 02/11', 'confirm' => '1'])->assertSessionHas('success');
        $this->assertFalse(app(SuppressionService::class)->isSuppressed('pierre@garage.fr'));
        $this->assertSame('levee_opposition', PrivacyRequest::value('type'));
        $this->assertSame('suppression.lifted', AuditLog::where('action', 'suppression.lifted')->value('action'));
    }

    public function test_retention_archives_inactive_prospects_only(): void
    {
        $old = $this->contact('ancien@agence.fr');
        Company::whereKey($old->company_id)->update(['updated_at' => now()->subMonths(40)]);
        Activity::where('subject_id', $old->company_id)->delete();
        $recent = $this->contact('recent@agence.fr');
        $client = $this->contact('client@agence.fr', [], ['lifecycle' => 'client']);
        Company::whereKey($client->company_id)->update(['updated_at' => now()->subMonths(40), 'lifecycle' => 'client']);

        $this->actingAs($this->dpo)->get(route('pm.compliance.retention'))->assertOk()->assertSee('Prospects à archiver');
        $this->artisan('pm:retention --simulation')->assertSuccessful();
        $this->assertNotNull(Contact::find($old->id), 'la simulation ne modifie rien');

        $this->artisan('pm:retention')->assertSuccessful();
        $this->assertTrue(Company::withTrashed()->find($old->company_id)->trashed());
        $this->assertNull(Contact::withTrashed()->find($old->id)->email);
        $this->assertFalse(Company::find($recent->company_id)->trashed());
        $this->assertNotNull(Company::find($client->company_id), 'les clients ne sont jamais archivés');
    }

    public function test_only_compliance_roles_can_access(): void
    {
        foreach (['commercial', 'marketing', 'responsable_commercial'] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('pm.compliance.requests'))->assertForbidden();
        }
        $this->actingAs($this->userWithRole('administrateur'))->get(route('pm.compliance.register'))->assertOk()->assertSee('Registre des traitements');
    }
}
