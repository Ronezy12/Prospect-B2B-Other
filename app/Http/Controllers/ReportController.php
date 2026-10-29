<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Company;
use App\Models\Inbox;
use App\Models\Opportunity;
use App\Models\PipelineStage;
use App\Models\Setting;
use App\Models\StageHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Statistiques e-mail et commerciales. Chaque indicateur provient d'événements
 * enregistrés ; un indicateur que le fournisseur ne remonte pas est signalé
 * comme indisponible plutôt qu'affiché à zéro.
 */
class ReportController extends Controller
{
    public const PERIODS = [30 => '30 derniers jours', 90 => '90 derniers jours', 365 => '12 derniers mois'];

    public function __invoke(Request $request): View
    {
        abort_unless($request->user()->can('reports.view'), 403);
        $days = array_key_exists((int) $request->query('jours'), self::PERIODS) ? (int) $request->query('jours') : 30;
        $since = now()->subDays($days)->startOfDay();

        $recipients = CampaignRecipient::query()->whereHas('campaign')->where('created_at', '>=', $since);
        $sent = (clone $recipients)->where('status', 'accepte');
        $sentCount = (clone $sent)->count();
        $eventDriven = (clone $sent)->whereHas('campaign.mailAccount', fn ($q) => $q->where('driver', 'brevo'))->count();

        $email = [
            'demandes' => (clone $recipients)->whereIn('status', ['accepte', 'echec', 'a_verifier', 'en_attente', 'envoi'])->count(),
            'acceptes' => $sentCount,
            'echecs' => (clone $recipients)->where('status', 'echec')->count(),
            'exclus' => (clone $recipients)->where('status', 'exclu')->count(),
            'livres' => (clone $sent)->where('delivery_status', 'livre')->count(),
            'rebonds_temporaires' => (clone $sent)->where('delivery_status', 'rebond_temporaire')->count(),
            'rebonds_definitifs' => (clone $sent)->whereIn('delivery_status', ['rebond_definitif', 'bloque'])->count(),
            'plaintes' => (clone $sent)->where('delivery_status', 'plainte')->count(),
            'ouvertures' => (clone $sent)->whereNotNull('opened_at')->count(),
            'clics' => (clone $sent)->whereNotNull('clicked_at')->count(),
            'desabonnements' => (clone $recipients)->whereNotNull('unsubscribed_at')->count(),
            'reponses' => (clone $sent)->whereNotNull('replied_at')->count(),
        ];

        $wonStageIds = PipelineStage::where('type', 'won')->pluck('id');
        $commercial = [
            'nouveaux_prospects' => Company::visibleTo($request->user())->where('created_at', '>=', $since)->count(),
            'opportunites_creees' => Opportunity::visibleTo($request->user())->active()->where('created_at', '>=', $since)->count(),
            'opportunites_gagnees' => StageHistory::whereIn('to_stage_id', $wonStageIds)->where('changed_at', '>=', $since)
                ->whereIn('opportunity_id', Opportunity::visibleTo($request->user())->select('id'))->distinct('opportunity_id')->count('opportunity_id'),
            'conversions_clients' => Company::visibleTo($request->user())->where('lifecycle', 'client')->where('converted_at', '>=', $since)->count(),
            'montant_gagne' => (float) Opportunity::visibleTo($request->user())->active()->whereIn('stage_id', $wonStageIds)->where('closed_at', '>=', $since)->sum('amount'),
        ];

        $campaigns = Campaign::where('type', '!=', 'individuel')
            ->where(fn ($q) => $q->where('started_at', '>=', $since)->orWhere('scheduled_at', '>=', $since))
            ->with('mailAccount:id,name,driver')
            ->withCount([
                'recipients as acceptes' => fn ($q) => $q->where('status', 'accepte'),
                'recipients as livres' => fn ($q) => $q->where('delivery_status', 'livre'),
                'recipients as rebonds' => fn ($q) => $q->whereIn('delivery_status', ['rebond_definitif', 'bloque']),
                'recipients as ouvertures' => fn ($q) => $q->whereNotNull('opened_at'),
                'recipients as clics' => fn ($q) => $q->whereNotNull('clicked_at'),
                'recipients as reponses' => fn ($q) => $q->whereNotNull('replied_at'),
                'recipients as desabonnements' => fn ($q) => $q->whereNotNull('unsubscribed_at'),
            ])
            ->latest('started_at')->limit(50)->get();

        return view('reports.index', [
            'days' => $days,
            'email' => $email,
            'commercial' => $commercial,
            'campaigns' => $campaigns,
            'availability' => [
                // Livraisons et rebonds : uniquement via webhooks d'un fournisseur API.
                'delivery' => $eventDriven > 0,
                'opens' => (bool) Setting::get('tracking.opens', false),
                'clicks' => (bool) Setting::get('tracking.clicks', false),
                'replies' => Inbox::where('is_active', true)->exists(),
            ],
            'since' => Carbon::parse($since),
        ]);
    }
}
