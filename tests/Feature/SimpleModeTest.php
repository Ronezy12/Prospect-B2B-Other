<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpleModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_start_in_simple_mode_and_can_switch(): void
    {
        $sales = $this->userWithRole('commercial', ['simple_mode' => true]);
        $this->assertTrue(User::factory()->create()->fresh()->isSimple(), 'activé par défaut');

        $this->actingAs($sales)->get(route('pm.dashboard'))->assertOk()
            ->assertSee('à appeler')->assertSee('Mes prospects')->assertSee('Réseaux sociaux')->assertSee('Passer en mode complet')
            ->assertDontSee(route('pm.pipelines.show'));

        $this->actingAs($sales)->post(route('pm.mode.toggle'))->assertRedirect(route('pm.dashboard'));
        $this->assertFalse($sales->fresh()->isSimple());
        $this->actingAs($sales->fresh())->get(route('pm.dashboard'))->assertSee(route('pm.pipelines.show'))->assertSee('Passer en mode simple');
    }

    public function test_simple_screens_show_calls_and_outcome_buttons(): void
    {
        $sales = $this->userWithRole('commercial', ['simple_mode' => true]);
        $company = $this->companyFor($sales, ['name' => 'Agence du Parc']);
        $task = new Task(['title' => 'Rappeler', 'type' => 'appel', 'priority' => 'normale', 'due_at' => now()->subDay()]);
        $task->company_id = $company->id;
        $task->assignee_id = $sales->id;
        $task->created_by = $sales->id;
        $task->save();
        $this->companyFor($sales, ['name' => 'Garage Neuf', 'phone' => '0410203040']);

        $this->actingAs($sales)->get(route('pm.dashboard'))->assertOk()
            ->assertSee('Agence du Parc')->assertSee('En retard')->assertSee('Garage Neuf')->assertSee('jamais appelé')->assertSee('Rendez-vous')
            ->assertSee('tel:+33410203040', false)->assertSee('Pas de réponse');

        $this->actingAs($sales)->get(route('pm.companies.index'))->assertOk()->assertSee('Agence du Parc')->assertSee('data-open=', false)->assertSee('Rechercher');
        $this->actingAs($sales)->get(route('pm.companies.show', $company))->assertOk()
            ->assertSee('Résultat de votre dernier appel')->assertSee('Ajouter une note')->assertSee('Prochain rappel')->assertSee('Réseaux sociaux')->assertSee('Interlocuteurs')->assertSee('Affaires');

        $this->actingAs($sales)->post(route('pm.companies.outcome', $company), ['outcome' => 'rdv'])->assertSessionHas('success');
        $this->assertSame('qualifie', $company->fresh()->status);
        $this->assertFalse(Task::where('company_id', $company->id)->where('status', 'a_faire')->first()->due_at->isWeekend());
    }

    public function test_simple_add_form_creates_company_and_contact(): void
    {
        $sales = $this->userWithRole('commercial', ['simple_mode' => true]);
        $this->actingAs($sales)->get(route('pm.companies.create'))->assertOk()->assertSee('Nom de l\'entreprise', false)->assertDontSee('SIREN');
        $this->actingAs($sales)->post(route('pm.companies.store'), [
            'status' => 'nouveau', 'name' => 'Boulangerie Martin', 'city' => 'Lyon',
            'contact' => ['first_name' => 'Paul', 'last_name' => 'Martin', 'phone' => '06 12 34 56 78', 'email' => 'paul@boulangerie.fr'],
        ])->assertRedirect();
        $company = Company::where('name', 'Boulangerie Martin')->firstOrFail();
        $this->assertSame('paul@boulangerie.fr', $company->contacts()->value('email'));
        $this->assertSame($sales->id, $company->owner_id);
    }

    public function test_status_can_be_changed_from_the_card_and_whatsapp_link_is_shown(): void
    {
        $sales = $this->userWithRole('commercial', ['simple_mode' => true]);
        $company = $this->companyFor($sales, ['name' => 'Agence Rapide', 'phone' => '06 12 34 56 78']);

        $this->actingAs($sales)->get(route('pm.companies.index'))->assertOk()
            ->assertSee('https://wa.me/33612345678', false)->assertSee(route('pm.companies.status', $company), false)->assertDontSee('sms:', false);

        $this->actingAs($sales)->post(route('pm.companies.status', $company), ['status' => 'qualifie'])->assertSessionHas('success');
        $this->assertSame('qualifie', $company->fresh()->status);
        $this->assertTrue($company->activities()->where('body', 'like', 'Statut%Qualifié%')->exists());
        $this->actingAs($sales)->post(route('pm.companies.status', $company), ['status' => 'inconnu'])->assertSessionHasErrors('status');
        $this->actingAs($this->userWithRole('commercial'))->post(route('pm.companies.status', $company), ['status' => 'nouveau'])->assertForbidden();
    }
}
