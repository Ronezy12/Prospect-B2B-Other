<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Services\SuppressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_prospect_is_created_with_primary_contact_and_history(): void
    {
        $user = $this->userWithRole('commercial');

        $response = $this->actingAs($user)->post(route('pm.companies.store'), [
            'name' => 'Agence du Parc',
            'registration_number' => '552 100 554',
            'website' => 'www.agenceduparc.fr',
            'postal_code' => '69003',
            'city' => 'Lyon',
            'status' => 'nouveau',
            'tags' => 'Salon 2026, Prioritaire',
            'contact' => ['first_name' => 'Marie', 'last_name' => 'Martin', 'email' => 'M.Martin@AgenceDuParc.fr'],
        ]);

        $company = Company::firstOrFail();
        $response->assertRedirect(route('pm.companies.show', $company));
        $this->assertSame('552100554', $company->siren);
        $this->assertSame('agenceduparc.fr', $company->domain);
        $this->assertSame($user->id, $company->owner_id);
        $this->assertCount(2, $company->tags);

        $contact = $company->contacts()->firstOrFail();
        $this->assertSame('m.martin@agenceduparc.fr', $contact->email);
        $this->assertTrue($contact->is_primary);
        $this->assertSame(1, $company->activities()->count());
        $this->assertDatabaseHas('pm_audit_logs', ['action' => 'company.created', 'subject_id' => $company->id]);
    }

    public function test_duplicate_is_flagged_before_creation(): void
    {
        $user = $this->userWithRole('commercial');
        $this->companyFor($user, ['name' => 'Garage Central SARL', 'postal_code' => '75011']);

        $payload = ['name' => 'garage central', 'postal_code' => '75011', 'status' => 'nouveau'];

        $this->actingAs($user)->post(route('pm.companies.store'), $payload)->assertSessionHas('duplicate');
        $this->assertSame(1, Company::count());

        $this->actingAs($user)->post(route('pm.companies.store'), $payload + ['confirm_duplicate' => '1'])->assertRedirect();
        $this->assertSame(2, Company::count());
    }

    public function test_invalid_data_is_rejected_server_side(): void
    {
        $user = $this->userWithRole('commercial');

        $this->actingAs($user)->post(route('pm.companies.store'), [
            'name' => '',
            'registration_number' => '123',
            'website' => 'pas une url',
            'status' => 'inconnu',
            'contact' => ['email' => 'pas-un-email'],
        ])->assertSessionHasErrors(['name', 'registration_number', 'website', 'status', 'contact.email']);

        $this->assertSame(0, Company::count());
    }

    public function test_suppressed_email_cannot_be_reintroduced_manually(): void
    {
        $user = $this->userWithRole('commercial');
        app(SuppressionService::class)->suppress('stop@exemple.fr', 'all', 'opposition', 'test');
        $company = $this->companyFor($user);

        $this->actingAs($user)->post(route('pm.contacts.store'), [
            'company_id' => $company->id, 'last_name' => 'Durand', 'email' => 'STOP@exemple.fr',
            'audience_type' => 'b2b', 'legal_basis' => 'interet_legitime',
        ])->assertSessionHasErrors('email');

        $this->actingAs($user)->post(route('pm.companies.store'), [
            'name' => 'Nouvelle société', 'status' => 'nouveau', 'contact' => ['last_name' => 'Durand', 'email' => 'stop@exemple.fr'],
        ])->assertSessionHasErrors('contact.email');

        $this->assertSame(0, Contact::count());
    }

    public function test_contact_cannot_be_attached_to_company_outside_scope(): void
    {
        $user = $this->userWithRole('commercial');
        $foreign = $this->companyFor($this->userWithRole('commercial'));

        $this->actingAs($user)->post(route('pm.contacts.store'), [
            'company_id' => $foreign->id, 'last_name' => 'Durand', 'audience_type' => 'b2b', 'legal_basis' => 'interet_legitime',
        ])->assertNotFound();
    }

    public function test_status_change_is_recorded_in_history_and_audit(): void
    {
        $user = $this->userWithRole('commercial');
        $company = $this->companyFor($user, ['name' => 'Restaurant Le Midi']);

        $this->actingAs($user)->put(route('pm.companies.update', $company), ['name' => 'Restaurant Le Midi', 'status' => 'qualifie'])
            ->assertRedirect(route('pm.companies.show', $company));

        $this->assertTrue($company->activities()->where('body', 'like', 'Statut : Nouveau → Qualifié%')->exists());
        $this->assertDatabaseHas('pm_audit_logs', ['action' => 'company.updated', 'subject_id' => $company->id]);
    }

    public function test_opposition_is_recorded_and_flagged(): void
    {
        $user = $this->userWithRole('commercial');
        $company = $this->companyFor($user);
        $contact = new Contact(['last_name' => 'Petit', 'email' => 'petit@exemple.fr']);
        $contact->company_id = $company->id;
        $contact->owner_id = $user->id;
        $contact->save();

        $this->actingAs($user)->post(route('pm.contacts.suppress', $contact), ['scope' => 'all', 'note' => 'Demande par téléphone'])
            ->assertSessionHas('success');

        $this->assertTrue(app(SuppressionService::class)->isSuppressed('Petit@Exemple.fr', 'prospection'));
        $this->assertDatabaseMissing('pm_suppressions', ['email_hash' => 'petit@exemple.fr']);
        $this->actingAs($user)->get(route('pm.companies.show', $company))->assertSee('Opposition enregistrée');
    }

    public function test_clients_are_hidden_from_prospect_list_by_default(): void
    {
        $admin = $this->userWithRole('administrateur');
        $this->companyFor($admin, ['name' => 'Prospect Visible']);
        $client = $this->companyFor($admin, ['name' => 'Client Séparé']);
        $client->forceFill(['lifecycle' => 'client'])->save();

        $this->actingAs($admin)->get(route('pm.companies.index'))->assertSee('Prospect Visible')->assertDontSee('Client Séparé');
        $this->actingAs($admin)->get(route('pm.companies.index', ['lifecycle' => 'client']))->assertSee('Client Séparé')->assertDontSee('Prospect Visible');
    }

    public function test_main_pages_render(): void
    {
        $admin = $this->userWithRole('administrateur');
        $company = $this->companyFor($admin);

        foreach ([
            route('pm.dashboard'), route('pm.companies.index'), route('pm.companies.create'),
            route('pm.companies.show', $company), route('pm.companies.edit', $company),
            route('pm.contacts.index'), route('pm.contacts.create', ['company_id' => $company->id]),
            route('pm.imports.index'), route('pm.imports.create'),
            route('pm.settings.users.index'), route('pm.settings.users.create'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}
