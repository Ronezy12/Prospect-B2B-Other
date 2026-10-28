<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Tag;
use App\Services\ActivityRecorder;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Modification en masse, limitée aux fiches du périmètre de l'utilisateur. */
class CompanyBulkController extends Controller
{
    private const MAX_IDS = 1000;

    public function __invoke(Request $request, AuditLogger $audit, ActivityRecorder $activities): RedirectResponse
    {
        abort_unless($request->user()->can('prospects.bulk_update'), 403);

        $data = $request->validate([
            'ids' => ['required', 'array', 'max:'.self::MAX_IDS],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['assign', 'status', 'sector', 'add_tag', 'delete'])],
            'owner_id' => ['required_if:action,assign', 'nullable', Rule::exists('users', 'id')->where('is_active', true)],
            'status' => ['required_if:action,status', 'nullable', Rule::in(array_keys(config('prospection.company_statuses')))],
            'sector_id' => ['required_if:action,sector', 'nullable', Rule::exists('pm_sectors', 'id')->whereNull('parent_id')],
            'tag' => ['required_if:action,add_tag', 'nullable', 'string', 'max:80'],
        ], ['ids.required' => 'Sélectionnez au moins une fiche.']);

        if ($data['action'] === 'assign') {
            abort_unless($request->user()->can('prospects.assign'), 403);
        }
        if ($data['action'] === 'delete') {
            abort_unless($request->user()->can('prospects.delete'), 403);
        }

        // Les identifiants hors périmètre sont silencieusement écartés par la requête.
        $companies = Company::visibleTo($request->user())->prospects()->whereIn('id', $data['ids'])->get();

        DB::transaction(function () use ($companies, $data, $activities) {
            foreach ($companies as $company) {
                match ($data['action']) {
                    'assign' => $company->forceFill(['owner_id' => $data['owner_id']])->save(),
                    'status' => $company->forceFill(['status' => $data['status']])->save(),
                    'sector' => $company->forceFill(['sector_id' => $data['sector_id'], 'subsector_id' => null])->save(),
                    'add_tag' => $company->tags()->syncWithoutDetaching(Tag::idsFromString($data['tag'])),
                    'delete' => $company->delete(),
                };
                if ($data['action'] !== 'delete') {
                    $activities->record($company, 'systeme', 'Modification en masse : '.$this->label($data).'.');
                }
            }
        });

        $audit->log('company.bulk_'.$data['action'], null, [
            'ids' => $companies->pluck('id')->all(),
            'valeur' => $data['owner_id'] ?? $data['status'] ?? $data['sector_id'] ?? $data['tag'] ?? null,
        ]);

        $ignored = count(array_unique($data['ids'])) - $companies->count();
        $message = "{$companies->count()} fiche(s) modifiée(s).".($ignored > 0 ? " {$ignored} ignorée(s) (hors périmètre ou clients)." : '');

        return back()->with('success', $message);
    }

    private function label(array $data): string
    {
        return match ($data['action']) {
            'assign' => 'nouveau responsable',
            'status' => 'statut « '.(config('prospection.company_statuses')[$data['status']] ?? $data['status']).' »',
            'sector' => 'changement de secteur',
            'add_tag' => 'ajout du tag « '.$data['tag'].' »',
            default => $data['action'],
        };
    }
}
