<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessImport;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\Sector;
use App\Models\Source;
use App\Models\Tag;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Import\ImportFields;
use App\Services\Import\ImportProcessor;
use App\Services\Import\ImportReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ImportController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Import::class);

        $imports = Import::with('user:id,name')
            ->unless($request->user()->can('prospects.view_all'), fn ($q) => $q->where('user_id', $request->user()->id))
            ->latest('id')
            ->paginate(config('prospection.per_page'));

        return view('imports.index', ['imports' => $imports]);
    }

    public function create(): View
    {
        Gate::authorize('create', Import::class);

        return view('imports.create', ['fields' => ImportFields::FIELDS]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Import::class);

        $request->validate([
            'file' => ['required', 'file', 'max:'.config('prospection.import.max_file_kb'), 'mimes:csv,txt,xlsx,xls'],
        ], ['file.mimes' => 'Formats acceptés : CSV, XLSX, XLS.']);

        $file = $request->file('file');
        $extension = Str::lower($file->getClientOriginalExtension());
        $type = match ($extension) {
            'csv', 'txt' => 'csv',
            'xlsx' => 'xlsx',
            'xls' => 'xls',
            default => null,
        };
        if (! $type) {
            return back()->withErrors(['file' => 'Extension de fichier non prise en charge.']);
        }

        // Nom aléatoire, stockage hors de la racine web.
        $path = $file->storeAs(config('prospection.import.directory'), Str::uuid().'.'.$type, config('prospection.import.disk'));
        $absolute = Storage::disk(config('prospection.import.disk'))->path($path);

        try {
            $reader = new ImportReader($absolute, $type);
            $headers = $reader->headers();
            $total = $reader->count();
        } catch (Throwable $e) {
            report($e);
            Storage::disk(config('prospection.import.disk'))->delete($path);

            return back()->withErrors(['file' => 'Le fichier n\'a pas pu être lu. Vérifiez son format.']);
        }

        if (! array_filter($headers)) {
            Storage::disk(config('prospection.import.disk'))->delete($path);

            return back()->withErrors(['file' => 'La première ligne doit contenir les en-têtes de colonnes.']);
        }
        if ($total === 0) {
            Storage::disk(config('prospection.import.disk'))->delete($path);

            return back()->withErrors(['file' => 'Le fichier ne contient aucune ligne de données.']);
        }

        $import = Import::create([
            'user_id' => $request->user()->id,
            'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
            'stored_path' => $path,
            'file_type' => $type,
            'file_hash' => hash_file('sha256', $absolute),
            'headers' => $headers,
            'mapping' => ImportFields::guess($headers),
            'status' => 'uploaded',
            'total_rows' => $total,
        ]);
        $this->audit->log('import.uploaded', $import, ['fichier' => $import->original_name, 'lignes' => $total]);

        return redirect()->route('pm.imports.mapping', $import);
    }

    public function mapping(Request $request, Import $import): View|RedirectResponse
    {
        Gate::authorize('update', $import);
        if (! in_array($import->status, ['uploaded', 'mapped'], true)) {
            return redirect()->route('pm.imports.show', $import);
        }

        $reader = new ImportReader(Storage::disk(config('prospection.import.disk'))->path($import->stored_path), $import->file_type);
        $sameFile = Import::where('file_hash', $import->file_hash)->whereKeyNot($import->id)->where('status', 'completed')->latest('id')->first();

        return view('imports.mapping', [
            'import' => $import,
            'preview' => $reader->preview(config('prospection.import.preview_rows')),
            'fields' => ImportFields::FIELDS,
            'strategies' => ImportProcessor::STRATEGIES,
            'sectors' => Sector::roots()->get(),
            'sources' => Source::where('is_active', true)->orderBy('name')->get(),
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'tags' => Tag::orderBy('name')->get(['id', 'name']),
            'sameFile' => $sameFile,
            'canAssign' => $request->user()->can('prospects.assign'),
        ]);
    }

    public function start(Request $request, Import $import): RedirectResponse
    {
        Gate::authorize('update', $import);
        abort_unless(in_array($import->status, ['uploaded', 'mapped'], true), 409, 'Cet import a déjà été lancé.');

        $columns = count((array) $import->headers);
        $data = $request->validate([
            'mapping' => ['required', 'array'],
            'mapping.*' => ['nullable', 'integer', 'min:0', 'max:'.max(0, $columns - 1)],
            'duplicate_strategy' => ['required', Rule::in(array_keys(ImportProcessor::STRATEGIES))],
            'default_sector_id' => ['nullable', Rule::exists('pm_sectors', 'id')->whereNull('parent_id')],
            'default_source_id' => ['nullable', 'exists:pm_sources,id'],
            'default_owner_id' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)],
            'default_audience_type' => ['required', Rule::in(array_keys(config('prospection.audience_types')))],
            'default_legal_basis' => ['required', Rule::in(array_keys(config('prospection.legal_bases')))],
            'default_collected_at' => ['nullable', 'date', 'before_or_equal:today'],
            'collection_note' => ['nullable', 'string', 'max:255'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:pm_tags,id'],
            'confirm_information' => ['accepted'],
        ], [
            'confirm_information.accepted' => 'Confirmez que la source des données et l\'information des personnes sont documentées.',
        ]);

        $mapping = array_filter(
            array_intersect_key($data['mapping'], ImportFields::FIELDS),
            fn ($v) => $v !== null && $v !== '',
        );
        if (! isset($mapping['company_name'])) {
            return back()->withInput()->withErrors(['mapping' => 'La colonne « raison sociale » doit être associée.']);
        }
        if (count($mapping) !== count(array_unique($mapping))) {
            return back()->withInput()->withErrors(['mapping' => 'Une même colonne ne peut alimenter qu\'un seul champ.']);
        }

        $options = collect($data)->except(['mapping', 'confirm_information'])->all();
        if (! $request->user()->can('prospects.assign')) {
            unset($options['default_owner_id']);
        }

        $import->update(['mapping' => array_map('intval', $mapping), 'options' => $options, 'status' => 'queued']);
        $this->audit->log('import.started', $import, ['mapping' => $mapping, 'options' => $options]);

        ProcessImport::dispatch($import->id);

        return redirect()->route('pm.imports.show', $import)->with('success', 'Import lancé. Le traitement se poursuit en arrière-plan.');
    }

    public function show(Request $request, Import $import): View
    {
        Gate::authorize('view', $import);

        $result = $request->query('result');
        $rows = $import->rows()
            ->when(array_key_exists((string) $result, ImportRow::RESULT_LABELS), fn ($q) => $q->where('result', $result))
            ->with(['company:id,name', 'contact:id,first_name,last_name,email'])
            ->orderBy('row_number')
            ->paginate(50)
            ->withQueryString();

        return view('imports.show', ['import' => $import, 'rows' => $rows, 'result' => $result]);
    }

    public function cancel(Import $import): RedirectResponse
    {
        Gate::authorize('update', $import);
        abort_if($import->isFinished(), 409, 'Cet import est déjà terminé.');

        $import->update(['status' => 'cancelled', 'finished_at' => now()]);
        $this->audit->log('import.cancelled', $import);

        return redirect()->route('pm.imports.show', $import)->with('success', 'Import annulé. Les lignes déjà traitées sont conservées.');
    }

    /** Rapport des lignes en erreur ou ignorées, pour correction et ré-import. */
    public function report(Import $import): StreamedResponse
    {
        Gate::authorize('view', $import);

        return response()->streamDownload(function () use ($import) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Ligne', 'Résultat', 'Messages'], ';');
            $import->rows()->where('result', '!=', 'created')->orderBy('row_number')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->row_number,
                        ImportRow::RESULT_LABELS[$row->result] ?? $row->result,
                        preg_replace('/^[=+\-@]/', "'$0", implode(' | ', (array) $row->messages)),
                    ], ';');
                }
            });
            fclose($out);
        }, 'rapport-import-'.$import->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
