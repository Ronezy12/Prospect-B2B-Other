<?php

namespace Tests\Feature;

use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Les droits sont vérifiés côté serveur, indépendamment de l'interface. */
class CompanyAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_commercial_only_sees_own_prospects(): void
    {
        $alice = $this->userWithRole('commercial');
        $bob = $this->userWithRole('commercial');
        $this->companyFor($alice, ['name' => 'Agence Alice']);
        $this->companyFor($bob, ['name' => 'Agence Bob']);

        $this->actingAs($alice)->get(route('pm.companies.index'))
            ->assertOk()->assertSee('Agence Alice')->assertDontSee('Agence Bob');
    }

    public function test_commercial_cannot_open_or_modify_another_users_prospect(): void
    {
        $alice = $this->userWithRole('commercial');
        $bob = $this->userWithRole('commercial');
        $company = $this->companyFor($bob);

        $this->actingAs($alice)->get(route('pm.companies.show', $company))->assertForbidden();
        $this->actingAs($alice)->get(route('pm.companies.edit', $company))->assertForbidden();
        $this->actingAs($alice)->put(route('pm.companies.update', $company), ['name' => 'Piraté', 'status' => 'nouveau'])->assertForbidden();
        $this->actingAs($alice)->delete(route('pm.companies.destroy', $company))->assertForbidden();
        $this->assertNotSame('Piraté', $company->fresh()->name);
    }

    public function test_team_manager_sees_team_and_unassigned_prospects_only(): void
    {
        $team = Team::create(['name' => 'Lyon']);
        $manager = $this->userWithRole('responsable_commercial', ['team_id' => $team->id]);
        $member = $this->userWithRole('commercial', ['team_id' => $team->id]);
        $outsider = $this->userWithRole('commercial');

        $this->companyFor($member, ['name' => 'Fiche équipe']);
        $this->companyFor(null, ['name' => 'Fiche non assignée']);
        $this->companyFor($outsider, ['name' => 'Fiche externe']);

        $this->actingAs($manager)->get(route('pm.companies.index'))
            ->assertSee('Fiche équipe')->assertSee('Fiche non assignée')->assertDontSee('Fiche externe');
    }

    public function test_read_only_user_cannot_create_or_update(): void
    {
        $reader = $this->userWithRole('lecture_seule');
        $company = $this->companyFor(null);

        $this->actingAs($reader)->get(route('pm.companies.show', $company))->assertOk();
        $this->actingAs($reader)->get(route('pm.companies.create'))->assertForbidden();
        $this->actingAs($reader)->post(route('pm.companies.store'), ['name' => 'X', 'status' => 'nouveau'])->assertForbidden();
        $this->actingAs($reader)->put(route('pm.companies.update', $company), ['name' => 'X', 'status' => 'nouveau'])->assertForbidden();
    }

    public function test_export_requires_permission(): void
    {
        $this->actingAs($this->userWithRole('commercial'))->get(route('pm.companies.export'))->assertForbidden();
        $this->actingAs($this->userWithRole('marketing'))->get(route('pm.companies.export'))->assertForbidden();
    }

    public function test_export_is_limited_to_scope_and_neutralises_formulas(): void
    {
        $team = Team::create(['name' => 'Paris']);
        $manager = $this->userWithRole('responsable_commercial', ['team_id' => $team->id]);
        $this->companyFor($manager, ['name' => '=HYPERLINK("http://x")']);
        $this->companyFor($this->userWithRole('commercial'), ['name' => 'Hors périmètre']);

        $response = $this->actingAs($manager)->get(route('pm.companies.export'));
        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('Hors périmètre', $csv);
        $this->assertDatabaseHas('pm_audit_logs', ['action' => 'company.exported', 'user_id' => $manager->id]);
    }

    public function test_bulk_update_ignores_records_outside_scope(): void
    {
        $team = Team::create(['name' => 'Nantes']);
        $manager = $this->userWithRole('responsable_commercial', ['team_id' => $team->id]);
        $mine = $this->companyFor($manager);
        $foreign = $this->companyFor($this->userWithRole('commercial'));

        $this->actingAs($manager)->post(route('pm.companies.bulk'), [
            'ids' => [$mine->id, $foreign->id],
            'action' => 'status',
            'status' => 'qualifie',
        ])->assertSessionHas('success');

        $this->assertSame('qualifie', $mine->fresh()->status);
        $this->assertSame('nouveau', $foreign->fresh()->status);
    }

    public function test_commercial_cannot_use_bulk_actions_or_assign(): void
    {
        $commercial = $this->userWithRole('commercial');
        $company = $this->companyFor($commercial);
        $other = $this->userWithRole('commercial');

        $this->actingAs($commercial)->post(route('pm.companies.bulk'), ['ids' => [$company->id], 'action' => 'status', 'status' => 'qualifie'])->assertForbidden();

        // Un commercial ne peut pas réassigner via le formulaire d'édition.
        $this->actingAs($commercial)->put(route('pm.companies.update', $company), [
            'name' => $company->name, 'status' => 'nouveau', 'owner_id' => $other->id,
        ])->assertRedirect();
        $this->assertSame($commercial->id, $company->fresh()->owner_id);
    }

    public function test_conversion_requires_permission_and_confirmation(): void
    {
        $commercial = $this->userWithRole('commercial');
        $company = $this->companyFor($commercial);
        $this->actingAs($commercial)->post(route('pm.companies.convert', $company), ['confirm' => '1'])->assertForbidden();

        $admin = $this->userWithRole('administrateur');
        $this->actingAs($admin)->post(route('pm.companies.convert', $company))->assertSessionHasErrors('confirm');
        $this->assertSame('prospect', $company->fresh()->lifecycle);

        $this->actingAs($admin)->post(route('pm.companies.convert', $company), ['confirm' => '1'])->assertRedirect();
        $company->refresh();
        $this->assertSame('client', $company->lifecycle);
        $this->assertSame($admin->id, $company->converted_by);
        $this->assertDatabaseHas('pm_audit_logs', ['action' => 'company.converted', 'subject_id' => $company->id]);
    }

    public function test_user_management_requires_permission(): void
    {
        $this->actingAs($this->userWithRole('responsable_commercial'))->get(route('pm.settings.users.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('administrateur'))->get(route('pm.settings.users.index'))->assertOk();
    }

    public function test_admin_cannot_remove_own_admin_role(): void
    {
        $admin = $this->userWithRole('administrateur');

        $this->actingAs($admin)->put(route('pm.settings.users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'commercial', 'is_active' => '1',
        ])->assertSessionHasErrors('role');
        $this->assertTrue($admin->fresh()->hasRole('administrateur'));
    }
}
