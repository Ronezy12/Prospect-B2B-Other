<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Company;
use App\Models\MailAccount;
use App\Models\Sector;
use App\Models\Segment;
use App\Models\Template;
use App\Services\AuditLogger;
use App\Services\Mail\AudienceBuilder;
use App\Services\Mail\CampaignService;
use App\Services\Mail\TemplateRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * « Écrire à plusieurs entreprises » en 3 écrans : à qui, quel message, envoyer.
 * La liste de destinataires, le modèle et l'envoi groupé sont créés en coulisse,
 * avec exactement les mêmes contrôles qu'un envoi préparé à la main.
 */
class SimpleEmailController extends Controller
{
    private const SESSION = 'simple_email';

    public const EXAMPLES = [
        'presentation' => [
            'label' => 'Se présenter',
            'subject' => '{{prenom}}, une proposition pour {{entreprise}}',
            'body' => "Bonjour {{prenom}},\n\nJe me permets de vous contacter car Entreprise AR accompagne les professionnels de {{ville}} pour leurs travaux d'électricité et de rénovation.\n\nSeriez-vous disponible pour un court échange de 10 minutes cette semaine ?\n\nBien cordialement,",
        ],
        'relance' => [
            'label' => 'Relancer',
            'subject' => 'Suite à mon précédent message',
            'body' => "Bonjour {{prenom}},\n\nJe reviens vers vous au sujet de mon précédent message. Avez-vous des projets de travaux pour {{entreprise}} dans les prochains mois ?\n\nSi ce n'est pas le bon moment, dites-le-moi simplement.\n\nBien cordialement,",
        ],
        'offre' => [
            'label' => 'Annoncer une offre',
            'subject' => 'Une offre pour {{entreprise}}',
            'body' => "Bonjour {{prenom}},\n\nCe mois-ci, nous proposons un diagnostic électrique gratuit aux professionnels de {{ville}}.\n\nRépondez simplement à cet e-mail pour réserver un créneau.\n\nBien cordialement,",
        ],
    ];

    public function __construct(
        private readonly AudienceBuilder $audience,
        private readonly CampaignService $campaigns,
        private readonly TemplateRenderer $renderer,
        private readonly AuditLogger $audit,
    ) {}

    /** Étape 1 : à qui ? Le nombre de destinataires se met à jour à chaque clic sur « Voir ». */
    public function create(Request $request): View
    {
        $this->guard($request);
        $filters = $this->filters($request->has('vu') ? $request->query() : (array) session(self::SESSION.'.filters', []));
        $estimate = $this->estimate($filters, $request);

        return view('simple-email.who', [
            'filters' => $filters,
            'estimate' => $estimate,
            'sample' => $this->sample($filters, $request),
            'sectors' => Sector::roots()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'statuses' => config('prospection.company_statuses'),
        ]);
    }

    public function storeWho(Request $request): RedirectResponse
    {
        $this->guard($request);
        $filters = $this->filters($request->all());
        if ($this->estimate($filters, $request)['eligible'] === 0) {
            return back()->withErrors(['who' => 'Aucune entreprise ne peut recevoir cet e-mail avec ces critères : élargissez la sélection.']);
        }
        session([self::SESSION.'.filters' => $filters]);

        return redirect()->route('pm.simple-email.message');
    }

    /** Étape 2 : quel message ? */
    public function message(Request $request): View|RedirectResponse
    {
        $this->guard($request);
        if (! session()->has(self::SESSION.'.filters')) {
            return redirect()->route('pm.simple-email.create');
        }
        $example = self::EXAMPLES[$request->query('exemple')] ?? null;
        $draft = (array) session(self::SESSION.'.message', []);

        return view('simple-email.message', [
            'subject' => $example['subject'] ?? ($draft['subject'] ?? self::EXAMPLES['presentation']['subject']),
            'body' => $example['body'] ?? ($draft['body'] ?? self::EXAMPLES['presentation']['body']),
            'accountId' => $draft['mail_account_id'] ?? null,
            'accounts' => $this->accounts(),
            'examples' => self::EXAMPLES,
        ]);
    }

