<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\Sector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_require_permission(): void
    {
        $pipeline = Pipeline::firstOrFail();
        $manager = $this->userWithRole('responsable_commercial');

        $this->actingAs($manager)->get(route('pm.settings.pipelines.index'))->assertForbidden();
        $this->actingAs($manager)->post(route('pm.settings.stages.store', $pipeline), ['name' => 'X', 'type' => 'open', 'probability' => 10])->assertForbidden();
        $this->actingAs($this->userWithRole('administrateur'))->get(route('pm.settings.pipelines.index'))->assertOk();
    }

    public function test_new_sector_gets_its_pipeline_and_subsector_does_not(): void
    {
        $admin = $this->userWithRole('administrateur');
        $this->actingAs($admin)->post(route('pm.settings.sectors.store'), ['name' => 'Hôtellerie'])->assertSessionHas('success');

        $sector = Sector::where('name', 'Hôtellerie')->firstOrFail();
        $this->assertSame(10, $sector->pipeline->stages()->count());

        $this->actingAs($admin)->post(route('pm.settings.sectors.store'), ['name' => 'Hôtels 4 étoiles', 'parent_id' => $sector->id]);
        $this->assertNull(Sector::where('name', 'Hôtels 4 étoiles')->firstOrFail()->pipeline);

        $this->actingAs($admin)->post(route('pm.settings.sectors.store'), ['name' => 'Hôtellerie'])->assertSessionHasErrors('name');
    }

    public function test_stage_management_rules(): void
    {
        $admin = $this->userWithRole('administrateur');
        $pipeline = Sector::where('slug', 'agences-immobilieres')->firstOrFail()->pipeline;
        $stage = $pipeline->stages()->where('name', 'Relance')->firstOrFail();

        $this->actingAs($admin)->post(route('pm.settings.stages.store', $pipeline), ['name' => 'Relance', 'type' => 'open', 'probability' => 10])
            ->assertSessionHasErrors('name');

        $company = $this->companyFor($admin);
        $opportunity = new Opportunity(['title' => 'Test', 'priority' => 'normale']);
        $opportunity->company_id = $company->id;
        $opportunity->pipeline_id = $pipeline->id;
        $opportunity->stage_id = $stage->id;
        $opportunity->save();

        $this->actingAs($admin)->delete(route('pm.settings.stages.destroy', $stage))->assertSessionHasErrors('stage');
        $this->actingAs($admin)->put(route('pm.settings.stages.update', $stage), ['name' => 'Relance', 'type' => 'won', 'probability' => 100])
            ->assertSessionHasErrors('type');
        $this->assertSame('open', $stage->fresh()->type);

        $empty = $pipeline->stages()->where('name', 'À recontacter')->firstOrFail();
        $this->actingAs($admin)->delete(route('pm.settings.stages.destroy', $empty))->assertSessionHas('success');
        $this->assertModelMissing($empty);
    }

    public function test_stages_can_be_reordered(): void
    {
        $admin = $this->userWithRole('administrateur');
        $pipeline = Pipeline::firstOrFail();
        $second = $pipeline->stages()->get()[1];

        $this->actingAs($admin)->post(route('pm.settings.stages.move', $second), ['direction' => 'up']);

        $this->assertSame($second->id, $pipeline->stages()->first()->id);
    }

    public function test_sources_can_be_added_and_renamed(): void
    {
        $admin = $this->userWithRole('administrateur');
        $this->actingAs($admin)->post(route('pm.settings.sources.store'), ['name' => 'Partenaire bancaire'])->assertSessionHas('success');
        $this->assertDatabaseHas('pm_sources', ['name' => 'Partenaire bancaire']);
        $this->actingAs($admin)->post(route('pm.settings.sources.store'), ['name' => 'Partenaire bancaire'])->assertSessionHasErrors('name');
    }
}
