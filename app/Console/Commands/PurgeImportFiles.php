<?php

namespace App\Console\Commands;

use App\Models\Import;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Minimisation des données : les fichiers téléversés ne sont conservés que le
 * temps nécessaire. Le rapport ligne à ligne reste disponible en base.
 */
class PurgeImportFiles extends Command
{
    protected $signature = 'pm:purge-import-files';

    protected $description = 'Supprime les fichiers d\'import plus anciens que la durée de conservation configurée';

    public function handle(): int
    {
        $days = (int) config('prospection.import.retention_days');
        $disk = Storage::disk(config('prospection.import.disk'));
        $count = 0;

        Import::whereNotNull('stored_path')
            ->where('created_at', '<', now()->subDays($days))
            ->whereIn('status', ['completed', 'failed', 'cancelled', 'uploaded', 'mapped'])
            ->chunkById(200, function ($imports) use ($disk, &$count) {
                foreach ($imports as $import) {
                    $disk->delete($import->stored_path);
                    $import->update([
                        'stored_path' => null,
                        'status' => in_array($import->status, ['uploaded', 'mapped'], true) ? 'cancelled' : $import->status,
                    ]);
                    $count++;
                }
            });

        $this->info("{$count} fichier(s) d'import supprimé(s).");

        return self::SUCCESS;
    }
}
