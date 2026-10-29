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

    public function test_guide_is_available_to_every_role_and_linked_from_sections(): void
    {
        foreach (['commercial', 'marketing', 'lecture_seule'] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('pm.guide'))->assertOk()
                ->assertSee('Les mots à connaître')->assertSee('Envoyer un e-mail à plusieurs entreprises');
        }
        $this->actingAs($this->userWithRole('marketing'))->get(route('pm.campaigns.index'))->assertOk()
            ->assertSee(route('pm.guide').'#emails', false)->assertSee('E-mails groupés');
    }
}
