<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Services\ActivityRecorder;
use App\Services\SuppressionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Enregistrement d'une opposition exprimée par un contact (téléphone,
 * courrier, e-mail…). Toute personne pouvant modifier la fiche peut la saisir ;
 * la levée d'une opposition est réservée au rôle conformité (phase 9).
 */
class SuppressionController extends Controller
{
    public function store(Request $request, Contact $contact, SuppressionService $suppressions, ActivityRecorder $activities): RedirectResponse
    {
        abort_unless(
            $contact->isVisibleTo($request->user())
            && ($request->user()->can('prospects.update') || $request->user()->can('compliance.manage')),
            403,
        );
        abort_if(! $contact->email, 422, 'Ce contact n\'a pas d\'adresse e-mail.');

        $data = $request->validate([
            'scope' => ['required', Rule::in(array_keys(config('prospection.suppression_scopes')))],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $suppressions->suppress($contact->email, $data['scope'], 'opposition', 'saisie_manuelle', $data['note'] ?? null);
        if ($contact->company) {
            $activities->record($contact->company, 'systeme', sprintf(
                'Opposition enregistrée pour %s (%s).',
                $contact->fullName(),
                config('prospection.suppression_scopes')[$data['scope']],
            ));
        }

        return back()->with('success', 'Opposition enregistrée : cette adresse est exclue des envois concernés.');
    }
}
