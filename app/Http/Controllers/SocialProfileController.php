<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\SocialProfile;
use App\Models\SocialTemplate;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Social\SocialProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Suivi manuel / assisté des prospects sur les réseaux sociaux. Les actions sont réalisées
 * par le commercial sur la plateforme elle-même, puis consignées ici : aucune connexion
 * à un compte personnel, aucune collecte ni action automatisée.
 */
class SocialProfileController extends Controller
{
    public const VIEWS = ['a_traiter' => 'À traiter', 'en_cours' => 'En cours', 'fermes' => 'Clos', 'tous' => 'Tous'];

    public function __construct(private readonly SocialProfileService $social, private readonly AuditLogger $audit) {}

    /** File de travail : profils à contacter ou dont la prochaine action est échue. */
    public function index(Request $request): View
    {
        $this->guard($request);
        $view = array_key_exists($request->query('vue'), self::VIEWS) ? $request->query('vue') : 'a_traiter';
        $closed = config('prospection.social.closed_statuses');
        $filters = $request->validate([
            'platform' => ['nullable', Rule::in(array_keys(config('prospection.social.platforms')))],
            'status' => ['nullable', Rule::in(array_keys(config('prospection.social.statuses')))],
            'owner_id' => ['nullable', 'integer'],
        ]);

        $base = SocialProfile::query()->visibleTo($request->user());
        $counts = [
            'a_traiter' => (clone $base)->whereNotIn('status', $closed)->where(fn ($q) => $q->where('next_action_at', '<=', now()->endOfDay())->orWhere(fn ($w) => $w->whereNull('next_action_at')->where('status', 'a_contacter')))->count(),
            'en_cours' => (clone $base)->whereNotIn('status', $closed)->count(),
        ];

        $profiles = $base
            ->with(['company:id,name,city,owner_id', 'company.owner:id,name', 'contact:id,first_name,last_name,job_title'])
            ->when($view === 'a_traiter', fn ($q) => $q->whereNotIn('status', $closed)->where(fn ($w) => $w->where('next_action_at', '<=', now()->endOfDay())->orWhere(fn ($n) => $n->whereNull('next_action_at')->where('status', 'a_contacter'))))
            ->when($view === 'en_cours', fn ($q) => $q->whereNotIn('status', $closed))
            ->when($view === 'fermes', fn ($q) => $q->whereIn('status', $closed))
            ->when($filters['platform'] ?? null, fn ($q, $v) => $q->where('platform', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['owner_id'] ?? null, fn ($q, $v) => $q->whereHas('company', fn ($c) => $c->where('owner_id', $v)))
            ->orderByRaw('next_action_at is null')
            ->orderBy('next_action_at')
            ->orderByDesc('id')
            ->paginate(config('prospection.per_page'))
            ->withQueryString();

        return view('social.index', [
            'profiles' => $profiles,
            'view' => $view,
            'views' => self::VIEWS,
            'counts' => $counts,
            'filters' => $filters,
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, Company $company): RedirectResponse
    {
        $this->guard($request);
        abort_unless($company->isVisibleTo($request->user()), 403);
        $data = $request->validate([
            'platform' => ['required', Rule::in(array_keys(config('prospection.social.platforms')))],
            'url' => ['required', 'string', 'max:255'],
            'contact_id' => ['nullable', 'integer', Rule::exists('pm_contacts', 'id')->where('company_id', $company->id)->whereNull('deleted_at')],
        ], [], ['platform' => 'réseau', 'url' => 'adresse du profil', 'contact_id' => 'interlocuteur']);

        $contact = isset($data['contact_id']) ? $company->contacts()->find($data['contact_id']) : null;
        [$profile, $result] = $this->social->attach($company, $data['platform'], $data['url'], $contact, $request->user());

        return match ($result) {
            'invalid' => back()->withInput()->withErrors(['url' => 'Adresse invalide pour ce réseau : collez l\'adresse complète du profil ou de la page (https://…).']),
            'opposed' => back()->withInput()->withErrors(['url' => 'Ce profil a demandé à ne plus être contacté : il ne peut pas être ajouté de nouveau.']),
            'existing' => redirect()->route('pm.social.show', $profile)->with('success', 'Ce profil était déjà suivi pour cette entreprise.'),
            default => redirect()->route('pm.social.show', $profile)->with('success', 'Profil ajouté au suivi.'),
        };
    }

    public function show(Request $request, SocialProfile $profile): View
    {
        $this->authorizeProfile($request, $profile);
        $profile->load(['company.contacts', 'contact', 'interactions.user:id,name']);

        $templates = $profile->isOpposed() ? collect() : SocialTemplate::for($profile->platform)->orderBy('kind')->orderBy('name')->get()
            ->map(fn (SocialTemplate $t) => ['template' => $t, 'text' => $this->social->render($t, $profile, $request->user())]);

        return view('social.show', ['profile' => $profile, 'templates' => $templates]);
    }

    public function interaction(Request $request, SocialProfile $profile): RedirectResponse
    {
        $this->authorizeProfile($request, $profile);
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(config('prospection.social.interaction_types')))],
            'body' => ['nullable', 'string', 'max:5000'],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:now'],
            'status' => ['nullable', Rule::in(array_diff(array_keys(config('prospection.social.statuses')), ['ne_plus_contacter']))],
            'next_action_at' => ['nullable', 'date', 'after_or_equal:today'],
            'create_task' => ['nullable', 'boolean'],
        ], [], ['type' => 'action', 'body' => 'compte rendu', 'occurred_at' => 'date', 'next_action_at' => 'prochaine action']);

