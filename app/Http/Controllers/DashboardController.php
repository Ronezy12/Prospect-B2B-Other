<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Import;
use App\Models\Opportunity;
use App\Models\SocialProfile;
use App\Models\Task;
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
                ->latest('occurred_at')->latest('id')->limit(6)->get(),
            // Prospects jamais travaillés : rien de prévu, statut « nouveau ».
            'toCall' => $user->can('prospects.update') ? Company::visibleTo($user)->prospects()->where('status', 'nouveau')
                ->where(fn ($q) => $q->where('owner_id', $user->id)->orWhereNull('owner_id'))
                ->whereDoesntHave('tasks', fn ($t) => $t->where('status', 'a_faire'))
                ->with(['contacts' => fn ($c) => $c->orderByDesc('is_primary')])
                ->latest('id')->limit(5)->get() : collect(),
            'myTasks' => Task::where('assignee_id', $user->id)->where('status', 'a_faire')
                ->where('due_at', '<=', today()->endOfDay())
                ->with(['company', 'contact', 'opportunity:id,title', 'assignee:id,name'])
                ->orderBy('due_at')->limit(8)->get(),
            'openOpportunities' => Opportunity::visibleTo($user)->active()->whereNull('closed_at')->count(),
            'campaignsThisMonth' => $user->can('campaigns.view') ? Campaign::where('type', '!=', 'individuel')->where('started_at', '>=', now()->startOfMonth())->count() : 0,
            'socialToDo' => $user->can('social.use') ? SocialProfile::visibleTo($user)->whereNotIn('status', config('prospection.social.closed_statuses'))
                ->where(fn ($q) => $q->where('next_action_at', '<=', now()->endOfDay())->orWhere(fn ($w) => $w->whereNull('next_action_at')->where('status', 'a_contacter')))->count() : 0,
            'recentImports' => $user->can('prospects.import')
                ? Import::where('user_id', $user->id)->latest('id')->limit(5)->get()
                : collect(),
        ]);
    }
}
