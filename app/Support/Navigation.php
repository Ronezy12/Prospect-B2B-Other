<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Menu simplifié : quelques rubriques dans la barre latérale, et des onglets en haut
 * de page pour les écrans d'une même rubrique. Chaque entrée n'apparaît que si
 * l'utilisateur a la permission correspondante (le contrôle réel reste côté serveur).
 */
final class Navigation
{
    /** @return array<int, array{key: string, label: string, icon: string, route: string, active: array<int, string>, tabs: array<int, array{label: string, route: string, params?: array, active: array<int, string>}>}> */
    public static function sections(User $user): array
    {
        $sections = [
            [
                'key' => 'accueil', 'label' => 'Accueil', 'icon' => 'house', 'can' => 'prospects.view',
                'active' => ['pm.dashboard', 'pm.notifications.*'],
                'tabs' => [],
            ],
            [
                'key' => 'prospects', 'label' => 'Prospects', 'icon' => 'building-2', 'can' => 'prospects.view',
                'active' => ['pm.companies.*', 'pm.contacts.index', 'pm.contacts.create', 'pm.contacts.edit', 'pm.imports.*'],
                'tabs' => [
                    ['label' => 'Entreprises', 'route' => 'pm.companies.index', 'active' => ['pm.companies.*']],
                    ['label' => 'Contacts', 'route' => 'pm.contacts.index', 'active' => ['pm.contacts.index', 'pm.contacts.create', 'pm.contacts.edit']],
                    ['label' => 'Imports', 'route' => 'pm.imports.index', 'can' => 'prospects.import', 'active' => ['pm.imports.*']],
                ],
            ],
            [
                'key' => 'pipeline', 'label' => 'Pipeline', 'icon' => 'kanban', 'can' => 'prospects.view',
                'active' => ['pm.pipelines.*', 'pm.opportunities.*'],
                'tabs' => [
                    ['label' => 'Tableau', 'route' => 'pm.pipelines.show', 'active' => ['pm.pipelines.show']],
                    ['label' => 'Vue globale', 'route' => 'pm.pipelines.overview', 'active' => ['pm.pipelines.overview']],
                    ['label' => 'Liste des opportunités', 'route' => 'pm.opportunities.index', 'active' => ['pm.opportunities.*']],
                ],
            ],
            [
                'key' => 'taches', 'label' => 'Tâches', 'icon' => 'list-checks', 'can' => 'prospects.view',
                'active' => ['pm.tasks.*'],
                'tabs' => [],
            ],
            [
                'key' => 'emails', 'label' => 'E-mails', 'icon' => 'mail', 'can' => 'campaigns.view',
                'active' => ['pm.campaigns.*', 'pm.newsletters.*', 'pm.templates.*', 'pm.segments.*'],
                'tabs' => [
                    ['label' => 'Campagnes', 'route' => 'pm.campaigns.index', 'active' => ['pm.campaigns.*']],
                    ['label' => 'Newsletters', 'route' => 'pm.newsletters.index', 'active' => ['pm.newsletters.*']],
                    ['label' => 'Modèles', 'route' => 'pm.templates.index', 'active' => ['pm.templates.*']],
                    ['label' => 'Segments', 'route' => 'pm.segments.index', 'active' => ['pm.segments.*']],
                ],
            ],
            [
                'key' => 'social', 'label' => 'Réseaux sociaux', 'icon' => 'share-2', 'can' => ['social.use', 'social.inbox'],
                'active' => ['pm.social.*'],
                'tabs' => [
                    ['label' => 'Suivi des profils', 'route' => 'pm.social.index', 'can' => 'social.use', 'active' => ['pm.social.index', 'pm.social.show']],
                    ['label' => 'Messages de la Page', 'route' => 'pm.social.inbox.index', 'can' => 'social.inbox', 'active' => ['pm.social.inbox.*']],
                    ['label' => 'Modèles de messages', 'route' => 'pm.social.templates.index', 'can' => 'social.templates', 'active' => ['pm.social.templates.*']],
                ],
            ],
            [
                'key' => 'automatisations', 'label' => 'Automatisations', 'icon' => 'sparkles', 'can' => 'automations.manage',
                'active' => ['pm.automations.*'],
                'tabs' => [],
            ],
            [
                'key' => 'statistiques', 'label' => 'Statistiques', 'icon' => 'chart-column', 'can' => 'reports.view',
                'active' => ['pm.reports.*'],
                'tabs' => [],
            ],
            [
                'key' => 'parametres', 'label' => 'Paramètres', 'icon' => 'settings', 'can' => ['users.manage', 'pipelines.manage', 'settings.manage'],
                'active' => ['pm.settings.*'],
                'tabs' => [
                    ['label' => 'Vue d\'ensemble', 'route' => 'pm.settings.index', 'active' => ['pm.settings.index']],
                    ['label' => 'Utilisateurs', 'route' => 'pm.settings.users.index', 'can' => 'users.manage', 'active' => ['pm.settings.users.*']],
                    ['label' => 'Secteurs et pipelines', 'route' => 'pm.settings.pipelines.index', 'can' => 'pipelines.manage', 'active' => ['pm.settings.pipelines.*']],
                    ['label' => 'Envoi d\'e-mails', 'route' => 'pm.settings.mail.index', 'can' => 'settings.manage', 'active' => ['pm.settings.mail.*']],
                    ['label' => 'Réponses (IMAP)', 'route' => 'pm.settings.inboxes.index', 'can' => 'settings.manage', 'active' => ['pm.settings.inboxes.*']],
                    ['label' => 'Suivi des e-mails', 'route' => 'pm.settings.tracking.edit', 'can' => 'settings.manage', 'active' => ['pm.settings.tracking.*']],
                    ['label' => 'Connexion Meta', 'route' => 'pm.settings.social.index', 'can' => 'settings.manage', 'active' => ['pm.settings.social.*']],
                ],
            ],
        ];

        $visible = [];
        foreach ($sections as $section) {
            if (! self::allowed($user, $section['can'] ?? null)) {
                continue;
            }
            $section['tabs'] = array_values(array_filter($section['tabs'], fn ($tab) => self::allowed($user, $tab['can'] ?? null)));
            // La rubrique ouvre son premier onglet accessible.
            $section['route'] = $section['tabs'][0]['route'] ?? match ($section['key']) {
                'accueil' => 'pm.dashboard',
                'taches' => 'pm.tasks.index',
                'automatisations' => 'pm.automations.index',
                default => 'pm.reports.index',
            };
            $visible[] = $section;
        }

        return $visible;
    }

    /** Rubrique de la page courante (pour les onglets). */
    public static function current(array $sections): ?array
    {
        foreach ($sections as $section) {
            if (Route::currentRouteNamed(...$section['active'])) {
                return $section;
            }
        }

        return null;
    }

    private static function allowed(User $user, string|array|null $permission): bool
    {
        if ($permission === null) {
            return true;
        }

        return collect((array) $permission)->contains(fn ($p) => $user->can($p));
    }
}
