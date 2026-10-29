<?php

namespace App\Services\Social;

use App\Models\Company;
use App\Models\Contact;
use App\Models\SocialInteraction;
use App\Models\SocialOpposition;
use App\Models\SocialProfile;
use App\Models\SocialTemplate;
use App\Models\Task;
use App\Models\User;
use App\Services\ActivityRecorder;
use App\Services\AuditLogger;
use App\Services\Automation\AutomationEngine;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Point d'entrée unique du suivi réseaux sociaux : normalisation des adresses de profil,
 * contrôle de la liste d'opposition et journal des actions. Toute création de profil
 * (saisie, fiche contact, import) passe par attach(), qui refuse un profil opposé.
 */
class SocialProfileService
{
    public function __construct(
        private readonly ActivityRecorder $activities,
        private readonly AuditLogger $audit,
    ) {}

    /** Plateforme déduite de l'adresse (null si l'adresse n'est pas une URL https valide). */
    public function detectPlatform(string $url): ?string
    {
        $host = $this->host($url);
        if (! $host) {
            return null;
        }
        foreach (config('prospection.social.platforms') as $key => $platform) {
            foreach ($platform['hosts'] as $candidate) {
                if ($host === $candidate || str_ends_with($host, '.'.$candidate)) {
                    return $key;
                }
            }
        }

        return 'autre';
    }

