<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Opportunity;
use App\Models\Sector;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickOutcomeTest extends TestCase
{
    use RefreshDatabase;

    private User $sales;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sales = $this->userWithRole('commercial');
        $this->company = new Company(['name' => 'Agence du Parc', 'status' => 'nouveau', 'sector_id' => Sector::where('slug', 'agences-immobilieres')->value('id')]);
        $this->company->owner_id = $this->sales->id;
        $this->company->save();
    }

    private function todayTask(): Task
    {
        $task = new Task(['title' => 'Appeler', 'type' => 'appel', 'priority' => 'normale', 'due_at' => now()->setTime(8, 0)]);
        $task->company_id = $this->company->id;
        $task->assignee_id = $this->sales->id;
        $task->created_by = $this->sales->id;
        $task->save();

        return $task;
    }

    public function test_interested_updates_everything_in_one_click(): void
    {
        $task = $this->todayTask();
        $this->actingAs($this->sales)->post(route('pm.companies.outcome', $this->company), ['outcome' => 'interesse', 'note' => 'Rappeler après 14 h'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Intéressé') && str_contains($m, 'prochain rappel'));

        $this->assertSame('qualifie', $this->company->fresh()->status);
        $this->assertSame('fait', $task->fresh()->status, 'le rappel du jour est clôturé');
        $opportunity = Opportunity::where('company_id', $this->company->id)->firstOrFail();
        $this->assertSame('Intéressé', $opportunity->stage->name);
        $next = Task::where('company_id', $this->company->id)->where('status', 'a_faire')->firstOrFail();
        $this->assertSame($this->sales->id, $next->assignee_id);
        $this->assertFalse($next->due_at->isWeekend());
        // Un champ date envoyé avec un autre résultat que « rendez-vous » est ignoré.
        $this->actingAs($this->sales)->post(route('pm.companies.outcome', $this->company), ['outcome' => 'pas_de_reponse', 'date' => now()->next('Saturday')->toDateString()]);
        $this->assertFalse(Task::where('company_id', $this->company->id)->where('status', 'a_faire')->latest('id')->first()->due_at->isWeekend());
        $this->assertTrue($next->due_at->gt(now()));
        $this->assertTrue($this->company->activities()->where('body', 'like', '%Rappeler après 14 h%')->exists());
    }

    public function test_appointment_uses_chosen_date_and_devis_moves_stage(): void
    {
        $date = now()->addDays(5)->toDateString();
        $this->actingAs($this->sales)->post(route('pm.companies.outcome', $this->company), ['outcome' => 'rdv', 'date' => $date]);
        $this->assertSame($date, Task::where('company_id', $this->company->id)->where('status', 'a_faire')->value('due_at')->toDateString());
        $this->assertSame('Rendez-vous', Opportunity::firstOrFail()->stage->name);

        $this->actingAs($this->sales)->post(route('pm.companies.outcome', $this->company), ['outcome' => 'devis']);
        $this->assertSame('Proposition envoyée', Opportunity::firstOrFail()->stage->name);
        $this->assertSame(1, Opportunity::count(), 'la même affaire avance');
    }

    public function test_not_interested_closes_deal_and_cancels_reminders(): void
    {
        $this->actingAs($this->sales)->post(route('pm.companies.outcome', $this->company), ['outcome' => 'interesse']);
        $this->actingAs($this->sales)->post(route('pm.companies.outcome', $this->company), ['outcome' => 'pas_interesse', 'note' => 'Budget']);

        $this->assertSame('non_pertinent', $this->company->fresh()->status);
        $opportunity = Opportunity::firstOrFail();
        $this->assertSame('lost', $opportunity->stage->type);
        $this->assertSame('Budget', $opportunity->lost_reason);
        $this->assertSame(0, Task::where('company_id', $this->company->id)->where('status', 'a_faire')->count());
    }

    public function test_signed_wins_and_converts_when_allowed(): void
    {
        $admin = $this->userWithRole('administrateur');
        $this->actingAs($admin)->post(route('pm.companies.outcome', $this->company), ['outcome' => 'gagne']);
        $this->assertSame('won', Opportunity::firstOrFail()->stage->type);
        $this->assertTrue($this->company->fresh()->isClient());

        $other = new Company(['name' => 'Garage', 'status' => 'nouveau']);
        $other->owner_id = $this->sales->id;
        $other->save();
        $this->actingAs($this->sales)->post(route('pm.companies.outcome', $other), ['outcome' => 'gagne']);
        $this->assertFalse($other->fresh()->isClient(), 'un commercial ne convertit pas lui-même');
    }

    public function test_access_and_validation(): void
    {
        $this->actingAs($this->userWithRole('commercial'))->post(route('pm.companies.outcome', $this->company), ['outcome' => 'interesse'])->assertForbidden();
        $this->actingAs($this->userWithRole('lecture_seule'))->post(route('pm.companies.outcome', $this->company), ['outcome' => 'interesse'])->assertForbidden();
        $this->actingAs($this->sales)->post(route('pm.companies.outcome', $this->company), ['outcome' => 'inconnu'])->assertSessionHasErrors('outcome');
        $this->actingAs($this->sales)->get(route('pm.companies.show', $this->company))->assertOk()->assertSee('Que s\'est-il passé ?', false);
    }
}
