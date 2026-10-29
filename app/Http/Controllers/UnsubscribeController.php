<?php

namespace App\Http\Controllers;

use App\Models\CampaignRecipient;
use App\Models\EmailEvent;
use App\Models\Subscription;
use App\Services\ActivityRecorder;
use App\Services\SuppressionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Désinscription publique, sans compte : lien signé propre à chaque destinataire.
 * GET affiche une confirmation ; POST (bouton ou « clic unique » des messageries) l'enregistre.
 */
class UnsubscribeController extends Controller
{
    public function show(CampaignRecipient $recipient): View
    {
        return view('public.unsubscribe', ['recipient' => $recipient, 'done' => false, 'scope' => $this->scopeLabel($recipient)]);
    }

    public function store(Request $request, CampaignRecipient $recipient, SuppressionService $suppressions, ActivityRecorder $activities): View|Response
    {
        $campaign = $recipient->campaign;
        $scope = $campaign->type === 'newsletter' ? 'newsletter' : 'prospection';

        DB::transaction(function () use ($recipient, $campaign, $scope, $suppressions, $activities) {
            $suppressions->suppress($recipient->email, $scope, 'desabonnement', 'lien_email', "Campagne #{$campaign->id}");
            if ($campaign->type === 'newsletter' && $recipient->contact_id) {
                Subscription::where('list_id', $campaign->list_id)->where('contact_id', $recipient->contact_id)
                    ->update(['status' => 'desabonne', 'unsubscribed_at' => now()]);
            }
            EmailEvent::firstOrCreate(
                ['provider' => 'interne', 'provider_event_id' => 'unsub-'.$recipient->id],
                ['recipient_id' => $recipient->id, 'campaign_id' => $campaign->id, 'type' => 'desabonnement', 'occurred_at' => now()],
            );
            if ($company = $recipient->contact?->company) {
                $activities->record($company, 'systeme', "Désinscription de {$recipient->email} via le lien de la campagne « {$campaign->name} ».", ['campaign_id' => $campaign->id], null);
            }
        });

        // Clic unique (RFC 8058) : réponse minimale attendue par les messageries.
        if ($request->input('List-Unsubscribe') === 'One-Click') {
            return response('', 200);
        }

        return view('public.unsubscribe', ['recipient' => $recipient, 'done' => true, 'scope' => $this->scopeLabel($recipient)]);
    }

    private function scopeLabel(CampaignRecipient $recipient): string
    {
        return $recipient->campaign->type === 'newsletter' ? 'nos newsletters' : 'nos e-mails de prospection commerciale';
    }
}
