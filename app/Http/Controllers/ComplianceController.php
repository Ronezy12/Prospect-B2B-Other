<?php

namespace App\Http\Controllers;

use App\Models\PrivacyRequest;
use App\Models\Setting;
use App\Models\SocialOpposition;
use App\Models\Suppression;
use App\Services\AuditLogger;
use App\Services\Privacy\PrivacyService;
use App\Services\Privacy\RetentionService;
use App\Services\Social\SocialProfileService;
use App\Services\SuppressionService;
use App\Support\Normalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Espace conformité (rôle conformité / administrateur) : demandes des personnes,
 * liste d'opposition et levée motivée, durées de conservation, registre des traitements.
 */
class ComplianceController extends Controller
{
    public function __construct(
        private readonly PrivacyService $privacy,
        private readonly SuppressionService $suppressions,
        private readonly AuditLogger $audit,
    ) {}

    /** Demandes des personnes + recherche « que sait-on sur cette adresse ? ». */
    public function requests(Request $request): View
    {
        $this->guard($request);
        $email = Normalizer::email((string) $request->query('email', ''));
        $report = null;
        if ($email) {
            abort_unless(filter_var($email, FILTER_VALIDATE_EMAIL), 422);
            $report = $this->privacy->report($email);
            $this->audit->log('privacy.searched', null, ['empreinte' => substr($this->suppressions->hash($email), 0, 12)]);
        }

        return view('compliance.requests', [
            'email' => $email,
            'report' => $report,
            'requests' => PrivacyRequest::with('handler:id,name')->orderByRaw("status = 'ouverte' desc")->orderBy('due_at')->paginate(25),
            'lateCount' => PrivacyRequest::where('status', 'ouverte')->where('due_at', '<', today())->count(),
        ]);
    }

