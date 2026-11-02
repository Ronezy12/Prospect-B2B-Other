<?php

namespace App\Console\Commands;

use App\Services\Privacy\RetentionService;
use Illuminate\Console\Command;

class ApplyRetention extends Command
{
    protected $signature = 'pm:retention {--simulation : Affiche ce qui serait traité, sans rien modifier}';

    protected $description = 'Applique les durées de conservation (prospects inactifs, suivi des e-mails, journaux)';

    public function handle(RetentionService $retention): int
    {
        $report = $retention->run((bool) $this->option('simulation'));
        $this->table(['Élément', 'Nombre'], collect($report)->map(fn ($n, $k) => [str_replace('_', ' ', $k), $n])->values()->all());
        $this->info($this->option('simulation') ? 'Simulation : rien n\'a été modifié.' : 'Durées de conservation appliquées.');

        return self::SUCCESS;
    }
}
