<?php

namespace App\Services\Mail;

use App\Http\Controllers\TrackingController;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\EmailEvent;
use App\Models\MailAccount;
use App\Services\ActivityRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Traite les destinataires « en attente » par lots. Garanties :
 *  - chaque destinataire est réservé atomiquement avant l'envoi (pas de double envoi
 *    entre deux workers) ;
 *  - la conformité est revérifiée juste avant l'appel au fournisseur ;
 *  - les quotas horaire et journalier du compte d'envoi sont respectés ;
 *  - une erreur temporaire est retentée avec délai, une erreur définitive ne l'est pas ;
 *  - un envoi interrompu en plein appel reste « envoi » puis passe « à vérifier »,
 *    sans renvoi automatique (on ne sait pas si le fournisseur l'a pris en charge).
 */
class CampaignSender
{
    public function __construct(
        private readonly MailProviderFactory $providers,
        private readonly TemplateRenderer $renderer,
        private readonly ComplianceGate $gate,
        private readonly ActivityRecorder $activities,
        private readonly TrackingPolicy $tracking,
    ) {}

    /**
     * Envoie un lot. Retourne le nombre de secondes avant le prochain lot
     * (0 = continuer, null = plus rien à faire pour l'instant).
     */
    public function processBatch(Campaign $campaign): ?int
    {
        $campaign->refresh();
        if ($campaign->status !== 'en_cours') {
            return null;
        }

        $account = $campaign->mailAccount;
        if (! $account || ! $account->is_active) {
            $campaign->forceFill(['status' => 'en_pause'])->save();

            return null;
        }

        $remaining = $this->quotaRemaining($account);
        if ($remaining <= 0) {
            return 300; // quota atteint : reprise automatique plus tard
        }

        $ids = CampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'en_attente')
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('id')
            ->limit(min((int) config('prospection.mail.batch_size'), $remaining))
            ->pluck('id');

        if ($ids->isEmpty()) {
            return $this->finishIfDone($campaign);
        }

        $lock = now();
        CampaignRecipient::whereIn('id', $ids)->where('status', 'en_attente')->update(['status' => 'envoi', 'locked_at' => $lock]);
        $claimed = CampaignRecipient::whereIn('id', $ids)->where('status', 'envoi')->where('locked_at', $lock)->get();

        $version = $campaign->templateVersion;
        foreach ($claimed as $recipient) {
            if (Campaign::whereKey($campaign->id)->value('status') !== 'en_cours') {
                // Pause ou annulation pendant le lot : on rend la main proprement.
                CampaignRecipient::whereKey($recipient->id)->where('status', 'envoi')->update(['status' => 'en_attente', 'locked_at' => null]);

                continue;
            }
            $this->sendOne($campaign, $account, $version->subject, $version->html, $version->preheader, $recipient);
        }

        return 0;
    }

    private function sendOne(Campaign $campaign, MailAccount $account, string $subject, string $html, ?string $preheader, CampaignRecipient $recipient): void
    {
        $contact = $recipient->contact_id ? Contact::withTrashed()->with('company')->find($recipient->contact_id) : null;
        $reason = $contact ? $this->gate->check($campaign, $contact) : 'fiche_supprimee';
        if ($reason) {
            $recipient->forceFill(['status' => 'exclu', 'exclusion_reason' => $reason, 'locked_at' => null])->save();

            return;
        }

        $data = (array) $recipient->merge_data + ['expediteur' => $campaign->from_name ?: $account->from_name];
        $headers = [];
        if ($campaign->isMarketing()) {
            $url = AudienceBuilder::unsubscribeUrl($recipient);
            $data['lien_desinscription'] = $url;
            // Désinscription en un clic reconnue par les messageries (RFC 8058).
            $headers['List-Unsubscribe'] = '<'.$url.'>';
            $headers['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
        }
        $rendered = $this->renderer->render($subject, $html, $preheader, $data, $campaign->isMarketing(), $campaign->from_name ?: $account->from_name);
        $rendered['html'] = $this->applyTracking($rendered['html'], $campaign, $contact, $recipient, $data['lien_desinscription'] ?? null);

        $message = new OutgoingMessage(
            toEmail: $recipient->email,
            toName: trim(($data['prenom'] ?? '').' '.($data['nom'] ?? '')) ?: null,
            fromEmail: $account->from_email,
            fromName: $campaign->from_name ?: $account->from_name,
            replyTo: $campaign->reply_to ?: $account->reply_to,
            subject: $rendered['subject'],
            html: $rendered['html'],
            text: $rendered['text'],
            messageKey: $recipient->idempotency_key,
            headers: $headers,
        );

        $provider = $this->providers->for($account);
        $this->event($recipient, $provider->name(), 'demande');
        $result = $provider->send($message);

        DB::transaction(function () use ($recipient, $result, $provider, $campaign, $contact) {
            $attempts = $recipient->attempts + 1;
            if ($result->isAccepted()) {
                $recipient->forceFill([
                    'status' => 'accepte', 'attempts' => $attempts, 'sent_at' => now(), 'locked_at' => null,
                    'provider_message_id' => $result->messageId, 'last_error' => null,
                ])->save();
                $this->event($recipient, $provider->name(), 'accepte', ['message_id' => $result->messageId]);
                if ($contact?->company) {
                    $this->activities->record($contact->company, 'email', "E-mail « {$campaign->name} » accepté par le serveur d'envoi pour {$recipient->email}.", ['campaign_id' => $campaign->id], $campaign->launched_by);
                }

                return;
            }

            $retry = $result->status === 'temporary' && $attempts < (int) config('prospection.mail.max_attempts');
            $delays = config('prospection.mail.retry_minutes');
            $recipient->forceFill([
                'status' => $retry ? 'en_attente' : 'echec',
                'attempts' => $attempts,
                'locked_at' => null,
                'last_error' => $result->error,
                'next_attempt_at' => $retry ? now()->addMinutes($delays[$attempts - 1] ?? end($delays)) : null,
            ])->save();
            $this->event($recipient, $provider->name(), $retry ? 'echec_temporaire' : 'echec', ['erreur' => $result->error]);
        });
    }

    /** Pixel et liens de suivi, uniquement si la politique de suivi l'autorise pour ce contact. */
    private function applyTracking(string $html, Campaign $campaign, Contact $contact, CampaignRecipient $recipient, ?string $unsubscribe): string
    {
        if ($this->tracking->allows('clicks', $campaign, $contact)) {
            $html = preg_replace_callback('/href="(https?:\/\/[^"]+)"/i', function ($m) use ($recipient, $unsubscribe) {
                $url = html_entity_decode($m[1]);
                if ($unsubscribe && $url === $unsubscribe) {
                    return $m[0];
                }

                return 'href="'.e(route('tracking.click', ['recipient' => $recipient->id, 'signature' => TrackingController::sign($recipient->id, $url), 'u' => $url])).'"';
            }, $html);
        }
        if ($this->tracking->allows('opens', $campaign, $contact)) {
            $pixel = '<img src="'.e(route('tracking.open', ['recipient' => $recipient->id, 'signature' => TrackingController::sign($recipient->id, 'open')])).'" width="1" height="1" alt="" style="display:block;border:0">';
            $html = stripos($html, '</body>') !== false ? preg_replace('#</body>#i', $pixel.'</body>', $html, 1) : $html.$pixel;
        }

        return $html;
    }

    /** Termine la campagne quand plus aucun destinataire n'est en attente. */
    public function finishIfDone(Campaign $campaign): ?int
    {
        $pending = CampaignRecipient::where('campaign_id', $campaign->id)->whereIn('status', ['en_attente', 'envoi']);
        if (! (clone $pending)->exists()) {
            Campaign::whereKey($campaign->id)->where('status', 'en_cours')->update(['status' => 'terminee', 'finished_at' => now()]);

            return null;
        }

        $next = (clone $pending)->where('status', 'en_attente')->min('next_attempt_at');

        return $next ? (int) max(30, now()->diffInSeconds($next, false)) : 60;
    }

    /** Envois acceptés restants dans la fenêtre horaire et journalière du compte. */
    public function quotaRemaining(MailAccount $account): int
    {
        $sent = fn ($since) => CampaignRecipient::query()
            ->join('pm_campaigns', 'pm_campaigns.id', '=', 'pm_campaign_recipients.campaign_id')
            ->where('pm_campaigns.mail_account_id', $account->id)
            ->where('pm_campaign_recipients.sent_at', '>=', $since)
            ->count();

        return max(0, min($account->hourly_limit - $sent(now()->subHour()), $account->daily_limit - $sent(now()->subDay())));
    }

    /** Destinataires bloqués « envoi » trop longtemps (worker interrompu) : vérification humaine. */
    public function flagStale(): int
    {
        return CampaignRecipient::where('status', 'envoi')
            ->where('locked_at', '<', now()->subMinutes((int) config('prospection.mail.stale_minutes')))
            ->update(['status' => 'a_verifier', 'last_error' => 'Envoi interrompu : vérifier auprès du fournisseur avant tout renvoi.']);
    }

    private function event(CampaignRecipient $recipient, string $provider, string $type, array $payload = []): void
    {
        EmailEvent::create([
            'recipient_id' => $recipient->id,
            'campaign_id' => $recipient->campaign_id,
            'provider' => $provider,
            'type' => $type,
            'payload' => $payload ?: null,
            'occurred_at' => now(),
        ]);
    }
}
