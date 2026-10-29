<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Opportunity;
use App\Models\Task;
use App\Models\User;
use App\Services\ActivityRecorder;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    public const VIEWS = [
        'retard' => 'En retard',
        'aujourdhui' => 'Aujourd\'hui',
        'a_venir' => 'À venir',
        'terminees' => 'Terminées',
    ];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ActivityRecorder $activities,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Task::class);
        $user = $request->user();
        $view = array_key_exists((string) $request->query('vue'), self::VIEWS) ? $request->query('vue') : 'aujourdhui';
        $assignee = $request->query('assignee_id');
        $mine = $assignee === null || $assignee === '' ? 'me' : $assignee;

        $base = Task::visibleTo($user)
            ->when($mine === 'me', fn (Builder $q) => $q->where('assignee_id', $user->id))
            ->when(is_numeric($mine), fn (Builder $q) => $q->where('assignee_id', (int) $mine));

        $counts = [
            'retard' => (clone $base)->where('status', 'a_faire')->where('due_at', '<', today())->count(),
            'aujourdhui' => (clone $base)->where('status', 'a_faire')->whereBetween('due_at', [today(), today()->endOfDay()])->count(),
            'a_venir' => (clone $base)->where('status', 'a_faire')->where('due_at', '>', today()->endOfDay())->count(),
        ];

        $tasks = (clone $base)
            ->when($view === 'retard', fn (Builder $q) => $q->where('status', 'a_faire')->where('due_at', '<', today())->orderBy('due_at'))
            ->when($view === 'aujourdhui', fn (Builder $q) => $q->where('status', 'a_faire')->whereBetween('due_at', [today(), today()->endOfDay()])->orderBy('due_at'))
            ->when($view === 'a_venir', fn (Builder $q) => $q->where('status', 'a_faire')->where('due_at', '>', today()->endOfDay())->orderBy('due_at'))
            ->when($view === 'terminees', fn (Builder $q) => $q->where('status', '!=', 'a_faire')->orderByDesc('completed_at')->orderByDesc('id'))
            ->with(['company:id,name', 'opportunity:id,title', 'assignee:id,name'])
            ->paginate(config('prospection.per_page'))
            ->withQueryString();

        return view('tasks.index', [
            'tasks' => $tasks,
            'view' => $view,
            'counts' => $counts,
            'assignee' => $mine,
            'users' => $user->can('prospects.view_team') || $user->can('prospects.view_all')
                ? User::where('is_active', true)
                    ->unless($user->can('prospects.view_all'), fn ($q) => $q->where('team_id', $user->team_id))
                    ->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Task::class);
        [$company, $opportunity] = $this->context($request->integer('company_id') ?: null, $request->integer('opportunity_id') ?: null, $request->user());

        return view('tasks.form', [
            'task' => (new Task([
                'type' => 'relance',
                'priority' => 'normale',
                'due_at' => now()->addDay()->setTime(9, 0),
                'title' => $opportunity ? 'Relancer : '.$opportunity->title : ($company ? 'Relancer '.$company->name : ''),
            ]))->forceFill(['company_id' => $company?->id, 'opportunity_id' => $opportunity?->id]),
            'company' => $company,
            'opportunity' => $opportunity,
        ] + $this->formData($request->user()));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Task::class);
        $data = $this->validated($request);
        [$company, $opportunity] = $this->context($data['company_id'] ?? null, $data['opportunity_id'] ?? null, $request->user());

        $task = new Task($data);
        $task->company_id = $company?->id ?? $opportunity?->company_id;
        $task->opportunity_id = $opportunity?->id;
        $task->assignee_id = $this->assignee($request->user(), $data['assignee_id'] ?? null);
        $task->created_by = $request->user()->id;
        $task->save();

        if ($task->company) {
            $this->activities->record($task->company, 'systeme', "Tâche planifiée pour le {$task->due_at->format('d/m/Y à H:i')} : {$task->title}.", ['task_id' => $task->id]);
        }
        $this->audit->log('task.created', $task, ['titre' => $task->title]);

        return $this->back($task, 'Tâche créée.');
    }

    public function edit(Request $request, Task $task): View
    {
        Gate::authorize('update', $task);

        return view('tasks.form', [
            'task' => $task,
            'company' => $task->company,
            'opportunity' => $task->opportunity,
        ] + $this->formData($request->user()));
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);
        $data = $this->validated($request);

        $task->fill(collect($data)->only(['title', 'description', 'type', 'priority', 'due_at'])->all());
        if (array_key_exists('assignee_id', $data)) {
            $task->assignee_id = $this->assignee($request->user(), $data['assignee_id'], $task->assignee_id);
        }
        $task->save();
        if ($changes = $this->audit->diff($task)) {
            $this->audit->log('task.updated', $task, $changes);
        }

        return $this->back($task, 'Tâche enregistrée.');
    }

    public function complete(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);
        $status = $request->validate(['status' => ['required', Rule::in(['fait', 'annule', 'a_faire'])]])['status'];

        $task->status = $status;
        $task->completed_at = $status === 'a_faire' ? null : now();
        $task->completed_by = $status === 'a_faire' ? null : $request->user()->id;
        $task->save();

        if ($task->company && $status === 'fait') {
            $this->activities->record($task->company, $task->type === 'appel' ? 'appel' : 'note', "Tâche réalisée : {$task->title}.", ['task_id' => $task->id]);
        }
        $this->audit->log('task.status', $task, ['statut' => $status]);

        return back()->with('success', match ($status) {
            'fait' => 'Tâche marquée comme faite.',
            'annule' => 'Tâche annulée.',
            default => 'Tâche rouverte.',
        });
    }

    public function destroy(Task $task): RedirectResponse
    {
        Gate::authorize('delete', $task);
        $task->delete();
        $this->audit->log('task.deleted', $task, ['titre' => $task->title]);

        return redirect()->route('pm.tasks.index')->with('success', 'Tâche supprimée.');
    }

    /**
     * Le rattachement à une fiche ou une opportunité exige d'y avoir accès.
     *
     * @return array{0: ?Company, 1: ?Opportunity}
     */
    private function context(?int $companyId, ?int $opportunityId, User $user): array
    {
        $opportunity = $opportunityId ? Opportunity::visibleTo($user)->findOrFail($opportunityId) : null;
        $company = $companyId ? Company::visibleTo($user)->findOrFail($companyId) : $opportunity?->company;
        if ($opportunity && $company && $opportunity->company_id !== $company->id) {
            abort(422, 'L\'opportunité n\'appartient pas à cette entreprise.');
        }

        return [$company, $opportunity];
    }

    /** Sans droit d'assignation, on ne peut créer des tâches que pour soi. */
    private function assignee(User $user, mixed $requested, ?int $current = null): int
    {
        if (! $requested || ! $user->can('prospects.assign')) {
            return $current ?? $user->id;
        }

        return (int) $requested;
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'company_id' => ['nullable', 'integer'],
            'opportunity_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', Rule::in(array_keys(config('prospection.task_types')))],
            'priority' => ['required', Rule::in(array_keys(config('prospection.priorities')))],
            'due_at' => ['required', 'date'],
            'assignee_id' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)],
        ], [], ['title' => 'intitulé', 'due_at' => 'échéance', 'assignee_id' => 'responsable']);
    }

    private function back(Task $task, string $message): RedirectResponse
    {
        $target = match (true) {
            (bool) $task->opportunity_id => route('pm.opportunities.show', $task->opportunity_id),
            (bool) $task->company_id => route('pm.companies.show', $task->company_id).'#taches',
            default => route('pm.tasks.index'),
        };

        return redirect()->to($target)->with('success', $message);
    }

    private function formData(User $user): array
    {
        return [
            'users' => $user->can('prospects.assign')
                ? User::where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : collect([$user]),
        ];
    }
}
