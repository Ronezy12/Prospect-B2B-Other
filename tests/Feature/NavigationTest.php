<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_shows_only_sections_the_role_can_use(): void
    {
        $sales = $this->userWithRole('commercial');
        $this->actingAs($sales)->get(route('pm.dashboard'))->assertOk()
            ->assertSee('Comment fonctionne le module')
            ->assertSee('Réseaux sociaux')
            ->assertDontSee(route('pm.settings.index'))
            ->assertDontSee(route('pm.automations.index'));
        $this->actingAs($sales)->get(route('pm.settings.index'))->assertForbidden();

        $admin = $this->userWithRole('administrateur');
        $this->actingAs($admin)->get(route('pm.settings.index'))->assertOk()
            ->assertSee('Utilisateurs et rôles')->assertSee('Connexion Meta');
        // Onglets de rubrique : la page Contacts propose Entreprises / Contacts / Imports.
        $this->actingAs($admin)->get(route('pm.contacts.index'))->assertOk()
            ->assertSee('class="tabs subnav"', false)->assertSee(route('pm.imports.index'));
    }
}