        if ($profile->isOpposed() && in_array($data['type'], config('prospection.social.outbound_types'), true)) {
            return back()->withErrors(['type' => 'Ce profil a demandé à ne plus être contacté : aucune nouvelle sollicitation ne peut être enregistrée.']);
        }

        $this->social->logInteraction(
            $profile,
            $data['type'],
            $data['body'] ?? null,
            isset($data['occurred_at']) ? Carbon::parse($data['occurred_at']) : now(),
            $data['status'] ?? null,
            isset($data['next_action_at']) ? Carbon::parse($data['next_action_at'])->setTime(9, 0) : null,
            $request->user(),
            (bool) ($data['create_task'] ?? false),
        );

        return redirect()->route('pm.social.show', $profile)->with('success', 'Action enregistrée dans l\'historique du prospect.');
    }

    /** Un clic : l'action est notée, le statut suit et le prochain rappel est créé tout seul. */
    public function quick(Request $request, SocialProfile $profile): RedirectResponse
    {
        $this->authorizeProfile($request, $profile);
        $key = $request->validate(['action' => ['required', Rule::in(array_keys(config('prospection.social.quick_actions')))]])['action'];
        $action = config('prospection.social.quick_actions.'.$key);
        if ($profile->isOpposed() && in_array($action['type'], config('prospection.social.outbound_types'), true)) {
            return back()->withErrors(['type' => 'Ce profil a demandé à ne plus être contacté.']);
        }
        $next = $action['next'] !== null ? now()->addDays($action['next'])->setTime(9, 0) : null;
        while ($next && $next->isWeekend()) {
            $next->addDay();
        }
        $this->social->logInteraction($profile, $action['type'], $key === 'non' ? 'Pas intéressé.' : null, now(), $action['status'], $next, $request->user(), $next !== null);

        return back()->with('success', 'C\'est noté'.($next ? ' : rappel « '.$action['task'].' » le '.$next->locale('fr')->isoFormat('dddd D MMMM').'.' : '.'));
    }

    public function update(Request $request, SocialProfile $profile): RedirectResponse
    {
        $this->authorizeProfile($request, $profile);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_diff(array_keys(config('prospection.social.statuses')), ['ne_plus_contacter']))],
            'next_action_at' => ['nullable', 'date'],
            'contact_id' => ['nullable', 'integer', Rule::exists('pm_contacts', 'id')->where('company_id', $profile->company_id)->whereNull('deleted_at')],
        ], [], ['next_action_at' => 'prochaine action', 'contact_id' => 'interlocuteur']);

        if ($profile->isOpposed()) {
            return back()->withErrors(['status' => 'Opposition enregistrée : le statut de ce profil ne peut plus être modifié.']);
        }
        $before = $profile->status;
        $profile->forceFill([
            'status' => $data['status'],
            'next_action_at' => isset($data['next_action_at']) ? Carbon::parse($data['next_action_at'])->setTime(9, 0) : null,
            'contact_id' => $data['contact_id'] ?? null,
        ])->save();
        if ($before !== $profile->status) {
            $this->audit->log('social_profile.status', $profile, ['avant' => $before, 'après' => $profile->status]);
        }

        return back()->with('success', 'Suivi mis à jour.');
    }

    public function oppose(Request $request, SocialProfile $profile): RedirectResponse
    {
        $this->authorizeProfile($request, $profile);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:255'], 'confirm' => ['accepted']], ['confirm.accepted' => 'Confirmez l\'opposition.']);
        $this->social->oppose($profile, $data['note'] ?? null, $request->user());

        return back()->with('success', 'Opposition enregistrée : ce profil ne sera plus proposé et ne pourra pas être réimporté.');
    }

    public function destroy(Request $request, SocialProfile $profile): RedirectResponse
    {
        $this->authorizeProfile($request, $profile);
        $company = $profile->company;
        $this->audit->log('social_profile.deleted', $profile, ['plateforme' => $profile->platform, 'opposition_conservee' => $profile->isOpposed()]);
        $profile->delete();

        return redirect()->route('pm.companies.show', $company)->with('success', $profile->isOpposed()
            ? 'Profil retiré. L\'opposition reste enregistrée : il ne pourra pas être ajouté de nouveau.'
            : 'Profil retiré du suivi.');
    }

    private function authorizeProfile(Request $request, SocialProfile $profile): void
    {
        $this->guard($request);
        abort_unless($profile->isVisibleTo($request->user()), 403);
    }

    private function guard(Request $request): void
    {
        abort_unless($request->user()->can('social.use'), 403);
    }
}
