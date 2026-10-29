<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PipelineBoardController extends Controller
{
    /** Nombre maximal de cartes chargées par colonne (le reste est accessible en vue liste). */
    private const PER_COLUMN = 60;

    public function show(Request $request, ?Pipeline $pipeline = null): View|RedirectResponse
    {
        Gate::authorize('viewAny', Opportunity::class);

        $pipelines = Pipeline::with('sector:id,name')->where('is_active', true)->orderBy('name')->get();
        if (! $pipeline) {
            $first = $pipelines->first();

            return $first
                ? redirect()->route('pm.pipelines.show', $first)
                : redirect()->route('pm.pipelines.overview');
        }
        abort_unless($pipeline->is_active, 404);

        $filters = OpportunityController::filters($request);
        unset($filters['pipeline_id'], $filters['stage_id'], $filters['state']);

        $stages = $pipeline->stages()->get();
        $base = Opportunity::visibleTo($request->user())->active()->where('pipeline_id', $pipeline->id)->filter($filters);
        $counts = (clone $base)->selectRaw('stage_id, count(*) as n, sum(amount) as total')->groupBy('stage_id')->get()->keyBy('stage_id');

        $columns = $stages->map(fn ($stage) => [
            'stage' => $stage,
            'count' => (int) ($counts[$stage->id]->n ?? 0),
            'amount' => (float) ($counts[$stage->id]->total ?? 0),
            'cards' => (clone $base)->where('stage_id', $stage->id)
                ->with(['company:id,name,city', 'owner:id,name'])
                ->orderBy('position')->orderBy('id')
                ->limit(self::PER_COLUMN)->get(),
        ]);

        return view('pipelines.board', [
            'pipeline' => $pipeline,
            'pipelines' => $pipelines,
            'columns' => $columns,
            'filters' => $filters,
            'perColumn' => self::PER_COLUMN,
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'canMove' => $request->user()->can('prospects.update'),
        ]);
    }

    /** Vue consolidée de toutes les pipelines. */
    public function overview(Request $request): View
    {
        Gate::authorize('viewAny', Opportunity::class);

        $pipelines = Pipeline::with(['stages', 'sector:id,name'])->where('is_active', true)->orderBy('name')->get();
        $stats = Opportunity::visibleTo($request->user())->active()
            ->selectRaw('pipeline_id, stage_id, count(*) as n, sum(amount) as total')
            ->groupBy('pipeline_id', 'stage_id')->get()
            ->groupBy('pipeline_id');

        $visible = Opportunity::visibleTo($request->user())->active();
        $totals = [
            'open' => (clone $visible)->whereNull('closed_at')->count(),
            'won' => (clone $visible)->whereHas('stage', fn ($q) => $q->where('type', 'won'))->count(),
            'lost' => (clone $visible)->whereHas('stage', fn ($q) => $q->where('type', 'lost'))->count(),
            'overdue' => (clone $visible)->whereNull('closed_at')->whereDate('follow_up_at', '<', today())->count(),
            'weighted' => (float) (clone $visible)->whereNull('closed_at')
                ->join('pm_pipeline_stages as s', 's.id', '=', 'pm_opportunities.stage_id')
                ->selectRaw('coalesce(sum(pm_opportunities.amount * s.probability / 100), 0) as w')->value('w'),
        ];

        return view('pipelines.overview', compact('pipelines', 'stats', 'totals'));
    }
}
