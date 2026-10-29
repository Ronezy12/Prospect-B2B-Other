<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\NewsletterList;
use App\Models\Subscription;
use App\Services\AuditLogger;
use App\Services\SuppressionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Listes de newsletter : un abonnement n'est enregistré qu'avec la source et la
 * date du consentement. Un désabonné n'est jamais réabonné sans nouveau consentement.
 */
class NewsletterController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $this->guard($request, 'campaigns.view');

        return view('newsletters.index', [
            'lists' => NewsletterList::withCount([
                'subscriptions as subscribed_count' => fn ($q) => $q->where('status', 'abonne'),
                'subscriptions as unsubscribed_count' => fn ($q) => $q->where('status', 'desabonne'),
            ])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:pm_newsletter_lists,name'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [], ['name' => 'nom de la liste']);
        $list = NewsletterList::create($data);
        $this->audit->log('newsletter_list.created', $list, $data);

        return redirect()->route('pm.newsletters.show', $list)->with('success', 'Liste créée.');
    }

    public function show(Request $request, NewsletterList $list): View
    {
        $this->guard($request, 'campaigns.view');

        return view('newsletters.show', [
            'list' => $list,
            'subscriptions' => $list->subscriptions()->with(['contact.company:id,name'])->orderByRaw("status = 'abonne' desc")->latest('consented_at')->paginate(50),
        ]);
    }

    public function subscribe(Request $request, NewsletterList $list, SuppressionService $suppressions): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'email' => ['required', 'email'],
            'consent_source' => ['required', 'string', 'max:255'],
            'consented_at' => ['required', 'date', 'before_or_equal:now'],
        ], [], ['consent_source' => 'source du consentement', 'consented_at' => 'date du consentement']);

        $contact = Contact::visibleTo($request->user())->where('email_normalized', mb_strtolower(trim($data['email'])))->first();
        if (! $contact) {
            return back()->withInput()->withErrors(['email' => 'Aucun contact de votre périmètre n\'a cette adresse : créez d\'abord la fiche.']);
        }
        if ($suppressions->isSuppressed($contact->email, 'newsletter')) {
            return back()->withInput()->withErrors(['email' => 'Cette adresse s\'est opposée aux newsletters : elle ne peut pas être réabonnée depuis le CRM.']);
        }

        $subscription = Subscription::updateOrCreate(
            ['list_id' => $list->id, 'contact_id' => $contact->id],
            ['status' => 'abonne', 'consent_source' => $data['consent_source'], 'consented_at' => $data['consented_at'], 'unsubscribed_at' => null, 'created_by' => $request->user()->id],
        );
        $this->audit->log('subscription.created', $subscription, ['liste' => $list->name, 'source' => $data['consent_source']]);

        return back()->with('success', "{$contact->fullName()} est abonné(e) à « {$list->name} ».");
    }

    public function unsubscribe(Request $request, Subscription $subscription, SuppressionService $suppressions): RedirectResponse
    {
        $this->guard($request);
        $subscription->update(['status' => 'desabonne', 'unsubscribed_at' => now()]);
        $suppressions->suppress($subscription->contact->email, 'newsletter', 'desabonnement', 'saisie_manuelle', 'Liste '.$subscription->list->name);
        $this->audit->log('subscription.cancelled', $subscription);

        return back()->with('success', 'Désabonnement enregistré.');
    }

    private function guard(Request $request, string $permission = 'campaigns.manage'): void
    {
        abort_unless($request->user()->can($permission), 403);
    }
}
