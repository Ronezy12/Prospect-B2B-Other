<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\EmailEvent;
use App\Models\MailAccount;
use App\Models\NewsletterList;
use App\Models\Segment;
use App\Models\Tag;
use App\Models\Template;
use App\Services\AuditLogger;
use App\Services\Mail\CampaignService;
use App\Services\Mail\MailProviderFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class CampaignController extends Controller
{
    private const TYPES = ['prospection', 'newsletter', 'operationnel'];

    public function __construct(private readonly CampaignService $campaigns, private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $this->guard($request, 'campaigns.view');
        $type = in_array($request->query('type'), array_keys(config('prospection.campaign_types')), true) ? $request->query('type') : null;
        $status = array_key_exists((string) $request->query('status'), config('prospection.campaign_statuses')) ? $request->query('status') : null;

        $campaigns = Campaign::with(['template:id,name', 'mailAccount:id,name'])
            ->withCount([
                'recipients as accepted_count' => fn ($q) => $q->where('status', 'accepte'),
                'recipients as pending_count' => fn ($q) => $q->whereIn('status', ['en_attente', 'envoi']),
            ])
            ->when($type, fn ($q) => $q->where('type', $type), fn ($q) => $q->where('type', '!=', 'individuel'))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->paginate(config('prospection.per_page'))
            ->withQueryString();

        return view('campaigns.index', compact('campaigns', 'type', 'status'));
    }

    public function create(Request $request): View
    {
        $this->guard($request);
        $type = in_array($request->query('type'), self::TYPES, true) ? $request->query('type') : 'prospection';

        return view('campaigns.form', ['campaign' => new Campaign(['type' => $type])] + $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->guard($request);
        $campaign = new Campaign($this->validated($request));
        $campaign->created_by = $request->user()->id;
        $campaign->save();
        $this->audit->log('campaign.created', $campaign, ['nom' => $campaign->name, 'type' => $campaign->type]);

        return redirect()->route('pm.campaigns.show', $campaign)->with('success', 'Brouillon créé. Envoyez un test puis vérifiez la campagne avant de la programmer.');
    }

    public function show(Request $request, Campaign $campaign, MailProviderFactory $providers): View
    {
        $this->guard($request, 'campaigns.view');
        $campaign->load(['template.latestVersion', 'templateVersion', 'mailAccount', 'segment', 'list', 'author:id,name']);

        $counts = CampaignRecipient::where('campaign_id', $campaign->id)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $exclusions = CampaignRecipient::where('campaign_id', $campaign->id)->where('status', 'exclu')
            ->selectRaw('exclusion_reason, count(*) as n')->groupBy('exclusion_reason')->pluck('n', 'exclusion_reason');
        $events = EmailEvent::where('campaign_id', $campaign->id)->selectRaw('type, count(*) as n')->groupBy('type')->pluck('n', 'type');

        return view('campaigns.show', [
            'campaign' => $campaign,
            'counts' => $counts,
            'exclusions' => $exclusions,
            'events' => $events,
            'preflight' => $campaign->isEditable() && $request->user()->can('campaigns.send') ? $this->campaigns->preflight($campaign, $request->user()) : null,
            'reportsEvents' => $campaign->mailAccount ? $providers->for($campaign->mailAccount)->reportsEvents() : false,
            'recipients' => $campaign->recipients()
                ->when(array_key_exists((string) $request->query('statut'), config('prospection.recipient_statuses')), fn ($q) => $q->where('status', $request->query('statut')))
                ->orderBy('id')->paginate(50, ['*'], 'page')->withQueryString(),
        ]);
    }

    public function edit(Request $request, Campaign $campaign): View|RedirectResponse
    {
        $this->guard($request);
        if (! $campaign->isEditable()) {
            return redirect()->route('pm.campaigns.show', $campaign)->withErrors(['campaign' => 'Seul un brouillon peut être modifié. Dupliquez la campagne pour la réutiliser.']);
        }

        return view('campaigns.form', ['campaign' => $campaign] + $this->formData());
    }

    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->guard($request);
        abort_unless($campaign->isEditable(), 409, 'Seul un brouillon peut être modifié.');
        $campaign->update($this->validated($request, $campaign->type));
        $this->audit->log('campaign.updated', $campaign, array_keys($this->audit->diff($campaign)));

        return redirect()->route('pm.campaigns.show', $campaign)->with('success', 'Campagne enregistrée.');
    }

    public function test(Request $request, Campaign $campaign, TemplateController $templates): RedirectResponse
    {
        $this->guard($request);
        $emails = $templates->parseEmails((string) $request->validate(['emails' => ['required', 'string', 'max:1000']])['emails']);
        $version = $campaign->templateVersion ?? $campaign->template?->latestVersion;
        if (! $emails || ! $version || ! $campaign->mailAccount) {
            return back()->withErrors(['emails' => $emails ? 'Choisissez un modèle et un compte d\'envoi.' : 'Indiquez jusqu\'à '.config('prospection.mail.test_max_recipients').' adresses valides.']);
        }

        $results = $this->campaigns->sendTest($campaign->mailAccount, $version, $emails, $campaign->isMarketing(), $request->user(), $campaign->from_name);
        $failed = array_filter($results, fn ($r) => ! $r->isAccepted());
        if ($failed) {
            return back()->withErrors(['emails' => 'Échec du test : '.reset($failed)->error]);
        }
        $campaign->forceFill(['last_test_at' => now()])->save();

        return back()->with('success', 'E-mail de test accepté par le serveur d\'envoi pour '.implode(', ', $emails).'.');
    }

    public function schedule(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->guard($request, 'campaigns.send');
        $data = $request->validate([
            'when' => ['required', Rule::in(['now', 'later'])],
            'scheduled_at' => ['required_if:when,later', 'nullable', 'date', 'after:now'],
            'confirm_compliance' => ['accepted'],
        ], ['confirm_compliance.accepted' => 'Confirmez la vérification de la base légale et du contenu.', 'scheduled_at.after' => 'La date de programmation doit être dans le futur.'], ['scheduled_at' => 'date d\'envoi']);

        try {
            $this->campaigns->schedule($campaign, $request->user(), $data['when'] === 'later' ? Carbon::parse($data['scheduled_at']) : null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['campaign' => $e->getMessage()]);
        }

        return redirect()->route('pm.campaigns.show', $campaign)->with('success', $data['when'] === 'later'
            ? 'Campagne programmée le '.$campaign->fresh()->scheduled_at->format('d/m/Y à H:i').'.'
            : 'Envoi lancé. La progression s\'affiche ci-dessous.');
    }

    public function pause(Request $request, Campaign $campaign): RedirectResponse
    {
        return $this->act($request, fn () => $this->campaigns->pause($campaign), 'Campagne mise en pause : aucun nouvel envoi ne part.');
    }

    public function resume(Request $request, Campaign $campaign): RedirectResponse
    {
        return $this->act($request, fn () => $this->campaigns->resume($campaign), 'Campagne reprise.');
    }

    public function cancel(Request $request, Campaign $campaign): RedirectResponse
    {
        return $this->act($request, fn () => $this->campaigns->cancel($campaign), 'Campagne annulée. Les envois restants ne partiront pas.');
    }

    public function duplicate(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->guard($request);
        $copy = $this->campaigns->duplicate($campaign, $request->user());

        return redirect()->route('pm.campaigns.edit', $copy)->with('success', 'Campagne dupliquée en brouillon.');
    }

    public function destroy(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->guard($request);
        abort_unless($campaign->isEditable(), 409, 'Seul un brouillon peut être supprimé.');
        $campaign->delete();
        $this->audit->log('campaign.deleted', $campaign, ['nom' => $campaign->name]);

        return redirect()->route('pm.campaigns.index')->with('success', 'Brouillon supprimé.');
    }

    private function act(Request $request, callable $action, string $message): RedirectResponse
    {
        $this->guard($request, 'campaigns.send');
        try {
            $action();
        } catch (RuntimeException $e) {
            return back()->withErrors(['campaign' => $e->getMessage()]);
        }

        return back()->with('success', $message);
    }

    private function validated(Request $request, ?string $lockedType = null): array
    {
        $type = $lockedType ?? $request->input('type');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => [$lockedType ? 'prohibited' : 'required', Rule::in(self::TYPES)],
            'template_id' => ['required', Rule::exists('pm_templates', 'id')->whereNull('deleted_at')->where('category', $type)],
            'mail_account_id' => ['required', Rule::exists('pm_mail_accounts', 'id')->where('is_active', true)],
            'segment_id' => [in_array($type, ['prospection', 'operationnel'], true) ? 'required' : 'prohibited', 'nullable', Rule::exists('pm_segments', 'id')->whereNull('deleted_at')],
            'list_id' => [$type === 'newsletter' ? 'required' : 'prohibited', 'nullable', Rule::exists('pm_newsletter_lists', 'id')->where('is_active', true)],
            'from_name' => ['nullable', 'string', 'max:120'],
            'reply_to' => ['nullable', 'email', 'max:190'],
            'exclusions.tag_ids' => ['nullable', 'array'],
            'exclusions.tag_ids.*' => ['integer', 'exists:pm_tags,id'],
            'exclusions.contacted_within_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ], ['template_id.exists' => 'Choisissez un modèle de la même catégorie que la campagne.'], [
            'template_id' => 'modèle', 'mail_account_id' => 'compte d\'envoi', 'segment_id' => 'segment', 'list_id' => 'liste d\'abonnés', 'reply_to' => 'adresse de réponse',
        ]);
        $data['exclusions'] = array_filter($data['exclusions'] ?? [], fn ($v) => filled($v));

        return $data;
    }

    private function formData(): array
    {
        return [
            'templates' => Template::where('category', '!=', 'individuel')->orderBy('name')->get(['id', 'name', 'category']),
            'accounts' => MailAccount::where('is_active', true)->orderBy('name')->get(),
            'segments' => Segment::orderBy('name')->get(['id', 'name']),
            'lists' => NewsletterList::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'tags' => Tag::orderBy('name')->get(['id', 'name']),
        ];
    }

    private function guard(Request $request, string $permission = 'campaigns.manage'): void
    {
        abort_unless($request->user()->can($permission), 403);
    }
}
