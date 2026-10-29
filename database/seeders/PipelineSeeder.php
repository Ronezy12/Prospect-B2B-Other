<?php

namespace Database\Seeders;

use App\Models\Sector;
use App\Services\PipelineService;
use Illuminate\Database\Seeder;

/** Une pipeline avec les étapes par défaut pour chaque secteur racine (idempotent). */
class PipelineSeeder extends Seeder
{
    public function run(PipelineService $pipelines): void
    {
        Sector::whereNull('parent_id')->get()->each(fn (Sector $sector) => $pipelines->ensureForSector($sector));
    }
}
