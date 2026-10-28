<?php

use Illuminate\Support\Facades\Schedule;

// Nécessite la tâche cron système : * * * * * php artisan schedule:run
Schedule::command('pm:purge-import-files')->dailyAt('03:15')->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=720')->daily();
