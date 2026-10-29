<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Mémorise les visites guidées vues, pour ne pas les reproposer automatiquement. */
class TourController extends Controller
{
    public const OFF = '*desactive*';

    public function done(Request $request): JsonResponse
    {
        $key = $request->validate(['key' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9._*-]+$/']])['key'];
        DB::table('pm_tour_progress')->insertOrIgnore(['user_id' => $request->user()->id, 'tour_key' => $key, 'completed_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /** Réactive toutes les visites (bouton du guide) ou les désactive. */
    public function preference(Request $request): RedirectResponse
    {
        $enabled = $request->validate(['enabled' => ['required', 'boolean']])['enabled'];
        DB::table('pm_tour_progress')->where('user_id', $request->user()->id)->delete();
        if (! $enabled) {
            DB::table('pm_tour_progress')->insert(['user_id' => $request->user()->id, 'tour_key' => self::OFF, 'completed_at' => now()]);
        }

        return back()->with('success', $enabled ? 'Les visites guidées reprendront sur chaque écran.' : 'Les visites guidées ne se lanceront plus d\'elles-mêmes (bouton « Visite guidée » toujours disponible).');
    }
}
