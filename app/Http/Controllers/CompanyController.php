<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyRequest;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Sector;
use App\Models\Source;
use App\Models\Tag;
use App\Models\User;
use App\Services\ActivityRecorder;
use App\Services\AuditLogger;
use App\Services\Automation\AutomationEngine;
use App\Services\DuplicateFinder;
use App\Services\SuppressionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public const FILTERS = ['q', 'lifecycle', 'sector_id', 'status', 'source_id', 'owner_id', 'city', 'postal_code', 'tag_id'];

    public const SORTS = ['name', 'created_at', 'city', 'status'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ActivityRecorder $activities,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Company::class);

        $filters = $this->filters($request);
        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'created_at';
        $direction = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $companies = Company::query()
            ->visibleTo($request->user())
            ->filter($filters)
            ->with(['sector:id,name', 'owner:id,name', 'tags:id,name'])
            ->withCount('contacts')
            ->orderBy($sort, $direction)
            ->orderBy('id', 'desc')
            ->paginate(config('prospection.per_page'))
            ->withQueryString();

        return view('companies.index', [
            'companies' => $companies,
            'filters' => $filters,
            'sort' => $sort,
            'direction' => $direction,
        ] + $this->referenceData());
    }

    public function create(): View
    {
        Gate::authorize('create', Company::class);

        return view('companies.form', ['company' => new Company(['status' => 'nouveau', 'country' => 'FR'])] + $this->referenceData());
    }

    public function store(CompanyRequest $request, DuplicateFinder $duplicates, SuppressionService $suppressions): RedirectResponse
    {
        Gate::authorize('create', Company::class);
        $data = $request->validated();

        if (! $request->boolean('confirm_duplicate') && ($duplicate = $duplicates->findCompany($data))) {
            return back()->withInput()->with('duplicate', [
                'id' => $duplicate->trashed() ? null : $duplicate->id,
                'name' => $duplicate->name,
                'trashed' => $duplicate->trashed(),
                'client' => $duplicate->isClient(),
            ]);
        }

        $contactData = array_filter($data['contact'] ?? [], fn ($v) => filled($v));
        if (! empty($contactData['email']) && $suppressions->isSuppressed($contactData['email'])) {
            return back()->withInput()->withErrors(['contact.email' => 'Cette adresse figure dans la liste d\'opposition : le contact ne peut pas être enregistré avec cet e-mail.']);
        }

        $company = DB::transaction(function () use ($request, $data, $contactData) {
            $company = new Company(collect($data)->except(['tags', 'owner_id', 'contact', 'confirm_duplicate'])->all());
            $company->owner_id = $this->resolveOwner($request->user(), $data['owner_id'] ?? null);
            $company->created_by = $request->user()->id;
            $company->acquired_at ??= now();
            $company->save();
            $company->tags()->sync(Tag::idsFromString($data['tags'] ?? null));

            if (array_intersect_key($contactData, array_flip(['first_name', 'last_name', 'email']))) {
                $contact = new Contact($contactData + ['audience_type' => 'b2b', 'legal_basis' => 'interet_legitime']);
                $contact->company_id = $company->id;
                $contact->owner_id = $company->owner_id;
                $contact->source_id = $company->source_id;
                $contact->collected_at = now();
                $contact->created_by = $request->user()->id;
                $contact->is_primary = true;
                $contact->save();
            }

            $this->activities->record($company, 'systeme', 'Fiche créée.');
            $this->audit->log('company.created', $company, ['name' => $company->name]);

            return $company;
        });
        app(AutomationEngine::class)->fire('company.created', $company, 'created');

        return redirect()->route('pm.companies.show', $company)->with('success', 'Prospect créé.');
    }

    public function show(Request $request, Company $company): View
    {
        Gate::authorize('view', $company);

        $company->load(['sector', 'subsector', 'source', 'owner', 'tags', 'contacts' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('last_name'), 'socialProfiles.contact']);
        $suppressed = app(SuppressionService::class)->suppressedAmong($company->contacts->pluck('email')->filter()->all());

        $audit = $request->user()->can('audit.view')
            ? AuditLog::with('user:id,name')->where('subject_type', $company->getMorphClass())->where('subject_id', $company->id)->latest('id')->limit(50)->get()
            : collect();

        return view('companies.show', [
            'company' => $company,
            'activities' => $company->activities()->with('user:id,name')->paginate(20, ['*'], 'activites'),
            'suppressed' => $suppressed,
            'audit' => $audit,
            'opportunities' => $company->opportunities()->visibleTo($request->user())->with(['stage:id,name,type', 'pipeline:id,name', 'owner:id,name'])->latest('id')->get(),
            'tasks' => $company->tasks()->visibleTo($request->user())->where('status', 'a_faire')->with(['assignee:id,name', 'opportunity:id,title'])->orderBy('due_at')->limit(20)->get(),
        ]);
    }

    public function edit(Company $company): View
    {
        Gate::authorize('update', $company);
        $company->load('tags');

        return view('companies.form', ['company' => $company] + $this->referenceData());
    }

    public function update(CompanyRequest $request, Company $company): RedirectResponse
    {
        Gate::authorize('update', $company);
        $data = $request->validated();

        DB::transaction(function () use ($request, $company, $data) {
            $company->fill(collect($data)->except(['tags', 'owner_id', 'contact', 'confirm_duplicate'])->all());
            if ($request->user()->can('prospects.assign')) {
                $company->owner_id = $data['owner_id'] ?? null;
            }
            $company->save();
            $changes = $this->audit->diff($company);
            $company->tags()->sync(Tag::idsFromString($data['tags'] ?? null));

            if ($changes) {
                if (isset($changes['status'])) {
                    $statuses = config('prospection.company_statuses');
                    $this->activities->record($company, 'systeme', sprintf(
                        'Statut : %s → %s.',
                        $statuses[$changes['status']['avant']] ?? $changes['status']['avant'],
                        $statuses[$changes['status']['après']] ?? $changes['status']['après'],
                    ));
                }
                if (isset($changes['owner_id'])) {
                    $this->activities->record($company, 'systeme', 'Responsable modifié : '.($company->owner?->name ?? 'aucun').'.');
                }
                $this->audit->log('company.updated', $company, $changes);
            }
            if (isset($changes['status'])) {
                app(AutomationEngine::class)->fire('company.status_changed', $company, 'status:'.$changes['status']['avant'].'>'.$changes['status']['après'].':'.now()->timestamp, $changes['status']);
            }
        });

        return redirect()->route('pm.companies.show', $company)->with('success', 'Fiche enregistrée.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        Gate::authorize('delete', $company);

        $company->delete();
        $this->audit->log('company.deleted', $company, ['name' => $company->name]);

        return redirect()->route('pm.companies.index')->with('success', 'Fiche supprimée (archivée, restaurable par un administrateur).');
    }

    /** Conversion explicite et tracée : jamais automatique. */
    public function convert(Request $request, Company $company): RedirectResponse
    {
        Gate::authorize('convert', $company);
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Confirmez la conversion en client.']);

        $company->forceFill([
            'lifecycle' => 'client',
            'converted_at' => now(),
            'converted_by' => $request->user()->id,
        ])->save();

        $this->activities->record($company, 'systeme', 'Prospect converti en client.');
        $this->audit->log('company.converted', $company);

        return redirect()->route('pm.companies.show', $company)->with('success', 'Prospect converti en client.');
    }

    /** @return array<string, string> */
    private function filters(Request $request): array
    {
        $filters = array_filter($request->only(self::FILTERS), fn ($v) => is_string($v) && trim($v) !== '');
        $filters = array_map(fn ($v) => mb_substr(trim($v), 0, 100), $filters);
        $filters['lifecycle'] ??= 'prospect';
        if ($filters['lifecycle'] === 'all') {
            unset($filters['lifecycle']);
        }

        return $filters;
    }

    private function resolveOwner(User $user, mixed $ownerId): int
    {
        return $user->can('prospects.assign') && $ownerId ? (int) $ownerId : $user->id;
    }

    /** @return array<string, mixed> */
    private function referenceData(): array
    {
        return [
            'sectors' => Sector::roots()->with('children')->where('is_active', true)->get(),
            'sources' => Source::where('is_active', true)->orderBy('name')->get(),
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'tags' => Tag::orderBy('name')->get(['id', 'name']),
            'statuses' => config('prospection.company_statuses'),
        ];
    }
}
