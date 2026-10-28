<?php

namespace App\Jobs;

use App\Models\Import;
use App\Services\Import\ImportProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class ProcessImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 3600;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $importId)
    {
        $this->onQueue('imports');
    }

    /** Un même import n'est jamais traité par deux workers en parallèle. */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('import-'.$this->importId))->releaseAfter(120)->expireAfter(3700)];
    }

    public function handle(ImportProcessor $processor): void
    {
        $import = Import::find($this->importId);
        if (! $import || in_array($import->status, ['completed', 'cancelled'], true)) {
            return;
        }

        $processor->process($import);
    }

    public function failed(?Throwable $exception): void
    {
        Import::whereKey($this->importId)->whereNotIn('status', ['completed', 'cancelled'])->update([
            'status' => 'failed',
            'failure_message' => 'Le traitement a échoué après plusieurs tentatives. Les lignes déjà traitées sont conservées.',
            'finished_at' => now(),
        ]);
    }
}
