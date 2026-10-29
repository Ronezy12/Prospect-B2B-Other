<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TourTest extends TestCase
{
    use RefreshDatabase;

    public function test_tour_starts_once_per_screen_and_can_be_replayed_or_disabled(): void
    {
        $user = $this->userWithRole('commercial');

        $this->actingAs($user)->get(route('pm.dashboard'))->assertOk()
            ->assertSee('id="pm-tour"', false)->assertSee('data-autostart="1"', false)
            ->assertSee('Bienvenue sur votre espace de prospection')->assertSee('Ajouter mon premier prospect');

        $this->actingAs($user)->postJson(route('pm.tours.done'), ['key' => 'pm.dashboard'])->assertOk();
        $this->actingAs($user)->postJson(route('pm.tours.done'), ['key' => 'pm.dashboard'])->assertOk();
        $this->actingAs($user)->postJson(route('pm.tours.done'), ['key' => '<script>'])->assertUnprocessable();
        $this->actingAs($user)->get(route('pm.dashboard'))->assertSee('data-autostart="0"', false)->assertSee('data-tour-start', false);
        $this->actingAs($user)->get(route('pm.companies.index'))->assertSee('data-autostart="1"', false);

        $this->actingAs($user)->post(route('pm.tours.preference'), ['enabled' => '0'])->assertSessionHas('success');
        $this->actingAs($user)->get(route('pm.companies.index'))->assertSee('data-autostart="0"', false);
        $this->actingAs($user)->post(route('pm.tours.preference'), ['enabled' => '1']);
        $this->actingAs($user)->get(route('pm.dashboard'))->assertSee('data-autostart="1"', false);
    }

    public function test_suggestions_respect_permissions(): void
    {
        $reader = $this->userWithRole('lecture_seule');
        $this->actingAs($reader)->get(route('pm.dashboard'))->assertOk()
            ->assertDontSee('Ajouter mon premier prospect')->assertSee('Voir les prospects');
        $this->actingAs($reader)->get(route('pm.companies.index'))->assertDontSee('Importer un fichier Excel');
    }
}