    public function storeMessage(Request $request): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:190'],
            'body' => ['required', 'string', 'max:10000'],
            'mail_account_id' => ['required', Rule::in($this->accounts()->pluck('id')->all())],
        ], ['mail_account_id.required' => 'Aucun compte d\'envoi n\'est prêt : demandez à un administrateur de le configurer dans Paramètres.'], ['subject' => 'objet', 'body' => 'message', 'mail_account_id' => 'adresse d\'envoi']);
        session([self::SESSION.'.message' => $data]);

        return redirect()->route('pm.simple-email.confirm');
    }

    /** Étape 3 : vérifier et envoyer. */
    public function confirm(Request $request): View|RedirectResponse
    {
        $this->guard($request);
        $filters = session(self::SESSION.'.filters');
        $message = session(self::SESSION.'.message');
        if (! $filters || ! $message) {
            return redirect()->route('pm.simple-email.create');
        }
        $estimate = $this->estimate($filters, $request);
        $first = $this->audience->query($this->draftCampaign($filters), $request->user())->with('company')->first();
        $data = ($first ? $this->audience->mergeData($first) : TemplateRenderer::sampleData('')) + ['expediteur' => $request->user()->name];

        return view('simple-email.confirm', [
            'estimate' => $estimate,
            'preview' => [
                'to' => $first ? trim($first->fullName().' — '.$first->company?->name) : null,
                'subject' => $this->replace($message['subject'], $data),
                'body' => $this->replace($message['body'], $data)."\n".$request->user()->name,
            ],
            'account' => MailAccount::find($message['mail_account_id']),
            'canSend' => $request->user()->can('campaigns.send'),
            'tested' => (bool) session(self::SESSION.'.tested'),
        ]);
    }

    public function test(Request $request): RedirectResponse
    {
        $this->guard($request);
        $message = session(self::SESSION.'.message');
        abort_unless($message, 404);
        $template = $this->buildTemplate($message, $request, 'Test');
        $results = $this->campaigns->sendTest(MailAccount::findOrFail($message['mail_account_id']), $template->latestVersion, [$request->user()->email], true, $request->user());
        $template->delete();
        $ok = collect($results)->every(fn ($r) => $r->isAccepted());
        if ($ok) {
            session([self::SESSION.'.tested' => true]);
        }

        return back()->with($ok ? 'success' : 'warning', $ok ? 'Test envoyé à '.$request->user()->email.' : vérifiez votre boîte de réception.' : 'Le test n\'a pas pu partir : vérifiez le compte d\'envoi.');
    }

    public function send(Request $request): RedirectResponse
    {
        $this->guard($request);
        $filters = session(self::SESSION.'.filters');
        $message = session(self::SESSION.'.message');
        abort_unless($filters && $message, 404);
        $data = $request->validate([
            'confirm' => ['accepted'],
            'when' => ['required', Rule::in(['now', 'later'])],
            'scheduled_at' => ['required_if:when,later', 'nullable', 'date', 'after:now'],
        ], ['confirm.accepted' => 'Cochez la case pour confirmer que ces entreprises peuvent être contactées.'], ['scheduled_at' => 'date d\'envoi']);

        $user = $request->user();
        $campaign = DB::transaction(function () use ($filters, $message, $request, $user) {
            $label = 'Envoi du '.now()->format('d/m/Y').' — '.Str::limit($message['subject'], 60, '…');
            $segment = Segment::create(['name' => Str::limit($label, 150, ''), 'filters' => $this->segmentFilters($filters)]);
            $segment->forceFill(['created_by' => $user->id])->save();
            $template = $this->buildTemplate($message, $request, $label);

            $campaign = new Campaign([
                'name' => Str::limit($label, 150, ''),
                'type' => 'prospection',
                'template_id' => $template->id,
                'mail_account_id' => $message['mail_account_id'],
                'segment_id' => $segment->id,
                // Par défaut, on n'écrit pas à quelqu'un sollicité il y a moins d'un mois.
                'exclusions' => empty($filters['include_recent']) ? ['contacted_within_days' => 30] : [],
                'from_name' => $user->name,
            ]);
            $campaign->created_by = $user->id;
            $campaign->last_test_at = session(self::SESSION.'.tested') ? now() : null;
            $campaign->save();
            $this->audit->log('campaign.simple_created', $campaign, ['destinataires_filtres' => $filters]);

            return $campaign;
        });

        if (! $user->can('campaigns.send')) {
            session()->forget(self::SESSION);

            return redirect()->route('pm.campaigns.show', $campaign)->with('success', 'E-mail préparé. Un responsable doit maintenant valider l\'envoi.');
        }

        try {
            $this->campaigns->schedule($campaign, $user, $data['when'] === 'later' ? Carbon::parse($data['scheduled_at']) : null);
        } catch (RuntimeException $e) {
            return redirect()->route('pm.campaigns.show', $campaign)->withErrors(['campaign' => $e->getMessage()]);
        }
        session()->forget(self::SESSION);

        return redirect()->route('pm.campaigns.show', $campaign)->with('success', $data['when'] === 'later'
            ? 'C\'est programmé pour le '.$campaign->fresh()->scheduled_at->format('d/m/Y à H:i').'.'
            : 'C\'est parti ! Les e-mails partent progressivement ; suivez l\'avancement ci-dessous.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $this->guard($request);
        session()->forget(self::SESSION);

        return redirect()->route('pm.simple-email.create');
    }

    /** @return array<string, string> */
    private function filters(array $input): array
    {
        $filters = [
            'sector_id' => isset($input['sector_id']) && Sector::whereKey((int) $input['sector_id'])->exists() ? (string) (int) $input['sector_id'] : null,
            'city' => isset($input['city']) ? mb_substr(trim((string) $input['city']), 0, 120) : null,
            'status' => isset($input['status']) && array_key_exists($input['status'], config('prospection.company_statuses')) ? $input['status'] : null,
            'lifecycle' => 'prospect',
            // Par défaut, on n'écrit pas à quelqu'un déjà sollicité il y a moins d'un mois.
            'include_recent' => ! empty($input['include_recent']) ? '1' : null,
        ];

        return array_filter($filters, fn ($v) => filled($v));
    }

    private function draftCampaign(array $filters, ?bool $excludeRecent = null): Campaign
    {
        $excludeRecent ??= empty($filters['include_recent']);
        $campaign = new Campaign(['type' => 'prospection', 'exclusions' => $excludeRecent ? ['contacted_within_days' => 30] : []]);
        $campaign->setRelation('segment', new Segment(['name' => 'brouillon', 'filters' => $this->segmentFilters($filters)]));

        return $campaign;
    }

    /** @return array<string, string> */
    private function segmentFilters(array $filters): array
    {
        return array_diff_key($filters, ['include_recent' => true]);
    }

    /** Estimation + motifs de retrait en clair (dont « déjà contacté ce mois-ci »). */
    private function estimate(array $filters, Request $request): array
    {
        $estimate = $this->audience->estimate($this->draftCampaign($filters), $request->user());
        $reasons = [];
        foreach ($estimate['excluded'] as $reason => $count) {
            $reasons[] = $count.' : '.mb_strtolower(config('prospection.exclusion_reasons')[$reason] ?? $reason);
        }
        $recent = 0;
        if (empty($filters['include_recent'])) {
            $recent = $this->audience->estimate($this->draftCampaign($filters, false), $request->user())['eligible'] - $estimate['eligible'];
            if ($recent > 0) {
                $reasons[] = $recent.' : déjà contactée(s) il y a moins d\'un mois';
            }
        }

        return $estimate + ['reasons' => $reasons, 'recent' => $recent];
    }

    private function sample(array $filters, Request $request)
    {
        $companyFilters = $this->segmentFilters($filters);
        $query = Company::visibleTo($request->user())->filter($companyFilters)->whereHas('contacts', fn ($c) => $c->whereNotNull('email_normalized'));

        return ['count' => (clone $query)->count(), 'names' => $query->orderBy('name')->limit(6)->pluck('name')];
    }

    private function accounts()
    {
        return MailAccount::where('is_active', true)->where('last_test_result', 'ok')->orderBy('name')->get(['id', 'name', 'from_email', 'driver']);
    }

    /** Texte saisi → modèle d'e-mail sobre (texte échappé, variables conservées). */
    private function buildTemplate(array $message, Request $request, string $name): Template
    {
        $paragraphs = collect(preg_split("/\n{2,}/", str_replace("\r", '', trim($message['body']))))
            ->map(fn ($p) => '<p style="margin:0 0 16px">'.nl2br(e($p), false).'</p>')->implode('');
        $html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#1f2937;max-width:600px;margin:0 auto;padding:24px">'
            .$paragraphs.'<p style="margin:0">{{expediteur}}</p></div>';

        $template = Template::create(['name' => Str::limit($name, 150, ''), 'category' => 'prospection']);
        $template->forceFill(['created_by' => $request->user()->id])->save();
        $template->addVersion(['subject' => $message['subject'], 'html' => $html], $request->user()->id);

        return $template->fresh('latestVersion');
    }

    private function replace(string $text, array $data): string
    {
        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/i', fn ($m) => (string) ($data[strtolower($m[1])] ?? ''), $text);
    }

    private function guard(Request $request): void
    {
        abort_unless($request->user()->can('campaigns.manage'), 403);
    }
}