    /**
     * Adresse canonique d'un profil : https, domaine principal de la plateforme, sans
     * paramètres de suivi. Null si l'adresse ne correspond pas à la plateforme indiquée.
     */
    public function normalizeUrl(string $platform, ?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || mb_strlen($url) > 255) {
            return null;
        }
        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $url = 'https://'.$url;
        }
        $parts = parse_url($url);
        if (! $parts || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $host = $this->host($url);
        $detected = $host ? $this->detectPlatform($url) : null;
        if (! $detected || ($platform !== 'autre' && $detected !== $platform) || ($platform === 'autre' && $detected !== 'autre')) {
            return null;
        }

        $path = rtrim(preg_replace('#/+#', '/', $parts['path'] ?? ''), '/');
        if ($path === '' || ! preg_match('#^[\p{L}\p{N}/._~%@+-]+$#u', $path)) {
            return null;
        }

        if ($platform === 'autre') {
            return 'https://'.$host.$path;
        }

        // Facebook : les profils sans nom d'utilisateur sont de la forme profile.php?id=123.
        $query = '';
        if ($platform === 'facebook' && strtolower($path) === '/profile.php') {
            parse_str($parts['query'] ?? '', $params);
            if (! preg_match('/^\d{5,25}$/', (string) ($params['id'] ?? ''))) {
                return null;
            }
            $query = '?id='.$params['id'];
        }

        $primary = config('prospection.social.platforms.'.$platform.'.hosts')[0];
        $canonicalHost = $platform === 'x' ? $primary : 'www.'.$primary;

        return 'https://'.$canonicalHost.mb_strtolower($path).$query;
    }

    /** Empreinte HMAC de l'adresse canonique (même clé que la liste d'opposition e-mail). */
    public function fingerprint(string $canonicalUrl): string
    {
        $key = config('prospection.suppression_key') ?: config('app.key');

        return hash_hmac('sha256', 'social|'.mb_strtolower($canonicalUrl), (string) $key);
    }

    public function isOpposed(string $canonicalUrl): bool
    {
        return SocialOpposition::where('fingerprint', $this->fingerprint($canonicalUrl))->exists();
    }

    /**
     * Rattache un profil à une entreprise.
     *
     * @return array{0: ?SocialProfile, 1: string} created | existing | opposed | invalid
     */
    public function attach(Company $company, string $platform, ?string $url, ?Contact $contact, ?User $user, string $origin = 'saisie'): array
    {
        $canonical = $this->normalizeUrl($platform, $url);
        if (! $canonical) {
            return [null, 'invalid'];
        }
        if ($this->isOpposed($canonical)) {
            return [null, 'opposed'];
        }
        $fingerprint = $this->fingerprint($canonical);
        if ($existing = SocialProfile::where('company_id', $company->id)->where('fingerprint', $fingerprint)->first()) {
            if ($contact && ! $existing->contact_id) {
                $existing->forceFill(['contact_id' => $contact->id])->save();
            }

            return [$existing, 'existing'];
        }

        $profile = new SocialProfile(['platform' => $platform, 'url' => $canonical]);
        $profile->company_id = $company->id;
        $profile->contact_id = $contact?->id;
        $profile->fingerprint = $fingerprint;
        $profile->created_by = $user?->id;
        $profile->save();
        $this->activities->record($company, 'reseau_social', "Profil {$profile->platformLabel()} ajouté ({$origin}) : {$canonical}.", ['social_profile_id' => $profile->id], $user?->id);
        $this->audit->log('social_profile.created', $profile, ['plateforme' => $platform, 'origine' => $origin], $user?->id);

        return [$profile, 'created'];
    }

    /** Profil LinkedIn saisi sur la fiche contact ou importé : même contrôle que la saisie directe. */
    public function syncFromContact(Contact $contact, ?User $user, string $origin = 'fiche contact'): string
    {
        if (! $contact->linkedin_url || ! $contact->company_id || ! $contact->company) {
            return 'none';
        }

        return $this->attach($contact->company, 'linkedin', $contact->linkedin_url, $contact, $user, $origin)[1];
    }

    /**
     * Enregistre une action réalisée sur la plateforme (ou reçue via l'API officielle).
     * Le statut proposé est appliqué sauf si l'utilisateur en choisit un autre.
     */
    public function logInteraction(SocialProfile $profile, string $type, ?string $body, CarbonInterface $at, ?string $status, ?CarbonInterface $nextActionAt, ?User $user, bool $createTask = false, string $origin = 'manuel'): SocialInteraction
    {
        $interaction = DB::transaction(function () use ($profile, $type, $body, $at, $status, $nextActionAt, $user, $createTask, $origin) {
            $interaction = new SocialInteraction(['type' => $type, 'body' => $body ? mb_substr($body, 0, 5000) : null, 'occurred_at' => $at]);
            $interaction->profile_id = $profile->id;
            $interaction->company_id = $profile->company_id;
            $interaction->user_id = $user?->id;
            $interaction->origin = $origin;
            $interaction->save();

            $status ??= config('prospection.social.status_after')[$type] ?? null;
            $previous = $profile->status;
            $updates = ['last_interaction_at' => $profile->last_interaction_at && $profile->last_interaction_at->gt($at) ? $profile->last_interaction_at : $at];
            // Une opposition n'est jamais levée par une simple saisie d'action.
            if ($status && ! $profile->isOpposed()) {
                $updates['status'] = $status;
            }
            $updates['next_action_at'] = $nextActionAt;
            $profile->forceFill($updates)->save();

            $company = $profile->company;
            $label = config('prospection.social.interaction_types')[$type] ?? $type;
            $text = "{$profile->platformLabel()} — {$label}".($profile->contact ? ' ('.$profile->contact->fullName().')' : '').($body ? ' : '.mb_substr($body, 0, 300) : '.');
            $this->activities->record($company, 'reseau_social', $text, ['social_profile_id' => $profile->id, 'social_interaction_id' => $interaction->id, 'origine' => $origin], $user?->id);

            if ($createTask && $nextActionAt && ! $profile->isOpposed()) {
                $task = new Task(['title' => mb_substr("Relance {$profile->platformLabel()} — {$company->name}", 0, 190), 'type' => 'reseau_social', 'priority' => 'normale', 'due_at' => $nextActionAt, 'description' => "Profil : {$profile->url}"]);
                $task->company_id = $company->id;
                $task->contact_id = $profile->contact_id;
                $task->assignee_id = $company->owner_id ?? $user?->id;
                $task->created_by = $user?->id;
                $task->save();
            }

            if ($profile->status === 'ne_plus_contacter' && $previous !== 'ne_plus_contacter') {
                $this->registerOpposition($profile, 'Statut « ne plus contacter » saisi', $user);
            }

            return $interaction;
        });

        if ($type === 'message_recu') {
            app(AutomationEngine::class)->fire('social.reply_received', $profile->company, 'social:'.$interaction->id, ['social_profile_id' => $profile->id, 'plateforme' => $profile->platform]);
        }

        return $interaction;
    }

    /** La personne a demandé à ne plus être sollicitée sur ce réseau : opposition durable. */
    public function oppose(SocialProfile $profile, ?string $note, User $user): void
    {
        DB::transaction(function () use ($profile, $note, $user) {
            $profile->forceFill(['status' => 'ne_plus_contacter', 'next_action_at' => null])->save();
            $this->registerOpposition($profile, $note, $user);
            $this->activities->record($profile->company, 'reseau_social', "Opposition enregistrée sur {$profile->platformLabel()} : ne plus contacter ce profil.".($note ? " ({$note})" : ''), ['social_profile_id' => $profile->id], $user->id);
        });
    }

    /**
     * Texte prêt à copier : variables remplacées, sans mise en forme. Le message est
     * envoyé par le commercial lui-même sur la plateforme (aucun envoi automatisé).
     */
    public function render(SocialTemplate $template, SocialProfile $profile, User $user): string
    {
        $contact = $profile->contact ?? $profile->company->contacts()->orderByDesc('is_primary')->first();
        $data = [
            'prenom' => $contact?->first_name,
            'nom' => $contact?->last_name,
            'civilite' => $contact?->civility,
            'fonction' => $contact?->job_title,
            'entreprise' => $profile->company->name,
            'ville' => $profile->company->city,
            'expediteur' => $user->name,
        ];

        $text = preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/i', fn ($m) => (string) ($data[strtolower($m[1])] ?? ''), $template->body);

        return trim(preg_replace('/[ \t]{2,}/', ' ', preg_replace('/ +([,.])/', '$1', $text)));
    }

    private function registerOpposition(SocialProfile $profile, ?string $note, ?User $user): void
    {
        $opposition = SocialOpposition::firstOrCreate(
            ['fingerprint' => $profile->fingerprint],
            ['platform' => $profile->platform, 'note' => $note ? mb_substr($note, 0, 255) : null, 'created_by' => $user?->id],
        );
        if ($opposition->wasRecentlyCreated) {
            $this->audit->log('social_opposition.created', $profile, ['plateforme' => $profile->platform], $user?->id);
        }
        // Les relances réseaux sociaux encore ouvertes pour ce prospect n'ont plus lieu d'être.
        Task::where('company_id', $profile->company_id)->where('type', 'reseau_social')->where('status', 'a_faire')
            ->update(['status' => 'annule', 'completed_at' => now()]);
    }

    private function host(string $url): ?string
    {
        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $url = 'https://'.$url;
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || ! preg_match('/^([a-z0-9-]+\.)+[a-z]{2,}$/i', $host)) {
            return null;
        }

        return preg_replace('/^(www|m|mobile)\./', '', mb_strtolower($host));
    }
}
