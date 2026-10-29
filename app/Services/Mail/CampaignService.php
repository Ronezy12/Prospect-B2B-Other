<?php

namespace App\Services\Mail;

use App\Jobs\SendCampaignBatch;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\MailAccount;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CampaignService
{
    public function __construct(
        private readonly AudienceBuilder $audience,
        private readonly MailProviderFactory $providers,
        private readonly TemplateRenderer $renderer,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Vérification avant envoi.
     *
     * @return array{errors: array<int, string>, warnings: array<int, string>, estimate: array}
     */
    public function preflight(Campaign $campaign, User $user): array
    {
        $errors = [];
        $warnings = [];
        $version = $campaign->template?->latestVersion;

        if (! $version) {
            $errors[] = 'Aucun modèle enregistré : choisissez un modèle et enregistrez son contenu.';
        } elseif (trim($version->subject) === '') {
            $errors[] = 'Le modèle n\'a pas d\'objet.';
        }

        $account = $campaign->mailAccount;
        if (! $account) {
            $errors[] = 'Aucun compte d\'envoi sélectionné.';
        } elseif (! $account->is_active) {
            $errors[] = "Le compte d'envoi « {$account->name} » est désactivé.";
        } elseif (! $account->last_tested_at) {
            $warnings[] = "Le compte d'envoi « {$account->name} » n'a jamais été testé.";
        } elseif ($account->last_test_result !== 'ok') {
            $errors[] = "Le dernier test du compte « {$account->name} » a échoué : corrigez sa configuration.";
        }
        if ($account?->driver === 'log') {
            $warnings[] = 'Compte en mode « journal local » : aucun e-mail ne sera réellement envoyé.';
        }

        if ($campaign->type === 'newsletter' && ! $campaign->list_id) {
            $errors[] = 'Choisissez la liste d\'abonnés de la newsletter.';
        }
        if (in_array($campaign->type, ['prospection', 'operationnel'], true) && ! $campaign->segment_id) {
            $errors[] = 'Choisissez une liste de destinataires.';
        }
        if ($campaign->type === 'operationnel' && $version && preg_match('/promo|offre|remise|réduction/i', $version->subject)) {
            $warnings[] = 'Un message opérationnel ne doit pas contenir de contenu promotionnel : sinon il relève de la prospection.';
        }
        if (! $campaign->last_test_at) {
            $warnings[] = 'Aucun e-mail de test n\'a été envoyé pour cette campagne.';
        }

        $estimate = $errors ? ['total' => 0, 'eligible' => 0, 'excluded' => []] : $this->audience->estimate($campaign, $user);
        if (! $errors && $estimate['eligible'] === 0) {
            $errors[] = 'Aucun destinataire autorisé : vérifiez la liste de destinataires et les exclusions.';
        }

        return compact('errors', 'warnings', 'estimate');
    }

    /** Programme (ou lance immédiatement) : la version du modèle est figée. */
    public function schedule(Campaign $campaign, User $user, ?\DateTimeInterface $at): void
    {
        if (! $campaign->isEditable()) {
            throw new RuntimeException('Seul un brouillon peut être programmé.');
        }
        $check = $this->preflight($campaign, $user);
        if ($check['errors']) {
            throw new RuntimeException(implode(' ', $check['errors']));
        }

        $campaign->forceFill([
            'template_version_id' => $campaign->template->latestVersion->id,
            'status' => 'programmee',
            'scheduled_at' => $at ?? now(),
            'launched_by' => $user->id,
        ])->save();
        $this->audit->log('campaign.scheduled', $campaign, ['date' => $campaign->scheduled_at->toDateTimeString(), 'version' => $campaign->templateVersion->version]);

        if (! $at || $at <= now()) {
            $this->start($campaign);
        }
    }

    /** Démarrage effectif (appelé par le planificateur) : audience figée puis file d'envoi. */
    public function start(Campaign $campaign): void
    {
        $started = DB::transaction(function () use ($campaign) {
            $fresh = Campaign::whereKey($campaign->id)->lockForUpdate()->first();
            if ($fresh->status !== 'programmee') {
                return false;
            }
            $fresh->forceFill(['status' => 'en_cours', 'started_at' => now()])->save();

            return true;
        });
        if (! $started) {
            return;
        }

        $campaign->refresh();
        $count = $this->audience->freeze($campaign, User::findOrFail($campaign->launched_by));
        $this->audit->log('campaign.started', $campaign, ['destinataires' => $count], $campaign->launched_by);
        SendCampaignBatch::dispatch($campaign->id);
    }

    public function pause(Campaign $campaign): void
    {
        $this->transition($campaign, ['programmee', 'en_cours'], 'en_pause', 'campaign.paused');
    }

    public function resume(Campaign $campaign): void
    {
        $target = $campaign->started_at ? 'en_cours' : 'programmee';
        $this->transition($campaign, ['en_pause'], $target, 'campaign.resumed');
        if ($target === 'en_cours') {
            SendCampaignBatch::dispatch($campaign->id);
        }
    }

    /** Annulation définitive : les destinataires non encore envoyés sont annulés. */
    public function cancel(Campaign $campaign): void
    {
        $this->transition($campaign, Campaign::ACTIVE, 'annulee', 'campaign.cancelled');
        $campaign->forceFill(['cancelled_at' => now()])->save();
        CampaignRecipient::where('campaign_id', $campaign->id)->where('status', 'en_attente')->update(['status' => 'annule']);
    }

    public function duplicate(Campaign $campaign, User $user): Campaign
    {
        $copy = $campaign->replicate(['status', 'template_version_id', 'scheduled_at', 'started_at', 'finished_at', 'cancelled_at', 'last_test_at', 'launched_by']);
        $copy->name = Str::limit('Copie de '.$campaign->name, 150, '');
        $copy->status = 'brouillon';
        $copy->created_by = $user->id;
        $copy->save();
        $this->audit->log('campaign.duplicated', $copy, ['source' => $campaign->id]);

        return $copy;
    }

    /**
     * E-mail de test : contenu réel avec des données d'exemple, objet préfixé [TEST].
     *
     * @param  array<int, string>  $emails
     */
    public function sendTest(MailAccount $account, TemplateVersion $version, array $emails, bool $marketing, User $user, ?string $fromName = null): array
    {
        $data = TemplateRenderer::sampleData($fromName ?: $account->from_name);
        $rendered = $this->renderer->render($version->subject, $version->html, $version->preheader, $data, $marketing, $fromName ?: $account->from_name);
        $results = [];
        foreach ($emails as $email) {
            $result = $this->providers->for($account)->send(new OutgoingMessage(
                $email, null, $account->from_email, $fromName ?: $account->from_name, $account->reply_to,
                '[TEST] '.$rendered['subject'], $rendered['html'], $rendered['text'], 'test-'.Str::uuid(),
            ));
            $results[$email] = $result;
        }
        $this->audit->log('email.test_sent', $version, ['destinataires' => $emails, 'compte' => $account->name], $user->id);

        return $results;
    }

    /** E-mail individuel à un contact : même chaîne d'envoi et mêmes contrôles qu'une campagne. */
    public function sendIndividual(Contact $contact, MailAccount $account, string $subject, string $body, User $user): Campaign
    {
        $template = Template::firstOrCreate(['name' => 'E-mails individuels', 'category' => 'individuel']);
        $html = '<div style="font:15px/1.6 Arial,sans-serif;color:#222">'.nl2br(e($body)).'<p>'.e($user->name).'</p></div>';
        $version = $template->addVersion(['subject' => $subject, 'html' => $html], $user->id);

        $campaign = new Campaign([
            'name' => Str::limit('E-mail à '.$contact->fullName().' — '.$subject, 150, ''),
            'type' => 'individuel',
            'mail_account_id' => $account->id,
            'from_name' => $user->name,
        ]);
        $campaign->template_id = $template->id;
        $campaign->template_version_id = $version->id;
        $campaign->audience = ['contact_ids' => [$contact->id]];
        $campaign->status = 'programmee';
        $campaign->scheduled_at = now();
        $campaign->launched_by = $user->id;
        $campaign->created_by = $user->id;
        $campaign->last_test_at = now();
        $campaign->save();
        $this->audit->log('email.individual', $campaign, ['contact' => $contact->id]);

        $this->start($campaign);

        return $campaign->refresh();
    }

    /** Envoi d'un modèle à un contact (scénarios d'automatisation) : mêmes contrôles qu'une campagne. */
    public function sendTemplateToContact(Contact $contact, MailAccount $account, Template $template, User $user, string $origin): Campaign
    {
        $campaign = new Campaign([
            'name' => Str::limit($origin.' — '.$contact->fullName(), 150, ''),
            'type' => 'individuel',
            'mail_account_id' => $account->id,
        ]);
        $campaign->template_id = $template->id;
        $campaign->template_version_id = $template->latestVersion->id;
        $campaign->audience = ['contact_ids' => [$contact->id]];
        $campaign->status = 'programmee';
        $campaign->scheduled_at = now();
        $campaign->launched_by = $user->id;
        $campaign->created_by = $user->id;
        $campaign->last_test_at = now();
        $campaign->save();
        $this->audit->log('email.automation', $campaign, ['contact' => $contact->id, 'origine' => $origin], $user->id);

        $this->start($campaign);

        return $campaign->refresh();
    }

    private function transition(Campaign $campaign, array $from, string $to, string $action): void
    {
        $updated = Campaign::whereKey($campaign->id)->whereIn('status', $from)->update(['status' => $to]);
        if (! $updated) {
            throw new RuntimeException('Action impossible dans l\'état actuel de la campagne.');
        }
        $campaign->refresh();
        $this->audit->log($action, $campaign, ['statut' => $to]);
    }
}
