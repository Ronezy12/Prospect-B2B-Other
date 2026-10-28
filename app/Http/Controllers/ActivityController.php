<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\ActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ActivityController extends Controller
{
    public function store(Request $request, Company $company, ActivityRecorder $activities): RedirectResponse
    {
        Gate::authorize('update', $company);

        $types = array_diff(array_keys(config('prospection.activity_types')), ['systeme']);
        $data = $request->validate([
            'type' => ['required', Rule::in($types)],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $activities->record($company, $data['type'], $data['body']);

        return redirect()->to(route('pm.companies.show', $company).'#historique')->with('success', 'Activité ajoutée à l\'historique.');
    }
}
