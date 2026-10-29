<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Données de référence uniquement : aucune donnée fictive de prospect.
     * Le premier administrateur se crée avec `php artisan pm:create-admin`.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SectorSeeder::class,
            PipelineSeeder::class,
        ]);
    }
}
