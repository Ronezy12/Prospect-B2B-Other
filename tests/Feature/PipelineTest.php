<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\Sector;
use App\Models\StageHistory;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineTest extends TestCase
{
    use RefreshDatabase;

    private function pipeline(string $slug = 'agences-immobilieres'): Pipeline
    {
        return Sector::where('slug', $slug)->firstOrFail()->pipeline()->with('stages')->firstOrFail();
    }

    private function opportunityFor(User $owner, ?Company $company = null, string $stage = 'Nouveau'): Opportunity
    {
        $company ??= $this->companyFor($owner, ['sector_id' => Sector::where('slug', 'agences-immobilieres')->value('id')]);
        $pipeline = $this->pipeline();

        $this->actingAs($owner)->post(route('pm.opportunities.store'), [
            'company_id' => $company->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $pipeline->stages->firstWhere('name', $stage)->id,
            'title' => 'Mandat de gestion',
            'priority' => 'normale',
        ])->assertRedirect();

        return Opportunity::latest('id')->firstOrFail();
    }

    public function test_each_sector_has_a_pipeline_with_default_stages_in_order(): void
    {
        $this->assertSame(Sector::whereNull('parent_id')->count(), Pipeline::count());
        $names = $this->pipeline()->stages->pluck('name')->all();

        $this->assertSame(['Nouveau', 'À contacter', 'Contact effectué', 'Relance', 'Intéressé', 'Rendez-vous', 'Proposition envoyée', 'Gagné', 'Perdu', 'À recontacter'], $names);
        $this->assertSame('won', $this->pipeline()->stages->firstWhere('name', 'Gagné')->type);
    }

    public function test_opportunity_creation_records_history_and_activity(): void
    {
        $user = $this->userWithRole('commercial');
        $opportunity = $this->opportunityFor($user);

        $this->assertSame($user->id, $opportunity->owner_id);
        $this->assertSame('Nouveau', $opportunity->stage->name);
        $this->assertSame(1, $opportunity->history()->count());
        $this->assertTrue($opportunity->company->activities()->where('body', 'like', 'Opportunité créée%')->exists());
    }

    public function test_kanban_move_updates_stage_history_and_closing(): void
    {
        $user = $this->userWithRole('commercial');
        $opportunity = $this->opportunityFor($user);
        $won = $this->pipeline()->stages->firstWhere('name', 'Gagné');
        $open = $this->pipeline()->stages->firstWhere('name', 'Intéressé');

        $this->actingAs($user)->postJson(route('pm.opportunities.move', $opportunity), ['stage_id' => $won->id, 'position' => 0])
            ->assertOk()->assertJson(['ok' => true, 'changed' => true, 'stage' => 'Gagné']);
        $opportunity->refresh();
        $this->assertNotNull($opportunity->closed_at);
        $this->assertSame(2, StageHistory::where('opportunity_id', $opportunity->id)->count());
        $this->assertDatabaseHas('pm_audit_logs', ['action' => 'opportunity.stage_changed', 'subject_id' => $opportunity->id]);

        $this->actingAs($user)->postJson(route('pm.opportunities.move', $opportunity), ['stage_id' => $open->id])->assertOk();
        $this->assertNull($opportunity->fresh()->closed_at, 'rouvrir une opportunité efface la date de clôture');
    }

    public function test_cards_are_reordered_within_a_column(): void
    {
        $user = $this->userWithRole('commercial');
        $a = $this->opportunityFor($user);
        $b = $this->opportunityFor($user);
        $stage = $a->stage_id;

        $this->actingAs($user)->postJson(route('pm.opportunities.move', $b), ['stage_id' => $stage, 'position' => 0])
            ->assertOk()->assertJson(['changed' => false]);

        $this->assertSame([$b->id, $a->id], Opportunity::where('stage_id', $stage)->orderBy('position')->pluck('id')->all());
    }

    public function test_cannot_move_to_a_stage_of_another_pipeline(): void
    {
        $user = $this->userWithRole('commercial');
        $opportunity = $this->opportunityFor($user);
        $foreignStage = $this->pipeline('syndics-de-copropriete')->stages->first();

        $this->actingAs($user)->postJson(route('pm.opportunities.move', $opportunity), ['stage_id' => $foreignStage->id])
            ->assertStatus(422)->assertJsonValidationErrors('stage_id');
        $this->assertSame('Nouveau', $opportunity->fresh()->stage->name);
    }

    public function test_scope_and_permissions_apply_to_opportunities(): void
    {
        $alice = $this->userWithRole('commercial');
        $bob = $this->userWithRole('commercial');
        $opportunity = $this->opportunityFor($bob);
        $stage = $this->pipeline()->stages->firstWhere('name', 'Intéressé');

        $this->actingAs($alice)->get(route('pm.opportunities.show', $opportunity))->assertForbidden();
        $this->actingAs($alice)->postJson(route('pm.opportunities.move', $opportunity), ['stage_id' => $stage->id])->assertForbidden();
        $this->actingAs($alice)->get(route('pm.pipelines.show', $this->pipeline()))->assertOk()->assertDontSee('Mandat de gestion');
        $this->actingAs($bob)->get(route('pm.pipelines.show', $this->pipeline()))->assertOk()->assertSee('Mandat de gestion');

        $reader = $this->userWithRole('lecture_seule');
        $this->actingAs($reader)->get(route('pm.pipelines.show', $this->pipeline()))->assertOk()->assertSee('Mandat de gestion');
        $this->actingAs($reader)->postJson(route('pm.opportunities.move', $opportunity), ['stage_id' => $stage->id])->assertForbidden();
    }

    public function test_team_manager_can_move_team_opportunities(): void
    {
        $team = Team::create(['name' => 'Sud']);
        $manager = $this->userWithRole('responsable_commercial', ['team_id' => $team->id]);
        $member = $this->userWithRole('commercial', ['team_id' => $team->id]);
        $opportunity = $this->opportunityFor($member);
        $stage = $this->pipeline()->stages->firstWhere('name', 'Rendez-vous');

        $this->actingAs($manager)->postJson(route('pm.opportunities.move', $opportunity), ['stage_id' => $stage->id])->assertOk();
        $this->assertSame('Rendez-vous', $opportunity->fresh()->stage->name);
    }

    public function test_opportunity_cannot_be_created_for_out_of_scope_or_client_company(): void
    {
        $user = $this->userWithRole('commercial');
        $foreign = $this->companyFor($this->userWithRole('commercial'));
        $client = $this->companyFor($user);
        $client->forceFill(['lifecycle' => 'client'])->save();
        $pipeline = $this->pipeline();

        foreach ([$foreign, $client] as $company) {
            $this->actingAs($user)->post(route('pm.opportunities.store'), [
                'company_id' => $company->id, 'pipeline_id' => $pipeline->id, 'stage_id' => $pipeline->stages->first()->id,
                'title' => 'X', 'priority' => 'normale',
            ])->assertNotFound();
        }
        $this->assertSame(0, Opportunity::count());
    }

    public function test_list_filters_overdue_follow_ups(): void
    {
        $user = $this->userWithRole('commercial');
        $late = $this->opportunityFor($user);
        $late->forceFill(['follow_up_at' => today()->subDays(2), 'title' => 'Relance oubliée'])->save();
        $later = $this->opportunityFor($user);
        $later->forceFill(['follow_up_at' => today()->addDays(5), 'title' => 'Relance planifiée'])->save();

        $this->actingAs($user)->get(route('pm.opportunities.index', ['state' => 'overdue']))
            ->assertOk()->assertSee('Relance oubliée')->assertDontSee('Relance planifiée');
    }

    public function test_pages_render(): void
    {
        $admin = $this->userWithRole('administrateur');
        $opportunity = $this->opportunityFor($admin);

        foreach ([
            route('pm.pipelines.overview'), route('pm.pipelines.show', $this->pipeline()), route('pm.opportunities.index'),
            route('pm.opportunities.show', $opportunity), route('pm.opportunities.edit', $opportunity),
            route('pm.opportunities.create', ['company_id' => $opportunity->company_id]),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
        $this->actingAs($admin)->get(route('pm.pipelines.show'))->assertRedirect();
    }

    public function test_opportunities_of_deleted_companies_leave_the_board(): void
    {
        $user = $this->userWithRole('responsable_commercial');
        $opportunity = $this->opportunityFor($user);
        $opportunity->company->delete();

        $this->actingAs($user)->get(route('pm.pipelines.show', $this->pipeline()))->assertOk()->assertDontSee('Mandat de gestion');
        $this->actingAs($user)->get(route('pm.opportunities.index'))->assertOk()->assertDontSee('Mandat de gestion');
        $this->actingAs($user)->get(route('pm.pipelines.overview'))->assertOk();
        $this->actingAs($user)->get(route('pm.opportunities.show', $opportunity))->assertNotFound();
    }
}
