<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Services\Mail\CampaignSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Envoie les lots d'une campagne pendant au plus ~50 secondes, puis rend la main.
 * Le planificateur (pm:campaigns-run, chaque minute) relance le travail tant qu'il
 * reste des destinataires : l'envoi reprend seul après un redémarrage.
 */
class SendCampaignBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    private const TIME_BUDGET = 50;

    public function __construct(public readonly int $campaignId)
    {
        $this->onQueue('emails');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('campaign-'.$this->campaignId))->dontRelease()->expireAfter(180)];
    }

    public function handle(CampaignSender $sender): void
    {
        $campaign = Campaign::find($this->campaignId);
        if (! $campaign) {
            return;
        }

        $start = microtime(true);
        do {
            $wait = $sender->processBatch($campaign);
        } while ($wait === 0 && microtime(true) - $start < self::TIME_BUDGET);

        if ($wait === 0) {
            self::dispatch($this->campaignId)->delay(now()->addSeconds(5));
        }
    }
}
