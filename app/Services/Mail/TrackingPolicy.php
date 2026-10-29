<?php

namespace App\Services\Mail;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Setting;

/**
 * Suivi des ouvertures (pixel) et des clics : désactivé par défaut.
 * Recommandation CNIL du 12/03/2026 : consentement préalable du destinataire.
 * Le suivi n'est donc appliqué que si (1) il est activé globalement, (2) la campagne
 * le demande et (3) le contact a donné son consentement, tracé avec sa source.
 */
class TrackingPolicy
{
    public function allows(string $kind, Campaign $campaign, ?Contact $contact): bool
    {
        $enabled = $kind === 'opens' ? Setting::get('tracking.opens', false) : Setting::get('tracking.clicks', false);
        $requested = $kind === 'opens' ? $campaign->track_opens : $campaign->track_clicks;

        return $enabled && $requested && $contact?->tracking_consent_at !== null;
    }
}
