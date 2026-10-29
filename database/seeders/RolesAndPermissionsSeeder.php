<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Idempotent : peut être rejoué à chaque déploiement. */
class RolesAndPermissionsSeeder extends Seeder
{
    public const PERMISSIONS = [
        'prospects.view' => 'Consulter les prospects',
        'prospects.view_team' => 'Périmètre : fiches de mon équipe',
        'prospects.view_all' => 'Périmètre : toutes les fiches',
        'prospects.create' => 'Créer des prospects',
        'prospects.update' => 'Modifier des prospects',
        'prospects.delete' => 'Supprimer des prospects',
        'prospects.bulk_update' => 'Modifier en masse',
        'prospects.assign' => 'Assigner un responsable',
        'prospects.import' => 'Importer des fichiers',
        'prospects.export' => 'Exporter des données',
        'prospects.convert' => 'Convertir un prospect en client',
        'compliance.manage' => 'Gérer oppositions et droits des personnes',
        'audit.view' => 'Consulter le journal d\'audit',
        'users.manage' => 'Gérer les utilisateurs et les rôles',
        'settings.manage' => 'Gérer les paramètres et intégrations',
        'pipelines.manage' => 'Configurer pipelines, étapes, secteurs et sources',
        'campaigns.view' => 'Consulter les campagnes et leurs statistiques',
        'campaigns.manage' => 'Préparer des campagnes, segments et listes',
        'campaigns.send' => 'Programmer, lancer, suspendre et annuler des campagnes',
        'templates.manage' => 'Créer et modifier les modèles d\'e-mails',
        'emails.individual' => 'Envoyer des e-mails individuels aux prospects',
        'automations.manage' => 'Créer, tester, activer et arrêter les scénarios d\'automatisation',
        'reports.view' => 'Consulter les statistiques et rapports',
        'social.use' => 'Suivre les prospects sur les réseaux sociaux (profils, actions, relances)',
        'social.templates' => 'Gérer les modèles de messages pour les réseaux sociaux',
        'social.inbox' => 'Lire et répondre aux messages reçus sur la Page (API officielle Meta)',
    ];

    public const ROLES = [
        'administrateur' => '*',
        'responsable_commercial' => [
            'prospects.view', 'prospects.view_team', 'prospects.create', 'prospects.update', 'prospects.delete',
            'prospects.bulk_update', 'prospects.assign', 'prospects.import', 'prospects.export', 'prospects.convert',
            'campaigns.view', 'emails.individual', 'automations.manage', 'reports.view',
            'social.use', 'social.templates', 'social.inbox',
        ],
        'commercial' => ['prospects.view', 'prospects.create', 'prospects.update', 'prospects.import', 'emails.individual', 'social.use', 'social.inbox'],
        'marketing' => ['prospects.view', 'prospects.view_all', 'campaigns.view', 'campaigns.manage', 'campaigns.send', 'templates.manage', 'automations.manage', 'reports.view', 'social.use', 'social.templates', 'social.inbox'],
        'conformite' => ['prospects.view', 'prospects.view_all', 'compliance.manage', 'audit.view', 'campaigns.view', 'reports.view'],
        'lecture_seule' => ['prospects.view', 'prospects.view_all'],
    ];

    public const ROLE_LABELS = [
        'administrateur' => 'Administrateur',
        'responsable_commercial' => 'Responsable commercial',
        'commercial' => 'Commercial',
        'marketing' => 'Marketing',
        'conformite' => 'Conformité / DPO',
        'lecture_seule' => 'Lecture seule',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys(self::PERMISSIONS) as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (self::ROLES as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions(
                $permissions === '*' ? array_keys(self::PERMISSIONS) : $permissions
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
