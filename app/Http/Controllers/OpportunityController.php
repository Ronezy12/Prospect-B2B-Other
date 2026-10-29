<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OpportunityController extends Controller
{
    public const FILTERS = ['q', 'pipeline_id', 'stage_id', 'owner_id', 'priority', 'state'];

    public function __construct(
        private readonly PipelineService $pipelines,
        private readonly AuditLogger $audit,
    ) {}

    /** Vue liste, toutes pipelines confondues. */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Opportunity::class);
        $filters = self::filters($request, ['state' => 'open']);

        $opportunities = Opportunity::visibleTo($request->user())
            ->active()
            ->filter($filters)
            ->with(['company:id,name,city', 'stage:id,name,type', 'pipeline:id,name', 'owner:id,name'])
            ->orderByRaw('follow_up_at is null')->orderBy('follow_up_at')->orderByDesc('id')
            ->paginate(config('prospection.per_page'))
            ->withQueryString();

        return view('opportunities.index', [
            'opportunities' => $opportunities,
            'filters' => $filters,
            'pipelines' => Pipeline::with('stages')->where('is_active', true)->orderBy('name')->get(),
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Opportunity::class);
        $company = Company::visibleTo($request->user())->prospects()->findOrFail($request->integer('company_id'));
        Gate::authorize('update', $company);

        $pipeline = $company->sector?->pipeline ?? Pipeline::where('is_active', true)->orderBy('name')->first();

        return view('opportunities.form', [
            'opportunity' => new Opportunity(['company_id' => $company->id, 'priority' => 'normale', 'title' => 'Prospection '.$company->name]),
            'company' => $company,
            'defaultPipeline' => $pipeline,
        ] + $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Opportunity::class);
        $data = $this->validated($request, true);
        $company = Company::visibleTo($request->user())->prospects()->findOrFail($data['company_id']);
        Gate::authorize('update', $company);

        $pipeline = Pipeline::where('is_active', true)->findOrFail($data['pipeline_id']);
        $stage = $pipeline->stages()->findOrFail($data['stage_id']);

        $opportunity = new Opportunity($data);
        $opportunity->owner_id = $request->user()->can('prospects.assign') && ! empty($data['owner_id'])
            ? (int) $data['owner_id']
            : ($company->owner_id ?? $request->user()->id);
        $this->pipelines->create($opportunity, $pipeline, $stage, $request->user());

        return redirect()->route('pm.opportunities.show', $opportunity)->with('success', 'Opportunité créée.');
    }

    public function show(Opportunity $opportunity): View
    {
        Gate::authorize('view', $opportunity);
        $opportunity->load(['company', 'contact', 'pipeline.stages', 'stage', 'owner', 'history.user']);

        return view('opportunities.show', [
            'opportunity' => $opportunity,
            'tasks' => $opportunity->tasks()->with('assignee:id,name')->orderByRaw("status = 'a_faire' desc")->orderBy('due_at')->get(),
        ]);
    }

    public function edit(Opportunity $opportunity): View
    {
        Gate::authorize('update', $opportunity);

        return view('opportunities.form', [
            'opportunity' => $opportunity,
            'company' => $opportunity->company,
            'defaultPipeline' => $opportunity->pipeline,
        ] + $this->formData());
    }

    public function update(Request $request, Opportunity $opportunity): RedirectResponse
    {
        Gate::authorize('update', $opportunity);
        $data = $this->validated($request, false, $opportunity);

        $opportunity->fill(collect($data)->except(['company_id', 'stage_id', 'pipeline_id', 'owner_id'])->all());
        if ($request->user()->can('prospects.assign') && array_key_exists('owner_id', $data)) {
            $opportunity->owner_id = $data['owner_id'];
        }
        $opportunity->save();
        if ($changes = $this->audit->diff($opportunity)) {
            $this->audit->log('opportunity.updated', $opportunity, $changes);
        }

        $stage = $opportunity->pipeline->stages()->findOrFail($data['stage_id']);
        $this->pipelines->move($opportunity, $stage, null, $request->user());

        return redirect()->route('pm.opportunities.show', $opportunity)->with('success', 'Opportunité enregistrée.');
    }

    /** Déplacement depuis le Kanban (JSON) ou le formulaire de repli (redirection). */
    public function move(Request $request, Opportunity $opportunity): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $opportunity);
        $data = $request->validate([
            'stage_id' => ['required', 'integer', Rule::exists('pm_pipeline_stages', 'id')->where('pipeline_id', $opportunity->pipeline_id)],
            'position' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ], ['stage_id.exists' => 'Cette étape n\'appartient pas à la pipeline de l\'opportunité.']);

        $stage = PipelineStage::findOrFail($data['stage_id']);
        $changed = $this->pipelines->move($opportunity, $stage, $data['position'] ?? null, $request->user());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'changed' => $changed, 'stage' => $stage->name]);
        }

        return back()->with('success', $changed ? "Opportunité déplacée vers « {$stage->name} »." : 'Ordre mis à jour.');
    }

    public function destroy(Opportunity $opportunity): RedirectResponse
    {
        Gate::authorize('delete', $opportunity);
        $company = $opportunity->company;
        $opportunity->delete();
        $this->audit->log('opportunity.deleted', $opportunity, ['titre' => $opportunity->title]);

        return redirect()->route('pm.companies.show', $company)->with('success', 'Opportunité supprimée.');
    }

    /** @return array<string, string> */
    public static function filters(Request $request, array $defaults = []): array
    {
        $filters = array_filter($request->only(self::FILTERS), fn ($v) => is_string($v) && trim($v) !== '');
        $filters = array_map(fn ($v) => mb_substr(trim($v), 0, 100), $filters) + $defaults;
        if (($filters['state'] ?? null) === 'all') {
            unset($filters['state']);
        }

        return $filters;
    }

    private function validated(Request $request, bool $creating, ?Opportunity $opportunity = null): array
    {
        $pipelineId = $creating ? $request->input('pipeline_id') : $opportunity->pipeline_id;

        return $request->validate([
            'company_id' => [$creating ? 'required' : 'prohibited', 'integer'],
            'pipeline_id' => [$creating ? 'required' : 'prohibited', 'integer', 'exists:pm_pipelines,id'],
            'stage_id' => ['required', 'integer', Rule::exists('pm_pipeline_stages', 'id')->where('pipeline_id', $pipelineId)],
            'contact_id' => ['nullable', 'integer', Rule::exists('pm_contacts', 'id')->where('company_id', $creating ? $request->input('company_id') : $opportunity->company_id)->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:190'],
            'priority' => ['required', Rule::in(array_keys(config('prospection.priorities')))],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'next_action' => ['nullable', 'string', 'max:255'],
            'follow_up_at' => ['nullable', 'date'],
            'lost_reason' => ['nullable', 'string', 'max:255'],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)],
        ], ['stage_id.exists' => 'Choisissez une étape de la pipeline sélectionnée.'], [
            'stage_id' => 'étape', 'title' => 'intitulé', 'amount' => 'montant', 'follow_up_at' => 'date de relance', 'next_action' => 'prochaine action',
        ]);
    }

    private function formData(): array
    {
        return [
            'pipelines' => Pipeline::with('stages')->where('is_active', true)->orderBy('name')->get(),
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }
}
