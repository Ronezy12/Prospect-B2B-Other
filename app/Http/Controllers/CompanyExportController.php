<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyExportController extends Controller
{
    public function __invoke(Request $request, AuditLogger $audit): StreamedResponse
    {
        Gate::authorize('export', Company::class);

        $filters = array_filter($request->only(CompanyController::FILTERS), fn ($v) => is_string($v) && trim($v) !== '');
        $filters['lifecycle'] ??= 'prospect';
        if ($filters['lifecycle'] === 'all') {
            unset($filters['lifecycle']);
        }

        $query = Company::visibleTo($request->user())->filter($filters)
            ->with(['sector:id,name', 'subsector:id,name', 'source:id,name', 'owner:id,name', 'tags:id,name', 'contacts'])
            ->orderBy('id');

        $max = (int) config('prospection.export.max_rows');
        $count = min((clone $query)->count(), $max);
        $audit->log('company.exported', null, ['filtres' => $filters, 'lignes' => $count]);

        $statuses = config('prospection.company_statuses');
        $filename = 'prospects-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query, $statuses, $max) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM pour Excel
            fputcsv($out, ['ID', 'Entreprise', 'SIREN/SIRET', 'Secteur', 'Sous-secteur', 'Site web', 'E-mail', 'Téléphone',
                'Adresse', 'Code postal', 'Ville', 'Pays', 'Source', 'Date d\'acquisition', 'Statut', 'Cycle', 'Responsable', 'Tags',
                'Contact principal', 'Fonction', 'E-mail contact', 'Téléphone contact', 'Créé le'], ';');

            $written = 0;
            $query->chunk(500, function ($companies) use ($out, $statuses, $max, &$written) {
                foreach ($companies as $c) {
                    if ($written++ >= $max) {
                        return false;
                    }
                    $contact = $c->contacts->firstWhere('is_primary', true) ?? $c->contacts->first();
                    fputcsv($out, array_map([$this, 'safe'], [
                        $c->id, $c->name, $c->registration_number, $c->sector?->name, $c->subsector?->name, $c->website,
                        $c->email, $c->phone, $c->address, $c->postal_code, $c->city, $c->country, $c->source?->name,
                        $c->acquired_at?->format('d/m/Y'), $statuses[$c->status] ?? $c->status, $c->lifecycle,
                        $c->owner?->name, $c->tags->pluck('name')->implode(', '),
                        $contact?->fullName(), $contact?->job_title, $contact?->email, $contact?->phone,
                        $c->created_at?->format('d/m/Y'),
                    ]), ';');
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Neutralise l'injection de formules dans les tableurs. */
    public function safe(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
