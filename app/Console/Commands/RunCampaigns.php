<?php

namespace App\Console\Commands;

use App\Jobs\SendCampaignBatch;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Services\Mail\CampaignSender;
use App\Services\Mail\CampaignService;
use Illuminate\Console\Command;
use Throwable;

/** Planificateur des envois : à exécuter chaque minute (cron / schedule:run). */
class RunCampaigns extends Command
{
    protected $signature = 'pm:campaigns-run';

    protected $description = 'Démarre les campagnes programmées échues et relance les envois en cours';

    public function handle(CampaignService $campaigns, CampaignSender $sender): int
    {
        $stale = $sender->flagStale();
        if ($stale) {
            $this->warn("{$stale} envoi(s) interrompu(s) marqué(s) « à vérifier ».");
        }

        Campaign::where('status', 'programmee')->where('scheduled_at', '<=', now())->orderBy('scheduled_at')->get()
            ->each(function (Campaign $campaign) use ($campaigns) {
                try {
                    $campaigns->start($campaign);
                    $this->info("Campagne #{$campaign->id} démarrée.");
                } catch (Throwable $e) {
                    report($e);
                    $this->error("Campagne #{$campaign->id} : démarrage impossible.");
                }
            });

        Campaign::where('status', 'en_cours')->pluck('id')->each(function (int $id) use ($sender) {
            $ready = CampaignRecipient::where('campaign_id', $id)->where('status', 'en_attente')
                ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))->exists();
            if ($ready) {
                SendCampaignBatch::dispatch($id);
            } else {
                $sender->finishIfDone(Campaign::find($id));
            }
        });

        return self::SUCCESS;
    }
}
