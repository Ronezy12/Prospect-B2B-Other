<?php

namespace App\Http\Controllers;

use App\Models\CampaignRecipient;
use App\Services\Mail\DeliveryTracker;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    public function store(Request $request, CampaignRecipient $recipient, DeliveryTracker $tracker): View|Response
    {
        // Opposition persistante, mise à jour de l'abonnement, historique et scénarios : DeliveryTracker.
        $tracker->record($recipient, 'desabonnement', now(), 'interne', 'unsub-'.$recipient->id, ['origin' => 'lien_email']);

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
