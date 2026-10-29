<?php

namespace App\Services\Mail;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Construit la liste figée des destinataires d'une campagne. Chaque contact
 * retenu ou exclu est enregistré, avec le motif d'exclusion le cas échéant.
 */
class AudienceBuilder
{
    public const SEGMENT_FILTERS = ['sector_id', 'status', 'source_id', 'owner_id', 'city', 'postal_code', 'tag_id', 'lifecycle'];

    public const CONTACT_FILTERS = ['audience_type', 'legal_basis'];

    public function __construct(private readonly ComplianceGate $gate) {}

    /** Requête des contacts ciblés, dans le périmètre de l'utilisateur. */
    public function query(Campaign $campaign, User $user): Builder
    {
        $query = Contact::query()->visibleTo($user)->whereNotNull('email_normalized')->whereHas('company');

        if ($campaign->type === 'individuel') {
            return $query->whereIn('id', (array) ($campaign->audience['contact_ids'] ?? []));
        }

        if ($campaign->type === 'newsletter') {
            return $query->whereHas('subscriptions', fn ($q) => $q->where('list_id', $campaign->list_id)->where('status', 'abonne'));
        }

        $filters = (array) ($campaign->segment?->filters ?? []);
        $companyFilters = array_filter(array_intersect_key($filters, array_flip(self::SEGMENT_FILTERS)), fn ($v) => filled($v));
        $companyFilters['lifecycle'] ??= $campaign->type === 'operationnel' ? 'all' : 'prospect';
        if ($companyFilters['lifecycle'] === 'all') {
            unset($companyFilters['lifecycle']);
        }
        $query->whereHas('company', fn ($c) => $c->filter($companyFilters));
        $query->filter(array_filter(array_intersect_key($filters, array_flip(self::CONTACT_FILTERS)), fn ($v) => filled($v)));

        $exclusions = (array) ($campaign->exclusions ?? []);
        if ($tagIds = array_filter((array) ($exclusions['tag_ids'] ?? []))) {
            $query->whereDoesntHave('company.tags', fn ($t) => $t->whereIn('pm_tags.id', $tagIds));
        }
        if ($days = (int) ($exclusions['contacted_within_days'] ?? 0)) {
            $query->whereNotIn('email_normalized', CampaignRecipient::where('status', 'accepte')
                ->where('sent_at', '>=', now()->subDays($days))
                ->where('campaign_id', '!=', $campaign->id)
                ->select('email'));
        }

        return $query;
    }

    /** Estimation avant lancement : total ciblé et exclusions par motif. */
    public function estimate(Campaign $campaign, User $user): array
    {
        $total = 0;
        $excluded = [];
        $this->query($campaign, $user)->with('company')->orderBy('id')->chunk(500, function ($contacts) use ($campaign, &$total, &$excluded) {
            foreach ($this->gate->evaluate($campaign, $contacts) as $reason) {
                $total++;
                if ($reason) {
                    $excluded[$reason] = ($excluded[$reason] ?? 0) + 1;
                }
            }
        });

        return ['total' => $total, 'eligible' => $total - array_sum($excluded), 'excluded' => $excluded];
    }

    /** Fige les destinataires (idempotent : un contact n'est inséré qu'une fois). */
    public function freeze(Campaign $campaign, User $user): int
    {
        $seen = CampaignRecipient::where('campaign_id', $campaign->id)->pluck('email')->flip()->all();
        $count = 0;

        $this->query($campaign, $user)->with('company')->orderBy('id')->chunk(500, function ($contacts) use ($campaign, &$seen, &$count) {
            $reasons = $this->gate->evaluate($campaign, $contacts);
            $rows = [];
            foreach ($contacts as $contact) {
                $email = $contact->email_normalized;
                $reason = $reasons[$contact->id];
                if (isset($seen[$email])) {
                    continue; // même adresse déjà présente (deux fiches) : un seul envoi
                }
                $seen[$email] = true;
                $rows[] = [
                    'campaign_id' => $campaign->id,
                    'contact_id' => $contact->id,
                    'email' => $email,
                    'merge_data' => json_encode($this->mergeData($contact)),
                    'status' => $reason ? 'exclu' : 'en_attente',
                    'exclusion_reason' => $reason,
                    'idempotency_key' => CampaignRecipient::keyFor($campaign->id, $email),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if ($rows) {
                DB::table('pm_campaign_recipients')->insertOrIgnore($rows);
                $count += count($rows);
            }
        });

        return $count;
    }

    /** @return array<string, string|null> */
    public function mergeData(Contact $contact): array
    {
        return [
            'prenom' => $contact->first_name,
            'nom' => $contact->last_name,
            'civilite' => $contact->civility,
            'fonction' => $contact->job_title,
            'entreprise' => $contact->company?->name,
            'ville' => $contact->company?->city,
        ];
    }

    public static function unsubscribeUrl(CampaignRecipient $recipient): string
    {
        return URL::signedRoute('unsubscribe.show', ['recipient' => $recipient->id]);
    }
}
