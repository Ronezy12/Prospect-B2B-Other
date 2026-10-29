<?php

namespace App\Services\Mail;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Subscription;
use App\Services\SuppressionService;

/**
 * Point de contrôle unique avant tout envoi (constitution de l'audience, puis
 * à nouveau juste avant l'appel au fournisseur). Règles implémentées :
 *  - fiche ou entreprise supprimée, absence d'e-mail : exclusion ;
 *  - adresse invalide (rebond définitif) : exclusion pour toute finalité ;
 *  - opposition / désabonnement : exclusion des envois marketing du périmètre ;
 *  - prospection et e-mails individuels : un particulier (B2C) exige un consentement ;
 *  - newsletter : abonnement actif à la liste exigé.
 * Ces règles traduisent l'art. L34-5 du CPCE et le RGPD ; elles doivent être
 * validées par un juriste et ne garantissent pas à elles seules la conformité.
 */
class ComplianceGate
{
    public function __construct(private readonly SuppressionService $suppressions) {}

    /**
     * @param  iterable<Contact>  $contacts  contacts avec leur entreprise chargée
     * @return array<int, string|null> id du contact → motif d'exclusion (null = autorisé)
     */
    public function evaluate(Campaign $campaign, iterable $contacts): array
    {
        $contacts = collect($contacts);
        $blocked = $this->suppressions->blockingReasons($contacts->pluck('email')->filter()->all(), $campaign->suppressionScope());

        $subscribed = [];
        if ($campaign->type === 'newsletter') {
            $subscribed = Subscription::where('list_id', $campaign->list_id)->where('status', 'abonne')
                ->whereIn('contact_id', $contacts->pluck('id'))->pluck('contact_id')->flip()->all();
        }

        $result = [];
        foreach ($contacts as $contact) {
            $result[$contact->id] = $this->reason($campaign, $contact, $blocked, $subscribed);
        }

        return $result;
    }

    public function check(Campaign $campaign, Contact $contact): ?string
    {
        return $this->evaluate($campaign, [$contact])[$contact->id];
    }

    private function reason(Campaign $campaign, Contact $contact, array $blocked, array $subscribed): ?string
    {
        if ($contact->trashed() || ($contact->company_id && ! $contact->company)) {
            return 'fiche_supprimee';
        }
        if (! $contact->email) {
            return 'sans_email';
        }
        if (isset($blocked[$contact->email])) {
            return $blocked[$contact->email];
        }
        if (in_array($campaign->type, ['prospection', 'individuel'], true)
            && $contact->audience_type === 'b2c' && $contact->legal_basis !== 'consentement') {
            return 'b2c_sans_consentement';
        }
        if ($campaign->type === 'newsletter' && ! isset($subscribed[$contact->id])) {
            return 'non_abonne';
        }

        return null;
    }
}
