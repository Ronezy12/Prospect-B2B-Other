<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $extra = []): array
    {
        return $extra + [
            'title' => 'Rappeler le gérant',
            'type' => 'appel',
            'priority' => 'normale',
            'due_at' => now()->addDay()->format('Y-m-d H:i'),
        ];
    }

    public function test_task_is_created_from_a_company_and_assigned_to_self_without_assign_right(): void
    {
        $user = $this->userWithRole('commercial');
        $colleague = $this->userWithRole('commercial');
        $company = $this->companyFor($user);

        $this->actingAs($user)->post(route('pm.tasks.store'), $this->payload(['company_id' => $company->id, 'assignee_id' => $colleague->id]))
            ->assertRedirect(route('pm.companies.show', $company).'#taches');

        $task = Task::firstOrFail();
        $this->assertSame($user->id, $task->assignee_id);
        $this->assertSame($company->id, $task->company_id);
        $this->assertTrue($company->activities()->where('body', 'like', 'Tâche planifiée%')->exists());
    }

    public function test_task_cannot_be_attached_to_a_company_outside_scope(): void
    {
        $user = $this->userWithRole('commercial');
        $foreign = $this->companyFor($this->userWithRole('commercial'));

        $this->actingAs($user)->post(route('pm.tasks.store'), $this->payload(['company_id' => $foreign->id]))->assertNotFound();
        $this->assertSame(0, Task::count());
    }

    public function test_task_visibility_and_team_manager_access(): void
    {
        $team = Team::create(['name' => 'Ouest']);
        $manager = $this->userWithRole('responsable_commercial', ['team_id' => $team->id]);
        $member = $this->userWithRole('commercial', ['team_id' => $team->id]);
        $outsider = $this->userWithRole('commercial');

        $this->actingAs($member)->post(route('pm.tasks.store'), $this->payload(['title' => 'Tâche du membre']));
        $task = Task::firstOrFail();

        $this->actingAs($outsider)->get(route('pm.tasks.edit', $task))->assertForbidden();
        $this->actingAs($outsider)->post(route('pm.tasks.complete', $task), ['status' => 'fait'])->assertForbidden();
        $this->actingAs($outsider)->get(route('pm.tasks.index', ['vue' => 'a_venir', 'assignee_id' => 'all']))->assertDontSee('Tâche du membre');

        $this->actingAs($manager)->get(route('pm.tasks.index', ['vue' => 'a_venir', 'assignee_id' => 'all']))->assertSee('Tâche du membre');
        $this->actingAs($manager)->post(route('pm.tasks.complete', $task), ['status' => 'fait'])->assertRedirect();
        $this->assertSame('fait', $task->fresh()->status);
    }

    public function test_complete_and_reopen(): void
    {
        $user = $this->userWithRole('commercial');
        $company = $this->companyFor($user);
        $this->actingAs($user)->post(route('pm.tasks.store'), $this->payload(['company_id' => $company->id]));
        $task = Task::firstOrFail();

        $this->actingAs($user)->post(route('pm.tasks.complete', $task), ['status' => 'fait']);
        $task->refresh();
        $this->assertSame('fait', $task->status);
        $this->assertSame($user->id, $task->completed_by);
        $this->assertTrue($company->activities()->where('body', 'like', 'Tâche réalisée%')->exists());

        $this->actingAs($user)->post(route('pm.tasks.complete', $task), ['status' => 'a_faire']);
        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_overdue_tab_and_navigation_counter(): void
    {
        $user = $this->userWithRole('commercial');
        $this->actingAs($user)->post(route('pm.tasks.store'), $this->payload(['title' => 'Relance en retard', 'due_at' => now()->subDays(2)->format('Y-m-d H:i')]));
        $this->actingAs($user)->post(route('pm.tasks.store'), $this->payload(['title' => 'Relance future', 'due_at' => now()->addDays(3)->format('Y-m-d H:i')]));

        $this->actingAs($user)->get(route('pm.tasks.index', ['vue' => 'retard']))
            ->assertOk()->assertSee('Relance en retard')->assertDontSee('Relance future')
            ->assertSee('<span class="nav-count" title="Tâches en retard">1</span>', false);
    }

    public function test_read_only_user_cannot_create_tasks(): void
    {
        $reader = $this->userWithRole('lecture_seule');
        $this->actingAs($reader)->get(route('pm.tasks.create'))->assertForbidden();
        $this->actingAs($reader)->post(route('pm.tasks.store'), $this->payload())->assertForbidden();
    }
}
