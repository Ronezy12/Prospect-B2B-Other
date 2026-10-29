<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrackingSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        return view('settings.tracking', [
            'opens' => (bool) Setting::get('tracking.opens', false),
            'clicks' => (bool) Setting::get('tracking.clicks', false),
            'consenting' => Contact::whereNotNull('tracking_consent_at')->count(),
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);
        $data = $request->validate(['opens' => ['nullable', 'boolean'], 'clicks' => ['nullable', 'boolean']]);
        Setting::put('tracking.opens', (bool) ($data['opens'] ?? false));
        Setting::put('tracking.clicks', (bool) ($data['clicks'] ?? false));
        $audit->log('settings.tracking', null, ['ouvertures' => (bool) ($data['opens'] ?? false), 'clics' => (bool) ($data['clicks'] ?? false)]);

        return back()->with('success', 'Réglages du suivi enregistrés.');
    }
}
