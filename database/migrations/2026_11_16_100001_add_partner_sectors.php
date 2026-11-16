<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Secteurs couverts par les fiches secteur (ajoutés seulement s'ils manquent).
return new class extends Migration
{
    public const SECTORS = [
        'Bailleurs et gestionnaires locatifs',
        'Architectes et maîtres d’œuvre',
        'Cuisinistes et agenceurs',
        'Plombiers-chauffagistes',
        'Installateurs solaires',
    ];

    public function up(): void
    {
        $position = (int) DB::table('pm_sectors')->whereNull('parent_id')->max('position');
        foreach (self::SECTORS as $name) {
            $slug = Str::slug($name);
            if (! DB::table('pm_sectors')->where('slug', $slug)->exists()) {
                DB::table('pm_sectors')->insert(['name' => $name, 'slug' => $slug, 'position' => ++$position, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        $slugs = array_map(fn ($n) => Str::slug($n), self::SECTORS);
        DB::table('pm_sectors')->whereIn('slug', $slugs)
            ->whereNotExists(fn ($q) => $q->from('pm_companies')->whereColumn('pm_companies.sector_id', 'pm_sectors.id'))
            ->delete();
    }
};