    public function storeRequest(Request $request): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(PrivacyRequest::TYPES))],
            'email' => ['nullable', 'email', 'max:190'],
            'requester_label' => ['nullable', 'string', 'max:120'],
            'channel' => ['nullable', Rule::in(['email', 'courrier', 'telephone', 'formulaire', 'autre'])],
            'received_at' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [], ['type' => 'type de demande', 'received_at' => 'date de réception']);

        $item = new PrivacyRequest($data);
        $item->received_at = Carbon::parse($data['received_at']);
        $item->due_at = Carbon::parse($data['received_at'])->addDays((int) config('prospection.retention.privacy_request_days'));
        $item->email_hash = filled($data['email'] ?? null) ? $this->suppressions->hash($data['email']) : null;
        $item->created_by = $request->user()->id;
        $item->save();
        $this->audit->log('privacy.request_created', $item, ['type' => $item->type]);

        return back()->with('success', 'Demande enregistrée : réponse attendue au plus tard le '.$item->due_at->format('d/m/Y').'.');
    }

    public function updateRequest(Request $request, PrivacyRequest $privacyRequest): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(PrivacyRequest::STATUSES))],
            'outcome' => ['required_unless:status,ouverte', 'nullable', 'string', 'max:2000'],
        ], ['outcome.required_unless' => 'Indiquez ce qui a été fait (ou le motif du refus).']);
        $privacyRequest->forceFill([
            'status' => $data['status'],
            'outcome' => $data['outcome'] ?? null,
            'handled_by' => $data['status'] === 'ouverte' ? null : $request->user()->id,
            'handled_at' => $data['status'] === 'ouverte' ? null : now(),
        ])->save();
        $this->audit->log('privacy.request_updated', $privacyRequest, ['statut' => $data['status']]);

        return back()->with('success', 'Demande mise à jour.');
    }

    /** Droit d'accès / portabilité : fichier JSON lisible de tout ce qui concerne l'adresse. */
    public function export(Request $request): StreamedResponse
    {
        $this->guard($request);
        $email = $request->validate(['email' => ['required', 'email']])['email'];
        $report = $this->privacy->report($email);
        $this->audit->log('privacy.exported', null, ['empreinte' => substr($this->suppressions->hash($email), 0, 12)]);

        return response()->streamDownload(
            fn () => print (json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'donnees-personnelles-'.now()->format('Ymd-His').'.json',
            ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store'],
        );
    }

    public function erase(Request $request): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'email' => ['required', 'email'],
            'confirm_email' => ['required', 'same:email'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['confirm_email.same' => 'Retapez exactement la même adresse pour confirmer l\'effacement.'], ['confirm_email' => 'confirmation']);

        $done = $this->privacy->erase($data['email'], $request->user(), $data['note'] ?? null);

        return redirect()->route('pm.compliance.requests')->with('success', sprintf(
            'Effacement réalisé : %d fiche(s) contact, %d e-mail(s), %d abonnement(s), %d profil(s) social(aux). L\'adresse reste dans la liste d\'opposition (empreinte seulement) pour ne jamais être réintroduite.',
            $done['contacts'], $done['emails'], $done['abonnements'], $done['profils_sociaux'],
        ));
    }

    /** Liste d'opposition : les adresses ne sont connues que par empreinte ; on cherche en retapant l'adresse. */
    public function suppressions(Request $request): View
    {
        $this->guard($request);
        $email = Normalizer::email((string) $request->query('email', ''));

        return view('compliance.suppressions', [
            'email' => $email,
            'matches' => $email ? Suppression::with('author:id,name')->where('email_hash', $this->suppressions->hash($email))->get() : collect(),
            'byReason' => Suppression::selectRaw('reason, count(*) as total')->groupBy('reason')->pluck('total', 'reason'),
            'recent' => Suppression::with('author:id,name')->latest('id')->limit(20)->get(),
            'socialCount' => SocialOpposition::count(),
        ]);
    }

    /** Levée motivée d'une opposition (ex. nouveau consentement écrit). Tracée dans le registre. */
    public function lift(Request $request, Suppression $suppression): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'justification' => ['required', 'string', 'min:15', 'max:1000'],
            'confirm' => ['accepted'],
        ], ['justification.min' => 'Expliquez précisément la raison (ex. « nouveau consentement écrit reçu le 02/11 par e-mail »).', 'confirm.accepted' => 'Confirmez la levée.']);

        $item = new PrivacyRequest(['type' => 'levee_opposition', 'received_at' => today(), 'due_at' => today(), 'note' => $data['justification']]);
        $item->email_hash = $suppression->email_hash;
        $item->created_by = $request->user()->id;
        $item->forceFill(['status' => 'traitee', 'outcome' => 'Opposition levée ('.(config('prospection.suppression_reasons')[$suppression->reason] ?? $suppression->reason).', périmètre '.$suppression->scope.').', 'handled_by' => $request->user()->id, 'handled_at' => now()])->save();
        $this->audit->log('suppression.lifted', $suppression, ['motif' => $suppression->reason, 'périmètre' => $suppression->scope, 'justification' => $data['justification']]);
        $suppression->delete();

        return back()->with('success', 'Opposition levée. La décision est tracée dans le registre des demandes.');
    }

    public function liftSocial(Request $request, SocialProfileService $social): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'platform' => ['required', Rule::in(array_keys(config('prospection.social.platforms')))],
            'url' => ['required', 'string', 'max:255'],
            'justification' => ['required', 'string', 'min:15', 'max:1000'],
        ]);
        $canonical = $social->normalizeUrl($data['platform'], $data['url']);
        $opposition = $canonical ? SocialOpposition::where('fingerprint', $social->fingerprint($canonical))->first() : null;
        if (! $opposition) {
            return back()->withErrors(['url' => 'Aucune opposition enregistrée pour ce profil.']);
        }
        $this->audit->log('social_opposition.lifted', null, ['plateforme' => $opposition->platform, 'justification' => $data['justification']]);
        $opposition->delete();

        return back()->with('success', 'Opposition sur ce profil levée.');
    }

    public function retention(Request $request, RetentionService $retention): View
    {
        $this->guard($request);

        return view('compliance.retention', [
            'rules' => config('prospection.retention'),
            'preview' => $retention->run(true),
            'lastRun' => Setting::get('retention.last_run'),
        ]);
    }

    public function applyRetention(Request $request, RetentionService $retention): RedirectResponse
    {
        $this->guard($request);
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Confirmez l\'application des durées de conservation.']);
        $report = $retention->run();

        return back()->with('success', 'Durées de conservation appliquées : '.$report['prospects_inactifs'].' prospect(s) archivé(s), '.$report['suivis_effaces'].' suivi(s) d\'e-mail effacé(s).');
    }

    /** Registre des traitements (art. 30 RGPD), pré-rempli à partir de la configuration réelle de l'outil. */
    public function register(Request $request): View
    {
        $this->guard($request);

        return view('compliance.register', ['rules' => config('prospection.retention')]);
    }

    private function guard(Request $request): void
    {
        abort_unless($request->user()->can('compliance.manage'), 403);
    }
}
