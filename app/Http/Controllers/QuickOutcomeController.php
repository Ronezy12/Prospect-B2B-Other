<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\Simple\QuickOutcomeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Bouton « résultat du contact » : une seule action, l'outil met tout à jour. */
class QuickOutcomeController extends Controller
{
    public function __invoke(Request $request, Company $company, QuickOutcomeService $outcomes): RedirectResponse
    {
        Gate::authorize('update', $company);
        $data = $request->validate([
            'outcome' => ['required', Rule::in(array_keys(config('prospection.quick_outcomes')))],
            'note' => ['nullable', 'string', 'max:1000'],
            'date' => ['nullable', 'date', 'after_or_equal:today'],
        ], [], ['note' => 'note', 'date' => 'date']);

        // La date ne sert qu'aux résultats qui en demandent une (rendez-vous).
        $date = ! empty(config('prospection.quick_outcomes.'.$data['outcome'].'.asks_date')) && isset($data['date']) ? Carbon::parse($data['date']) : null;
        $message = $outcomes->apply($company, $data['outcome'], $request->user(), $data['note'] ?? null, $date);

        return back()->with('success', $message);
    }
}
