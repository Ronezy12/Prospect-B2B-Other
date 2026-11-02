<?php

namespace App\Services\Privacy;

use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\CampaignRecipient;
use App\Models\Company;
use App\Models\Setting;
use App\Models\UserNotification;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Durées de conservation appliquées automatiquement (chaque nuit) :
 *  - prospect sans aucun échange depuis N mois : interlocuteurs anonymisés, fiche archivée ;
 *  - ouvertures et clics : effacés après N mois ;
 *  - notifications lues et journal d'audit : purgés après leur durée.
 * Les clients et les prospects ayant une affaire en cours ne sont jamais concernés.
 */
class RetentionService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return array<string, int> */
    public function run(bool $dryRun = false): array
    {
        $rules = config('prospection.retention');
        $prospectCutoff = now()->subMonths((int) $rules['inactive_prospect_months']);
        $morph = (new Company)->getMorphClass();

        $inactive = Company::query()->prospects()
            ->where('updated_at', '<', $prospectCutoff)
            ->whereDoesntHave('opportunities', fn ($q) => $q->whereNull('closed_at'))
            ->whereNotExists(fn ($q) => $q->from('pm_activities')->whereColumn('pm_activities.subject_id', 'pm_companies.id')
                ->where('pm_activities.subject_type', $morph)->where('pm_activities.occurred_at', '>=', $prospectCutoff))
            ->whereNotExists(fn ($q) => $q->from('pm_campaign_recipients')->join('pm_contacts', 'pm_contacts.id', '=', 'pm_campaign_recipients.contact_id')
                ->whereColumn('pm_contacts.company_id', 'pm_companies.id')->where(fn ($w) => $w->where('pm_campaign_recipients.replied_at', '>=', $prospectCutoff)->orWhere('pm_campaign_recipients.clicked_at', '>=', $prospectCutoff)));

        $trackingCutoff = now()->subMonths((int) $rules['tracking_months']);
        $tracking = CampaignRecipient::where('sent_at', '<', $trackingCutoff)->where(fn ($q) => $q->whereNotNull('opened_at')->orWhereNotNull('clicked_at'));
        $events = DB::table('pm_email_events')->whereIn('type', ['ouverture', 'clic'])->where('occurred_at', '<', $trackingCutoff);
        $notifications = UserNotification::whereNotNull('read_at')->where('created_at', '<', now()->subMonths((int) $rules['notifications_months']));
        $audit = AuditLog::where('created_at', '<', now()->subMonths((int) $rules['audit_months']));

        $report = [
            'prospects_inactifs' => (clone $inactive)->count(),
            'suivis_effaces' => (clone $tracking)->count(),
            'evenements_supprimes' => (clone $events)->count(),
            'notifications_supprimees' => (clone $notifications)->count(),
            'journal_purge' => (clone $audit)->count(),
        ];
        if ($dryRun) {
            return $report;
        }

        DB::transaction(function () use ($inactive, $tracking, $events, $notifications, $audit, $report) {
            $inactive->with('contacts')->chunkById(200, function ($companies) {
                foreach ($companies as $company) {
                    foreach ($company->contacts as $contact) {
                        $contact->forceFill([
                            'civility' => null, 'first_name' => null, 'last_name' => 'Contact anonymisé', 'job_title' => null,
                            'email' => null, 'phone' => null, 'mobile' => null, 'linkedin_url' => null, 'collection_note' => null,
                            'tracking_consent_at' => null, 'tracking_consent_source' => null,
                        ]);
                        $contact->email_normalized = null;
                        $contact->saveQuietly();
                        $contact->delete();
                    }
                    Activity::create(['subject_type' => $company->getMorphClass(), 'subject_id' => $company->id, 'type' => 'systeme', 'body' => 'Fiche archivée automatiquement : aucun échange pendant la durée de conservation.', 'occurred_at' => now()]);
                    $company->delete();
                }
            });
            $tracking->update(['opened_at' => null, 'clicked_at' => null, 'open_count' => 0, 'click_count' => 0]);
            $events->delete();
            $notifications->delete();
            $audit->delete();
            $this->audit->log('retention.applied', null, $report);
        });
        Setting::put('retention.last_run', ['le' => now()->toIso8601String()] + $report);

        return $report;
    }
}
