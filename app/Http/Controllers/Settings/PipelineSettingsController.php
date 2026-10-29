<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Sector;
use App\Models\Source;
use App\Services\AuditLogger;
use App\Services\PipelineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Paramétrage des secteurs, sources, pipelines et étapes. */
class PipelineSettingsController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PipelineService $pipelines,
    ) {}

    public function index(Request $request): View
    {
        $this->guard($request);

        return view('settings.pipelines.index', [
            'sectors' => Sector::roots()->with(['children', 'pipeline'])->withCount('children')->get(),
            'sources' => Source::orderBy('name')->get(),
        ]);
    }

    public function edit(Request $request, Pipeline $pipeline): View
    {
        $this->guard($request);
        $pipeline->load(['sector', 'stages' => fn ($q) => $q->withCount('opportunities')]);

        return view('settings.pipelines.edit', ['pipeline' => $pipeline]);
    }

    public function update(Request $request, Pipeline $pipeline): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $pipeline->update(['name' => $data['name'], 'is_active' => (bool) ($data['is_active'] ?? false)]);
        $this->audit->log('pipeline.updated', $pipeline, $this->audit->diff($pipeline));

        return back()->with('success', 'Pipeline enregistrée.');
    }

    public function storeStage(Request $request, Pipeline $pipeline): RedirectResponse
    {
        $this->guard($request);
        $data = $this->stageData($request, $pipeline);
        $stage = $pipeline->stages()->create($data + ['position' => (int) $pipeline->stages()->max('position') + 1]);
        $this->audit->log('stage.created', $stage, $data);

        return back()->with('success', "Étape « {$stage->name} » ajoutée.");
    }

    public function updateStage(Request $request, PipelineStage $stage): RedirectResponse
    {
        $this->guard($request);
        $stage->update($this->stageData($request, $stage->pipeline, $stage));
        $this->audit->log('stage.updated', $stage, $this->audit->diff($stage));

        return back()->with('success', 'Étape enregistrée.');
    }

    public function moveStage(Request $request, PipelineStage $stage): RedirectResponse
    {
        $this->guard($request);
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        DB::transaction(function () use ($stage, $direction) {
            $ids = $stage->pipeline->stages()->pluck('id')->all();
            $index = array_search($stage->id, $ids, true);
            $swap = $direction === 'up' ? $index - 1 : $index + 1;
            if ($swap < 0 || $swap >= count($ids)) {
                return;
            }
            [$ids[$index], $ids[$swap]] = [$ids[$swap], $ids[$index]];
            foreach ($ids as $position => $id) {
                PipelineStage::whereKey($id)->update(['position' => $position]);
            }
        });

        return back();
    }

    /** Une étape utilisée par des opportunités ne peut pas être supprimée. */
    public function destroyStage(Request $request, PipelineStage $stage): RedirectResponse
    {
        $this->guard($request);
        if ($stage->opportunities()->withTrashed()->exists()) {
            return back()->withErrors(['stage' => "L'étape « {$stage->name} » contient des opportunités : déplacez-les avant de la supprimer."]);
        }
        if ($stage->pipeline->stages()->where('type', 'open')->count() === 1 && $stage->type === 'open') {
            return back()->withErrors(['stage' => 'Une pipeline doit conserver au moins une étape en cours.']);
        }
        $stage->delete();
        $this->audit->log('stage.deleted', $stage, ['nom' => $stage->name]);

        return back()->with('success', "Étape « {$stage->name} » supprimée.");
    }

    public function storeSector(Request $request): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', Rule::exists('pm_sectors', 'id')->whereNull('parent_id')],
        ], [], ['name' => 'nom du secteur']);

        $data['parent_id'] ??= null;
        $slug = Str::slug(($data['parent_id'] ? Sector::find($data['parent_id'])->slug.'-' : '').$data['name']);
        if (Sector::where('slug', $slug)->exists()) {
            return back()->withInput()->withErrors(['name' => 'Ce secteur existe déjà.']);
        }

        $sector = DB::transaction(function () use ($data, $slug) {
            $sector = Sector::create([
                'name' => $data['name'],
                'slug' => $slug,
                'parent_id' => $data['parent_id'] ?? null,
                'position' => (int) Sector::where('parent_id', $data['parent_id'] ?? null)->max('position') + 1,
            ]);
            if (! $sector->parent_id) {
                $this->pipelines->ensureForSector($sector);
            }

            return $sector;
        });
        $this->audit->log('sector.created', $sector, ['nom' => $sector->name]);

        return back()->with('success', $sector->parent_id
            ? "Sous-secteur « {$sector->name} » ajouté."
            : "Secteur « {$sector->name} » ajouté avec sa pipeline et les étapes par défaut.");
    }

    public function updateSector(Request $request, Sector $sector): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $sector->update(['name' => $data['name'], 'is_active' => (bool) ($data['is_active'] ?? false)]);
        $this->audit->log('sector.updated', $sector, $this->audit->diff($sector));

        return back()->with('success', 'Secteur enregistré.');
    }

    public function storeSource(Request $request): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:120', 'unique:pm_sources,name']], [], ['name' => 'nom de la source']);
        $source = Source::create($data);
        $this->audit->log('source.created', $source, $data);

        return back()->with('success', "Source « {$source->name} » ajoutée.");
    }

    public function updateSource(Request $request, Source $source): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('pm_sources', 'name')->ignore($source->id)],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $source->update(['name' => $data['name'], 'is_active' => (bool) ($data['is_active'] ?? false)]);
        $this->audit->log('source.updated', $source, $this->audit->diff($source));

        return back()->with('success', 'Source enregistrée.');
    }

    private function stageData(Request $request, Pipeline $pipeline, ?PipelineStage $stage = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('pm_pipeline_stages', 'name')->where('pipeline_id', $pipeline->id)->ignore($stage?->id)],
            'type' => ['required', Rule::in(array_keys(config('prospection.stage_types')))],
            'probability' => ['required', 'integer', 'min:0', 'max:100'],
        ], [], ['name' => 'nom de l\'étape', 'probability' => 'probabilité']);

        // Le type d'une étape utilisée ne change pas : cela fausserait les clôtures existantes.
        if ($stage && $stage->type !== $data['type'] && $stage->opportunities()->exists()) {
            abort(back()->withErrors(['type' => 'Le type d\'une étape contenant des opportunités ne peut pas être modifié.'])->withInput());
        }

        return $data;
    }

    private function guard(Request $request): void
    {
        abort_unless($request->user()->can('pipelines.manage'), 403);
    }
}
