<?php

namespace App\Support;

use App\Models\Sector;
use Illuminate\Support\Str;

/**
 * Fiches secteur : ce qu'on propose à chaque type de partenaire, comment le qualifier,
 * et 10 modèles d'e-mails par secteur (du premier contact au suivi mensuel).
 * Le contenu est dans resources/playbooks/*.php (un fichier par secteur).
 */
final class Playbooks
{
    /** Les 10 étapes, identiques pour chaque secteur. */
    public const STEPS = [
        1 => ['label' => 'Premier contact', 'when' => 'Jour 1', 'mass' => true],
        2 => ['label' => 'Relance', 'when' => '3 à 4 jours après, sans réponse', 'mass' => true],
        3 => ['label' => 'Dernière relance', 'when' => '7 à 10 jours après, sans réponse', 'mass' => true],
        4 => ['label' => 'Confirmation du rendez-vous', 'when' => 'Dès qu’un rendez-vous est fixé', 'mass' => false],
        5 => ['label' => 'Compte rendu du rendez-vous', 'when' => 'Le jour même du rendez-vous', 'mass' => false],
        6 => ['label' => 'Proposition de partenariat', 'when' => '2 à 5 jours après le rendez-vous', 'mass' => false],
        7 => ['label' => 'Relance de la proposition', 'when' => '5 jours après la proposition', 'mass' => false],
        8 => ['label' => 'Premier chantier réussi', 'when' => 'Après le premier chantier', 'mass' => false],
        9 => ['label' => 'Bienvenue au partenaire', 'when' => 'À la signature', 'mass' => false],
        10 => ['label' => 'Suivi mensuel', 'when' => 'Chaque mois', 'mass' => false],
    ];

    /** Texte à remplacer avant l'envoi : l'envoi est refusé tant qu'il en reste. */
    public const PLACEHOLDER = '[à compléter';

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $cache = null;

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $items = [];
        foreach (glob(resource_path('playbooks/*.php')) ?: [] as $file) {
            $data = require $file;
            foreach ($data['emails'] as $i => $email) {
                $step = $i + 1;
                $data['emails'][$i] += ['step' => $step, 'key' => $data['slug'].'-'.$step] + self::STEPS[$step];
            }
            $items[$data['slug']] = $data;
        }

        return self::$cache = $items;
    }

    public static function find(?string $slug): ?array
    {
        return $slug ? (self::all()[$slug] ?? null) : null;
    }

    /** Modèle d'e-mail par clé « secteur-étape » (ex. concessionnaires-1). */
    public static function email(?string $key): ?array
    {
        if (! $key || ! preg_match('/^([a-z0-9-]+)-(\d{1,2})$/', $key, $m)) {
            return null;
        }

        return self::find($m[1])['emails'][(int) $m[2] - 1] ?? null;
    }

    /** Fiche correspondant au secteur d'une entreprise (ou à son secteur parent). */
    public static function forSector(?Sector $sector): ?array
    {
        if (! $sector) {
            return null;
        }
        $slugs = array_filter([$sector->slug, $sector->parent?->slug]);
        foreach (self::all() as $playbook) {
            foreach ($playbook['sectors'] as $name) {
                if (in_array(Str::slug($name), $slugs, true)) {
                    return $playbook;
                }
            }
        }

        return null;
    }

    /**
     * Modèles regroupés par secteur pour les listes déroulantes.
     *
     * @return array<string, array<string, string>> nom du secteur => [clé => libellé]
     */
    public static function options(bool $massOnly = false): array
    {
        $out = [];
        foreach (self::all() as $playbook) {
            foreach ($playbook['emails'] as $email) {
                if (! $massOnly || $email['mass']) {
                    $out[$playbook['name']][$email['key']] = $email['step'].'. '.$email['label'];
                }
            }
        }

        return $out;
    }

    public static function hasPlaceholder(string ...$texts): bool
    {
        foreach ($texts as $text) {
            if (mb_stripos($text, self::PLACEHOLDER) !== false) {
                return true;
            }
        }

        return false;
    }
}
