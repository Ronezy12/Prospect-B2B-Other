<?php

namespace App\Console\Commands;

use App\Jobs\RunAutomation;
use App\Models\AutomationRun;
use Illuminate\Console\Command;

/** Reprend les exécutions dont le délai est écoulé (et celles interrompues). Chaque minute. */
class RunAutomations extends Command
{
    protected $signature = 'pm:automations-run';

    protected $description = 'Reprend les scénarios d\'automatisation en attente';

    public function handle(): int
    {
        // Exécution bloquée « en cours » (worker interrompu) : reprise, sans rejouer les actions déjà faites.
        AutomationRun::where('status', 'en_cours')->where('updated_at', '<', now()->subMinutes(10))
            ->update(['status' => 'en_attente', 'resume_at' => now()]);

        AutomationRun::where('status', 'en_attente')->where('resume_at', '<=', now())->orderBy('resume_at')->limit(500)
            ->pluck('id')->each(fn (int $id) => RunAutomation::dispatch($id));

        return self::SUCCESS;
    }
}
