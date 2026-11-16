<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_commercial_archives_and_restores_a_prospect(): void
    {
        $user = $this->userWithRole('commercial', ['simple_mode' => true]);
        $company = $this->companyFor($user, ['name' => 'Agence Rangée']);
        $task = new Task(['title' => 'Relancer', 'type' => 'relance', 'due_at' => now()->addDay()]);
        $task->forceFill(['company_id' => $company->id, 'assignee_id' => $user->id, 'created_by' => $user->id, 'status' => 'a_faire'])->save();

        $this->actingAs($user)->get(route('pm.companies.show', $company))->assertSee('Archiver')->assertDontSee('Supprimer</button>', false);

        $this->actingAs($user)->post(route('pm.companies.archive', $company))->assertRedirect(route('pm.companies.index'));
        $company->refresh();
        $this->assertTrue($company->isArchived());
        $this->assertSame('prospect', $company->archived_from);
        $this->assertSame('annule', $task->fresh()->status);

        // Hors de la liste principale, visible dans l'onglet « Archivés ».
        $this->actingAs($user)->get(route('pm.companies.index'))->assertDontSee('id="prospect-'.$company->id.'"', false)->assertSee('Archivés');
        $this->actingAs($user)->get(route('pm.companies.index', ['lifecycle' => 'archive']))->assertSee('id="prospect-'.$company->id.'"', false);
        $this->actingAs($user)->get(route('pm.companies.show', $company))->assertOk()->assertSee('Sortir des archives');

        // Plus d'actions commerciales tant qu'elle est archivée.
        $this->actingAs($user)->post(route('pm.companies.status', $company), ['status' => 'qualifie'])->assertStatus(409);

        $this->actingAs($user)->post(route('pm.companies.unarchive', $company))->assertRedirect(route('pm.companies.show', $company));
        $company->refresh();
        $this->assertSame('prospect', $company->lifecycle);
        $this->assertNull($company->archived_at);
    }

    public function test_archived_companies_never_receive_campaigns(): void
    {
        $user = $this->userWithRole('commercial');
        $company = $this->companyFor($user);
        $company->forceFill(['lifecycle' => 'archive', 'archived_from' => 'prospect', 'archived_at' => now()])->save();

        $this->assertSame(0, Company::query()->filter(['lifecycle' => 'prospect'])->whereKey($company->id)->count());
    }

    public function test_only_users_with_delete_right_can_delete(): void
    {
        $commercial = $this->userWithRole('commercial', ['simple_mode' => true]);
        $company = $this->companyFor($commercial, ['name' => 'Doublon SARL']);
        $this->actingAs($commercial)->delete(route('pm.companies.destroy', $company))->assertForbidden();

        $manager = $this->userWithRole('responsable_commercial', ['simple_mode' => true]);
        $company = $this->companyFor($manager, ['name' => 'Doublon SARL']);
        $this->actingAs($manager)->get(route('pm.companies.show', $company))->assertSee('Supprimer');
        $this->actingAs($manager)->delete(route('pm.companies.destroy', $company))->assertRedirect(route('pm.companies.index'));
        $this->assertSoftDeleted($company);
    }
}
