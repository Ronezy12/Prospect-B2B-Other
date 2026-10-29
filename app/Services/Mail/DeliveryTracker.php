<?php

namespace App\Services\Mail;

use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Subscription;
use App\Services\ActivityRecorder;
use App\Services\Automation\AutomationEngine;
use App\Services\SuppressionService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Applique les événements de livraison (webhooks, pixel, clics, réponses IMAP).
 *  - Idempotent : un événement déjà reçu (même fournisseur, même identifiant) est ignoré.
 *  - Événements en retard : un statut de livraison plus grave n'est jamais écrasé
 *    (un « livré » tardif ne masque pas un rebond).
 *  - Rebond définitif et plainte alimentent la liste d'opposition.
 */
class DeliveryTracker
{
    public const DELIVERY_TYPES = ['livre', 'differe', 'rebond_temporaire', 'rebond_definitif', 'bloque', 'plainte'];

    public function __construct(
        private readonly SuppressionService $suppressions,
        private readonly ActivityRecorder $activities,
        private readonly AutomationEngine $automations,
    ) {}

    /** @return bool false si l'événement avait déjà été enregistré */
    public function record(CampaignRecipient $recipient, string $type, CarbonInterface $at, string $provider, string $eventId, array $payload = []): bool
    {
        $inserted = DB::table('pm_email_events')->insertOrIgnore([
            'recipient_id' => $recipient->id,
            'campaign_id' => $recipient->campaign_id,
            'provider' => $provider,
            'provider_event_id' => mb_substr($eventId, 0, 190),
            'type' => $type,
            'payload' => $payload ? json_encode($payload) : null,
            'occurred_at' => $at,
            'created_at' => now(),
        ]);
        if (! $inserted) {
            return false;
        }

        DB::transaction(fn () => $this->apply($recipient->fresh(), $type, $at, $payload));

        return true;
    }

    private function apply(CampaignRecipient $recipient, string $type, CarbonInterface $at, array $payload): void
    {
        $updates = [];

        if (in_array($type, self::DELIVERY_TYPES, true)) {
            $rank = config('prospection.delivery_precedence');
            if (($rank[$type] ?? 0) >= ($rank[$recipient->delivery_status] ?? 0)) {
                $updates['delivery_status'] = $type;
            }
            if ($type === 'livre') {
                $updates['delivered_at'] = $recipient->delivered_at ?? $at;
            }
            if (in_array($type, ['rebond_definitif', 'rebond_temporaire', 'bloque'], true)) {
                $updates['bounced_at'] = $recipient->bounced_at ?? $at;
            }
        }

        match ($type) {
            'ouverture' => $updates += ['opened_at' => $recipient->opened_at ?? $at, 'open_count' => $recipient->open_count + 1],
            'clic' => $updates += ['clicked_at' => $recipient->clicked_at ?? $at, 'click_count' => $recipient->click_count + 1],
            'reponse' => $updates += ['replied_at' => $recipient->replied_at ?? $at],
            'desabonnement' => $updates += ['unsubscribed_at' => $recipient->unsubscribed_at ?? $at],
            default => null,
        };

        if ($updates) {
            $recipient->forceFill($updates)->save();
        }

        $campaign = $recipient->campaign;
        $contact = $recipient->contact_id ? Contact::withTrashed()->with('company')->find($recipient->contact_id) : null;
        $company = $contact?->company;

        switch ($type) {
            case 'rebond_definitif':
                $this->suppressions->suppress($recipient->email, 'all', 'rebond_definitif', 'fournisseur', 'Campagne #'.$campaign->id);
                if ($company) {
                    $this->activities->record($company, 'systeme', "Adresse invalide (rebond définitif) : {$recipient->email}. Elle est exclue des prochains envois.", ['campaign_id' => $campaign->id], null);
                }
                $this->fire('email.bounced', $contact, $recipient, $type);
                break;
            case 'plainte':
                $this->suppressions->suppress($recipient->email, 'all', 'plainte', 'fournisseur', 'Signalement comme indésirable, campagne #'.$campaign->id);
                break;
            case 'desabonnement':
                $scope = $campaign->type === 'newsletter' ? 'newsletter' : 'prospection';
                $this->suppressions->suppress($recipient->email, $scope, 'desabonnement', $payload['origin'] ?? 'fournisseur', 'Campagne #'.$campaign->id);
                if ($campaign->type === 'newsletter' && $contact) {
                    Subscription::where('list_id', $campaign->list_id)->where('contact_id', $contact->id)->update(['status' => 'desabonne', 'unsubscribed_at' => now()]);
                }
                if ($company) {
                    $this->activities->record($company, 'systeme', "Désinscription de {$recipient->email} (campagne « {$campaign->name} »).", ['campaign_id' => $campaign->id], null);
                }
                $this->fire('email.unsubscribed', $contact, $recipient, $type);
                break;
            case 'reponse':
                if ($company) {
                    $this->activities->record($company, 'email', "Réponse reçue de {$recipient->email} à « {$campaign->name} »".(isset($payload['subject']) ? ' : '.mb_substr($payload['subject'], 0, 120) : '').'.', ['campaign_id' => $campaign->id], null);
                }
                $this->fire('email.replied', $contact, $recipient, $type);
                break;
        }
    }

    private function fire(string $trigger, ?Contact $contact, CampaignRecipient $recipient, string $type): void
    {
        if ($contact?->company) {
            $this->automations->fire($trigger, $contact->company, "{$type}:{$recipient->id}", ['campaign_id' => $recipient->campaign_id, 'contact_id' => $contact->id]);
        }
    }
}
