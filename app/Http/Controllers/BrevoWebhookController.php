<?php

namespace App\Http\Controllers;

use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\MailAccount;
use App\Services\Mail\DeliveryTracker;
use App\Services\Mail\TrackingPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Webhooks transactionnels Brevo. Authentification par jeton secret propre au compte
 * (dans l'URL déclarée chez Brevo). Les événements dupliqués ou désordonnés sont gérés
 * par le DeliveryTracker. Les ouvertures et clics de contacts sans consentement ne sont pas conservés.
 */
class BrevoWebhookController extends Controller
{
    private const MAP = [
        'delivered' => 'livre',
        'deferred' => 'differe',
        'soft_bounce' => 'rebond_temporaire',
        'hard_bounce' => 'rebond_definitif',
        'invalid_email' => 'rebond_definitif',
        'blocked' => 'bloque',
        'spam' => 'plainte',
        'complaint' => 'plainte',
        'unsubscribed' => 'desabonnement',
        'opened' => 'ouverture',
        'unique_opened' => 'ouverture',
        'click' => 'clic',
    ];

    public function __invoke(Request $request, string $token, DeliveryTracker $tracker, TrackingPolicy $tracking): JsonResponse
    {
        $account = MailAccount::where('driver', 'brevo')->get()->first(fn (MailAccount $a) => filled($a->setting('webhook_token')) && hash_equals((string) $a->setting('webhook_token'), $token));
        if (! $account) {
            Log::warning('Webhook Brevo refusé : jeton invalide', ['ip' => $request->ip()]);

            return response()->json(['error' => 'unauthorized'], 401);
        }

        $events = array_is_list($request->all()) ? $request->all() : [$request->all()];
        $stats = ['traites' => 0, 'doublons' => 0, 'ignores' => 0];

        foreach (array_slice($events, 0, 500) as $event) {
            $type = self::MAP[$event['event'] ?? ''] ?? null;
            $recipient = $type ? $this->recipient($event, $account) : null;
            if (! $type || ! $recipient) {
                $stats['ignores']++;

                continue;
            }
            if (in_array($type, ['ouverture', 'clic'], true)) {
                $contact = $recipient->contact_id ? Contact::find($recipient->contact_id) : null;
                if (! $tracking->allows($type === 'ouverture' ? 'opens' : 'clicks', $recipient->campaign, $contact)) {
                    $stats['ignores']++;

                    continue;
                }
            }

            $at = isset($event['ts_event']) ? Carbon::createFromTimestamp((int) $event['ts_event']) : (isset($event['date']) ? Carbon::parse($event['date']) : now());
            $eventId = 'brevo:'.sha1(($event['event'] ?? '').'|'.($event['message-id'] ?? '').'|'.($event['ts_event'] ?? $event['date'] ?? '').'|'.($event['link'] ?? ''));
            $payload = array_filter(['raison' => $event['reason'] ?? null, 'lien' => $event['link'] ?? null, 'evenement' => $event['event'] ?? null]);

            $tracker->record($recipient, $type, $at, 'brevo', $eventId, $payload) ? $stats['traites']++ : $stats['doublons']++;
        }

        return response()->json($stats);
    }

    private function recipient(array $event, MailAccount $account): ?CampaignRecipient
    {
        $query = CampaignRecipient::query()->whereHas('campaign', fn ($q) => $q->where('mail_account_id', $account->id));
        if ($key = $event['X-Mailin-custom'] ?? null) {
            if ($found = (clone $query)->where('idempotency_key', $key)->first()) {
                return $found;
            }
        }
        if ($messageId = $event['message-id'] ?? null) {
            return (clone $query)->where('provider_message_id', $messageId)->first();
        }

        return null;
    }
}
