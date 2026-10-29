<?php

namespace App\Services\Replies;

use App\Models\CampaignRecipient;
use App\Models\Inbox;
use App\Services\Mail\DeliveryTracker;
use Throwable;

/**
 * Rapproche les messages reçus des e-mails envoyés :
 *  1. par les en-têtes In-Reply-To / References, qui contiennent le Message-ID
 *     généré à l'envoi (clé d'idempotence du destinataire) — fiable ;
 *  2. à défaut, par l'adresse de l'expéditeur, si elle a reçu un e-mail accepté
 *     dans les 60 derniers jours — probable (la réponse la plus récente est retenue).
 * Les réponses automatiques (absence, accusés) ne sont pas filtrées par le contenu,
 * puisque le corps n'est pas lu : le journal l'indique comme « réponse reçue ».
 */
class ReplyDetector
{
    private const FALLBACK_DAYS = 60;

    private const BATCH = 200;

    public function __construct(private readonly InboxReader $reader, private readonly DeliveryTracker $tracker) {}

    /** @return array{lus: int, reponses: int} */
    public function check(Inbox $inbox): array
    {
        $read = 0;
        $replies = 0;
        $lastUid = $inbox->last_uid;

        try {
            foreach ($this->reader->fetch($inbox, $inbox->last_uid, self::BATCH) as $message) {
                $read++;
                $lastUid = max($lastUid, $message->uid);
                $recipient = $this->match($message);
                if ($recipient && $this->tracker->record($recipient, 'reponse', $message->date, 'imap', "imap:{$inbox->id}:{$message->uid}", ['subject' => $message->subject])) {
                    $replies++;
                }
            }
            $inbox->forceFill(['last_uid' => $lastUid, 'last_checked_at' => now(), 'last_result' => "ok : {$read} message(s) lu(s), {$replies} réponse(s)"])->save();
        } catch (Throwable $e) {
            report($e);
            $inbox->forceFill(['last_uid' => $lastUid, 'last_checked_at' => now(), 'last_result' => 'Échec de connexion IMAP : vérifiez le serveur et les identifiants.'])->save();
        }

        return ['lus' => $read, 'reponses' => $replies];
    }

    public function match(IncomingMessage $message): ?CampaignRecipient
    {
        $ids = array_filter(array_merge([$message->inReplyTo], $message->references));
        foreach ($ids as $id) {
            if ($recipient = CampaignRecipient::where('provider_message_id', $id)->first()) {
                return $recipient;
            }
            if (preg_match('/<?([a-f0-9]{64})@/i', $id, $m)) {
                if ($recipient = CampaignRecipient::where('idempotency_key', strtolower($m[1]))->first()) {
                    return $recipient;
                }
            }
        }

        if ($message->from === '') {
            return null;
        }

        return CampaignRecipient::where('email', $message->from)->where('status', 'accepte')
            ->where('sent_at', '>=', $message->date->copy()->subDays(self::FALLBACK_DAYS))
            ->where('sent_at', '<=', $message->date)
            ->latest('sent_at')->first();
    }
}
