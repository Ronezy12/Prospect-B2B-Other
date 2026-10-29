<?php

namespace App\Services\Social;

use App\Models\SocialAccount;
use App\Models\SocialConversation;
use App\Models\SocialMessage;
use App\Services\ActivityRecorder;
use App\Services\Automation\AutomationEngine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Événements de messagerie reçus de Meta (Messenger et Instagram). Idempotent : Meta
 * renvoie un événement non acquitté pendant plusieurs heures, chaque message n'est
 * enregistré qu'une fois (identifiant « mid » unique).
 */
class MetaWebhookProcessor
{
    public function __construct(
        private readonly SocialProfileService $social,
        private readonly ActivityRecorder $activities,
    ) {}

    /** @return array{recus: int, doublons: int, ignores: int} */
    public function handle(SocialAccount $account, array $payload): array
    {
        $stats = ['recus' => 0, 'doublons' => 0, 'ignores' => 0];
        $channel = match ($payload['object'] ?? null) {
            'page' => 'messenger',
            'instagram' => 'instagram',
            default => null,
        };
        if (! $channel) {
            $stats['ignores']++;

            return $stats;
        }
        $ownIds = array_filter([(string) $account->setting('page_id'), (string) $account->setting('instagram_id')]);

        foreach (array_slice((array) ($payload['entry'] ?? []), 0, 100) as $entry) {
            foreach (array_slice((array) ($entry['messaging'] ?? []), 0, 100) as $event) {
                $message = $event['message'] ?? null;
                $mid = is_array($message) ? (string) ($message['mid'] ?? '') : '';
                if ($mid === '') {
                    $stats['ignores']++; // accusés de lecture / livraison, réactions… non conservés

                    continue;
                }
                $echo = (bool) ($message['is_echo'] ?? false);
                $participant = (string) ($echo ? ($event['recipient']['id'] ?? '') : ($event['sender']['id'] ?? ''));
                $pageSide = (string) ($echo ? ($event['sender']['id'] ?? '') : ($event['recipient']['id'] ?? ''));
                if ($participant === '' || ! preg_match('/^\d{1,64}$/', $participant) || ($ownIds && ! in_array($pageSide, $ownIds, true))) {
                    $stats['ignores']++;

                    continue;
                }
                if (SocialMessage::where('external_id', $mid)->exists()) {
                    $stats['doublons']++;

                    continue;
                }

                $at = isset($event['timestamp']) ? Carbon::createFromTimestampMs((int) $event['timestamp']) : now();
                if ($at->isFuture()) {
                    $at = now();
                }
                $text = isset($message['text']) ? mb_substr((string) $message['text'], 0, 5000) : null;
                if ($text === null && ! empty($message['attachments'])) {
                    $text = '[Pièce jointe — à consulter sur la plateforme]';
                }

                $this->store($account, $channel, $participant, $mid, $echo, $text, $at) ? $stats['recus']++ : $stats['doublons']++;
            }
        }
        $account->forceFill(['last_event_at' => now()])->save();

        return $stats;
    }

    private function store(SocialAccount $account, string $channel, string $participant, string $mid, bool $echo, ?string $text, Carbon $at): bool
    {
        $conversation = DB::transaction(function () use ($account, $channel, $participant, $mid, $echo, $text, $at) {
            $conversation = SocialConversation::firstOrCreate(['account_id' => $account->id, 'channel' => $channel, 'participant_id' => $participant]);
            $inserted = DB::table('pm_social_messages')->insertOrIgnore([
                'conversation_id' => $conversation->id,
                'direction' => $echo ? 'sortant' : 'entrant',
                'external_id' => $mid,
                'body' => $text,
                // Écho : message envoyé depuis les outils Meta (Business Suite…), pas depuis ce module.
                'status' => $echo ? 'envoye_hors_module' : 'recu',
                'sent_at' => $at,
                'created_at' => now(),
            ]);
            if (! $inserted) {
                return null;
            }
            $updates = ['last_message_at' => max($conversation->last_message_at ?? $at, $at)];
            if (! $echo) {
                $updates['last_inbound_at'] = max($conversation->last_inbound_at ?? $at, $at);
                $updates['unread'] = true;
                $updates['status'] = 'ouverte';
            }
            $conversation->forceFill($updates)->save();

            return $conversation;
        });

        if (! $conversation) {
            return false;
        }
        if (! $echo) {
            $this->recordReply($conversation, $text, $at, $mid);
        }

        return true;
    }

    /** Réponse d'un prospect déjà rattaché : historique de la fiche + déclencheur d'automatisation. */
    public function recordReply(SocialConversation $conversation, ?string $text, Carbon $at, string $key): void
    {
        if ($conversation->profile && ! $conversation->profile->isOpposed()) {
            $this->social->logInteraction($conversation->profile, 'message_recu', $text, $at, null, null, null, false, 'meta');
        } elseif ($conversation->company) {
            $this->activities->record($conversation->company, 'reseau_social', "{$conversation->channelLabel()} — message reçu".($text ? ' : '.mb_substr($text, 0, 300) : '.'), ['social_conversation_id' => $conversation->id], null);
            app(AutomationEngine::class)->fire('social.reply_received', $conversation->company, 'meta:'.$key, ['social_conversation_id' => $conversation->id]);
        }
    }
}
