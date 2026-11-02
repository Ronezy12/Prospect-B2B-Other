<?php

namespace App\Services\Privacy;

use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\CampaignRecipient;
use App\Models\Company;
use App\Models\Contact;
use App\Models\SocialOpposition;
use App\Models\SocialProfile;
use App\Models\Subscription;
use App\Models\Suppression;
use App\Models\Task;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SuppressionService;
use App\Support\Normalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Droits des personnes : retrouver, exporter et effacer tout ce qui concerne une
 * adresse e-mail. L'effacement garde uniquement l'empreinte HMAC de l'adresse dans
 * la liste d'opposition, pour qu'elle ne soit jamais réintroduite (import, scénario…).
 */
class PrivacyService
{
    public const ERASED = 'Personne effacée';

    public function __construct(
        private readonly SuppressionService $suppressions,
        private readonly AuditLogger $audit,
    ) {}

    /** @return Collection<int, Contact> */
    public function contacts(string $email): Collection
    {
        $normalized = Normalizer::email($email);

        return $normalized ? Contact::withTrashed()->with(['company' => fn ($q) => $q->withTrashed()])->where('email_normalized', $normalized)->get() : collect();
    }

    /** Tout ce que l'outil sait sur cette adresse, sous une forme lisible et exportable. */
    public function report(string $email): array
    {
        $normalized = Normalizer::email($email);
        $contacts = $this->contacts($email);
        $contactIds = $contacts->pluck('id');
        $recipients = CampaignRecipient::with('campaign:id,name,type')->where('email', $normalized)->orderBy('id')->get();

        return [
            'adresse' => $normalized,
            'genere_le' => now()->toIso8601String(),
            'contacts' => $contacts->map(fn (Contact $c) => [
                'identifiant' => $c->id,
                'civilite' => $c->civility,
                'prenom' => $c->first_name,
                'nom' => $c->last_name,
                'fonction' => $c->job_title,
                'email' => $c->email,
                'telephone' => $c->phone,
                'mobile' => $c->mobile,
                'linkedin' => $c->linkedin_url,
                'entreprise' => $c->company?->name,
                'type' => $c->audience_type,
                'base_legale' => config('prospection.legal_bases')[$c->legal_basis] ?? $c->legal_basis,
                'source' => $c->source?->name,
                'date_de_collecte' => $c->collected_at?->toDateString(),
                'note_de_collecte' => $c->collection_note,
                'consentement_suivi' => $c->tracking_consent_at?->toIso8601String(),
                'cree_le' => $c->created_at?->toIso8601String(),
                'supprime_le' => $c->deleted_at?->toIso8601String(),
            ])->values()->all(),
            'emails_recus' => $recipients->map(fn (CampaignRecipient $r) => [
                'envoi' => $r->campaign?->name,
                'statut' => config('prospection.recipient_statuses')[$r->status] ?? $r->status,
                'envoye_le' => $r->sent_at?->toIso8601String(),
                'livraison' => $r->delivery_status,
                'ouverture_detectee' => $r->opened_at?->toIso8601String(),
                'clic' => $r->clicked_at?->toIso8601String(),
                'reponse' => $r->replied_at?->toIso8601String(),
                'desinscription' => $r->unsubscribed_at?->toIso8601String(),
            ])->all(),
            'abonnements' => Subscription::with('list:id,name')->whereIn('contact_id', $contactIds)->get()->map(fn ($s) => [
                'liste' => $s->list?->name, 'statut' => $s->status, 'consentement' => $s->consent_source, 'le' => $s->consented_at?->toIso8601String(), 'desabonne_le' => $s->unsubscribed_at?->toIso8601String(),
            ])->all(),
            'reseaux_sociaux' => SocialProfile::whereIn('contact_id', $contactIds)->get()->map(fn ($p) => ['reseau' => $p->platformLabel(), 'profil' => $p->url, 'statut' => $p->statusLabel()])->all(),
            'taches' => Task::whereIn('contact_id', $contactIds)->get()->map(fn ($t) => ['titre' => $t->title, 'echeance' => $t->due_at?->toIso8601String(), 'statut' => $t->status])->all(),
            'historique' => Activity::where('subject_type', (new Company)->getMorphClass())
                ->whereIn('subject_id', $contacts->pluck('company_id')->filter())
                ->where(fn ($q) => $q->where('body', 'like', '%'.$normalized.'%')->orWhere(fn ($w) => $contacts->each(fn ($c) => $c->last_name ? $w->orWhere('body', 'like', '%'.$c->last_name.'%') : null)))
                ->orderBy('occurred_at')->get()->map(fn ($a) => ['le' => $a->occurred_at?->toIso8601String(), 'type' => $a->type, 'texte' => $a->body])->all(),
            'opposition' => Suppression::where('email_hash', $this->suppressions->hash($normalized))->get()->map(fn ($s) => [
                'perimetre' => config('prospection.suppression_scopes')[$s->scope] ?? $s->scope, 'motif' => $s->reason, 'origine' => $s->origin, 'le' => $s->created_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * Effacement : données personnelles anonymisées partout, fiches contact supprimées,
     * opposition totale conservée sous forme d'empreinte.
     *
     * @return array<string, int> nombre d'éléments traités par catégorie
     */
    public function erase(string $email, User $user, ?string $note = null): array
    {
        $normalized = Normalizer::email($email);
        $contacts = $this->contacts($email);
        $ids = $contacts->pluck('id');
        $done = [];

        DB::transaction(function () use ($normalized, $contacts, $ids, $user, $note, &$done) {
            // Ne plus jamais réintroduire l'adresse : seule son empreinte est gardée.
            $this->suppressions->suppress($normalized, 'all', 'effacement', 'droit_effacement', $note ? mb_substr($note, 0, 255) : 'Demande d\'effacement');

            $profiles = SocialProfile::whereIn('contact_id', $ids)->get();
            $done['profils_sociaux'] = $profiles->count();
            foreach ($profiles as $profile) {
                SocialOpposition::firstOrCreate(['fingerprint' => $profile->fingerprint], ['platform' => $profile->platform, 'note' => 'Effacement', 'created_by' => $user->id]);
                $profile->delete();
            }

            $done['abonnements'] = Subscription::whereIn('contact_id', $ids)->delete();

            $recipients = CampaignRecipient::where('email', $normalized)->get();
            foreach ($recipients as $r) {
                $r->forceFill(['email' => 'efface-'.$r->id.'@anonyme.invalid', 'merge_data' => null, 'last_error' => null])->save();
            }
            DB::table('pm_email_events')->whereIn('recipient_id', $recipients->pluck('id'))->update(['payload' => null]);
            $done['emails'] = $recipients->count();

            foreach ($contacts as $contact) {
                foreach (array_filter([$normalized, $contact->last_name && mb_strlen($contact->last_name) > 2 ? $contact->fullName() : null, $contact->phone, $contact->mobile]) as $needle) {
                    Activity::where('subject_type', (new Company)->getMorphClass())->where('subject_id', $contact->company_id)
                        ->where('body', 'like', '%'.$needle.'%')
                        ->update(['body' => DB::raw('REPLACE(body, '.DB::getPdo()->quote($needle).', '.DB::getPdo()->quote('[donnée effacée]').')')]);
                }
                Task::where('contact_id', $contact->id)->update(['contact_id' => null]);
                $contact->forceFill([
                    'civility' => null, 'first_name' => null, 'last_name' => self::ERASED, 'job_title' => null,
                    'email' => null, 'phone' => null, 'mobile' => null, 'linkedin_url' => null,
                    'collection_note' => null, 'tracking_consent_at' => null, 'tracking_consent_source' => null,
                ]);
                $contact->email_normalized = null;
                $contact->saveQuietly();
                if (! $contact->trashed()) {
                    $contact->delete();
                }
                // Les anciennes valeurs consignées dans le journal d'audit sont masquées.
                AuditLog::where('subject_type', $contact->getMorphClass())->where('subject_id', $contact->id)->update(['changes' => json_encode(['données' => 'effacées'])]);
            }
            $done['contacts'] = $contacts->count();

            $this->audit->log('privacy.erased', null, ['empreinte' => substr($this->suppressions->hash($normalized), 0, 12), 'éléments' => $done], $user->id);
        });

        return $done;
    }
}
