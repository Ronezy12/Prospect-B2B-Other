<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessImport;
use App\Models\Company;
use App\Models\Import;
use App\Services\AuditLogger;
use App\Services\Import\ImportFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import / export en masse, version simple : un fichier modèle, un dépôt de fichier,
 * et l'import démarre seul si les colonnes sont reconnues. Sinon, l'écran de
 * correspondance complet prend le relais. La liste d'opposition reste appliquée
 * par le traitement d'import (aucune adresse opposée n'est réintroduite).
 */
class CompanyTransferController extends Controller
{
    /** Colonnes du fichier modèle : leurs intitulés sont reconnus automatiquement. */
    public const TEMPLATE = ['Entreprise', 'Adresse', 'Code postal', 'Ville', 'Prénom', 'Nom', 'Fonction', 'Téléphone', 'E-mail', 'Site web', 'Notes'];

    public function show(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->can('prospects.import') || $user->can('export', Company::class), 403);

        return view('simple.companies-transfer', [
            'imports' => $user->can('prospects.import')
                ? Import::where('user_id', $user->id)->latest('id')->limit(5)->get()
                : collect(),
            'total' => Company::visibleTo($user)->where('lifecycle', 'prospect')->count(),
        ]);
    }

    public function template(): StreamedResponse
    {
        Gate::authorize('create', Import::class);

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM : accents corrects dans Excel
            fputcsv($out, self::TEMPLATE, ';');
            fputcsv($out, ['Agence du Parc', '12 rue de la République', '69002', 'Lyon', 'Julie', 'Martin', 'Gérante', '06 12 34 56 78', 'julie.martin@exemple.fr', 'agenceduparc.fr', 'Exemple : supprimez cette ligne'], ';');
            fclose($out);
        }, 'modele-import-prospects.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request, ImportController $imports, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('create', Import::class);
        $request->validate([
            'duplicates' => ['required', Rule::in(['skip', 'fill_empty'])],
            'confirm' => ['accepted'],
        ], ['confirm.accepted' => 'Cochez la case pour confirmer d\'où viennent ces contacts.']);

        $import = $imports->upload($request);
        if ($import instanceof RedirectResponse) {
            return $import;
        }

        if (! isset($import->mapping['company_name'])) {
            return redirect()->route('pm.imports.mapping', $import)
                ->with('warning', 'La colonne du nom de l\'entreprise n\'a pas été reconnue : indiquez ci-dessous quelle colonne correspond à quoi.');
        }

        $options = [
            'duplicate_strategy' => $request->input('duplicates'),
            'default_audience_type' => 'b2b',
            'default_legal_basis' => 'interet_legitime',
            'default_collected_at' => now()->toDateString(),
            'collection_note' => 'Import du fichier « '.mb_substr($import->original_name, 0, 150).' »',
        ];
        $import->update(['options' => $options, 'status' => 'queued']);
        $audit->log('import.started', $import, ['mapping' => $import->mapping, 'options' => $options, 'mode' => 'simple']);
        ProcessImport::dispatch($import->id);

        $recognized = array_map(fn ($field) => ImportFields::FIELDS[$field]['label'], array_keys($import->mapping));

        return redirect()->route('pm.companies.transfer')->with('success', sprintf(
            'Import lancé : %d ligne(s) en cours d\'ajout. Colonnes reconnues : %s.',
            $import->total_rows,
            implode(', ', $recognized),
        ));
    }
}
