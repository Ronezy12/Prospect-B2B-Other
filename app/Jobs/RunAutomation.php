<?php

namespace App\Jobs;

use App\Models\AutomationRun;
use App\Services\Automation\AutomationRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunAutomation implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $runId)
    {
        $this->onQueue('automations');
    }

    public function handle(AutomationRunner $runner): void
    {
        if ($run = AutomationRun::find($this->runId)) {
            $runner->process($run);
        }
    }
}
