<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Sector;
use App\Models\Source;
use App\Services\ActivityRecorder;
use App\Services\AuditLogger;
use App\Services\DuplicateFinder;
use App\Services\Social\SocialProfileService;
use App\Services\SuppressionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ActivityRecorder $activities,
        private readonly SuppressionService $suppressions,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Contact::class);

        $filters = array_map(
            fn ($v) => mb_substr(trim($v), 0, 100),
            array_filter($request->only(['q', 'audience_type', 'legal_basis', 'sector_id', 'owner_id']), fn ($v) => is_string($v) && trim($v) !== ''),
        );

        $contacts = Contact::query()
            ->visibleTo($request->user())
            ->filter($filters)
            ->with(['company:id,name,city,lifecycle', 'owner:id,name'])
            ->orderBy('last_name')->orderBy('id')
            ->paginate(config('prospection.per_page'))
            ->withQueryString();

        return view('contacts.index', [
            'contacts' => $contacts,
            'filters' => $filters,
            'suppressed' => $this->suppressions->suppressedAmong($contacts->pluck('email')->filter()->all()),
            'sectors' => Sector::roots()->get(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Contact::class);
        $company = Company::visibleTo($request->user())->findOrFail($request->integer('company_id'));

        return view('contacts.form', [
            'contact' => new Contact(['company_id' => $company->id, 'audience_type' => 'b2b', 'legal_basis' => 'interet_legitime', 'collected_at' => now()]),
            'company' => $company,
            'sources' => Source::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(ContactRequest $request, DuplicateFinder $duplicates): RedirectResponse
    {
        Gate::authorize('create', Contact::class);
        $data = $request->validated();
        $company = Company::visibleTo($request->user())->findOrFail($data['company_id']);
        Gate::authorize('update', $company);

        if ($error = $this->emailProblem($data['email'] ?? null, null, $duplicates)) {
            return back()->withInput()->withErrors(['email' => $error]);
        }
        if ($this->socialOpposed($data['linkedin_url'] ?? null)) {
            return back()->withInput()->withErrors(['linkedin_url' => self::SOCIAL_OPPOSED]);
        }

        $contact = DB::transaction(function () use ($request, $data, $company) {
            $contact = new Contact($data);
            $this->applyTrackingConsent($contact, $data);
            $contact->owner_id = $company->owner_id;
            $contact->created_by = $request->user()->id;
            $contact->save();
            $this->syncPrimary($contact);
            $this->activities->record($company, 'systeme', "Contact ajouté : {$contact->fullName()}.");
            $this->audit->log('contact.created', $contact);

            return $contact;
        });
        app(SocialProfileService::class)->syncFromContact($contact, $request->user());

        return redirect()->route('pm.companies.show', $contact->company_id)->with('success', 'Contact ajouté.');
    }

    public function edit(Contact $contact): View
    {
        Gate::authorize('update', $contact);

        return view('contacts.form', [
            'contact' => $contact,
            'company' => $contact->company,
            'sources' => Source::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(ContactRequest $request, Contact $contact, DuplicateFinder $duplicates): RedirectResponse
    {
        Gate::authorize('update', $contact);
        $data = $request->validated();
        // Le rattachement à une autre entreprise exige aussi le droit sur celle-ci.
        $company = Company::visibleTo($request->user())->findOrFail($data['company_id']);

        if ($error = $this->emailProblem($data['email'] ?? null, $contact, $duplicates)) {
            return back()->withInput()->withErrors(['email' => $error]);
        }
        if ($this->socialOpposed($data['linkedin_url'] ?? null)) {
            return back()->withInput()->withErrors(['linkedin_url' => self::SOCIAL_OPPOSED]);
        }

        DB::transaction(function () use ($contact, $data) {
            $contact->fill($data);
            $this->applyTrackingConsent($contact, $data);
            $contact->save();
            $this->syncPrimary($contact);
            if ($changes = $this->audit->diff($contact)) {
                $this->audit->log('contact.updated', $contact, $changes);
            }
        });
        app(SocialProfileService::class)->syncFromContact($contact->refresh(), $request->user());

        return redirect()->route('pm.companies.show', $company)->with('success', 'Contact enregistré.');
    }

    private const SOCIAL_OPPOSED = 'Ce profil LinkedIn a demandé à ne plus être contacté : il ne peut pas être enregistré de nouveau.';

    private function socialOpposed(?string $url): bool
    {
        $social = app(SocialProfileService::class);
        $canonical = $url ? $social->normalizeUrl('linkedin', $url) : null;

        return $canonical !== null && $social->isOpposed($canonical);
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        Gate::authorize('delete', $contact);
        $contact->delete();
        if ($contact->company) {
            $this->activities->record($contact->company, 'systeme', "Contact supprimé : {$contact->fullName()}.");
        }
        $this->audit->log('contact.deleted', $contact);

        return back()->with('success', 'Contact supprimé.');
    }

    private function emailProblem(?string $email, ?Contact $current, DuplicateFinder $duplicates): ?string
    {
        if (! $email || ($current && mb_strtolower($email) === $current->email_normalized)) {
            return null;
        }
        if ($this->suppressions->isSuppressed($email)) {
            return 'Cette adresse figure dans la liste d\'opposition : elle ne peut pas être réintroduite.';
        }
        if ($duplicates->findContactByEmail($email, $current?->id)) {
            return 'Un contact utilise déjà cette adresse e-mail.';
        }

        return null;
    }

    /** Consentement à la mesure d'audience : date et source conservées comme preuve. */
    private function applyTrackingConsent(Contact $contact, array $data): void
    {
        $wanted = (bool) ($data['tracking_consent'] ?? false);
        if ($wanted && ! $contact->tracking_consent_at) {
            $contact->tracking_consent_at = now();
            $contact->tracking_consent_source = $data['tracking_consent_source'] ?? null;
        } elseif (! $wanted) {
            $contact->tracking_consent_at = null;
            $contact->tracking_consent_source = null;
        }
    }

    private function syncPrimary(Contact $contact): void
    {
        if ($contact->is_primary) {
            Contact::where('company_id', $contact->company_id)->whereKeyNot($contact->id)->update(['is_primary' => false]);
        }
    }
}
