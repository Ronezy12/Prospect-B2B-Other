<?php

namespace Database\Seeders;

use App\Models\Sector;
use App\Models\Source;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SectorSeeder extends Seeder
{
    public const SECTORS = [
        'Agences immobilières',
        'Concessionnaires automobiles',
        'Restaurants et commerces',
        'Syndics de copropriété',
        'Bailleurs et gestionnaires locatifs',
        'Architectes et maîtres d’œuvre',
        'Cuisinistes et agenceurs',
        'Plombiers-chauffagistes',
        'Installateurs solaires',
        'Professionnels et entreprises tous secteurs',
        'Autres',
    ];

    public const SOURCES = [
        'Site web', 'Recommandation', 'Salon / événement', 'Annuaire professionnel',
        'LinkedIn', 'Appel entrant', 'Prospection terrain', 'Import fichier', 'Autre',
    ];

    public function run(): void
    {
        foreach (self::SECTORS as $position => $name) {
            Sector::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'position' => $position]);
        }

        foreach (self::SOURCES as $name) {
            Source::firstOrCreate(['name' => $name]);
        }
    }
}
