<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Import;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->can('prospects.view'), 403);

        $prospects = Company::visibleTo($user)->prospects();

        $byStatus = (clone $prospects)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $bySector = (clone $prospects)
            ->leftJoin('pm_sectors', 'pm_sectors.id', '=', 'pm_companies.sector_id')
            ->selectRaw('coalesce(pm_sectors.name, ?) as sector, count(*) as total', ['Non renseigné'])
            ->groupBy('pm_sectors.name')
            ->orderByDesc('total')
            ->pluck('total', 'sector');

        $visibleIds = Company::visibleTo($user)->select('id');

        return view('dashboard.index', [
            'totals' => [
                'prospects' => (clone $prospects)->count(),
                'new_month' => (clone $prospects)->where('created_at', '>=', now()->startOfMonth())->count(),
                'unassigned' => (clone $prospects)->whereNull('owner_id')->count(),
                'clients' => Company::visibleTo($user)->where('lifecycle', 'client')->count(),
                'contacts' => Contact::visibleTo($user)->count(),
            ],
            'byStatus' => $byStatus,
            'bySector' => $bySector,
            'statuses' => config('prospection.company_statuses'),
            'recentActivities' => Activity::with(['user:id,name', 'subject'])
                ->where('subject_type', (new Company)->getMorphClass())
                ->whereIn('subject_id', $visibleIds)
                ->latest('occurred_at')->latest('id')->limit(10)->get(),
            'recentImports' => $user->can('prospects.import')
                ? Import::where('user_id', $user->id)->latest('id')->limit(5)->get()
                : collect(),
        ]);
    }
}
