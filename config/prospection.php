<?php

return [

    /*
    | Clé HMAC dédiée à la liste d'opposition. Elle doit rester stable dans le
    | temps : la changer rendrait les empreintes existantes inutilisables.
    | À défaut, la clé applicative est utilisée (déconseillé en production).
    */
    'suppression_key' => env('PM_SUPPRESSION_KEY'),

    'import' => [
        'max_file_kb' => (int) env('PM_IMPORT_MAX_KB', 20480),
        'chunk_size' => 500,
        'preview_rows' => 10,
        'disk' => 'local',
        'directory' => 'imports',
        // Les fichiers téléversés sont supprimés après ce délai (jours).
        'retention_days' => (int) env('PM_IMPORT_RETENTION_DAYS', 30),
    ],

    'export' => [
        'max_rows' => (int) env('PM_EXPORT_MAX_ROWS', 50000),
    ],

    'per_page' => 25,

    'company_statuses' => [
        'nouveau' => 'Nouveau',
        'a_qualifier' => 'À qualifier',
        'qualifie' => 'Qualifié',
        'non_pertinent' => 'Non pertinent',
        'en_sommeil' => 'En sommeil',
    ],

    'lifecycles' => [
        'prospect' => 'Prospect',
        'client' => 'Client',
    ],

    'audience_types' => [
        'b2b' => 'B2B (adresse professionnelle)',
        'b2c' => 'B2C / adresse personnelle',
    ],

    'legal_bases' => [
        'interet_legitime' => 'Intérêt légitime (prospection B2B)',
        'consentement' => 'Consentement',
        'contrat' => 'Exécution d\'un contrat',
        'obligation_legale' => 'Obligation légale',
        'a_determiner' => 'À déterminer',
    ],

    'suppression_scopes' => [
        'all' => 'Toutes communications marketing',
        'prospection' => 'Prospection commerciale',
        'newsletter' => 'Newsletters',
    ],

    'activity_types' => [
        'note' => 'Note',
        'appel' => 'Appel',
        'email' => 'E-mail',
        'rendez_vous' => 'Rendez-vous',
        'reseau_social' => 'Réseau social',
        'systeme' => 'Événement système',
    ],

    'priorities' => [
        'basse' => 'Basse',
        'normale' => 'Normale',
        'haute' => 'Haute',
        'urgente' => 'Urgente',
    ],

    // Étapes créées pour chaque nouvelle pipeline : [nom, type, probabilité %].
    'default_stages' => [
        ['Nouveau', 'open', 5],
        ['À contacter', 'open', 10],
        ['Contact effectué', 'open', 20],
        ['Relance', 'open', 25],
        ['Intéressé', 'open', 40],
        ['Rendez-vous', 'open', 55],
        ['Proposition envoyée', 'open', 70],
        ['Gagné', 'won', 100],
        ['Perdu', 'lost', 0],
        ['À recontacter', 'open', 15],
    ],

    'stage_types' => [
        'open' => 'En cours',
        'won' => 'Gagné (clôture)',
        'lost' => 'Perdu (clôture)',
    ],

    'task_types' => [
        'relance' => 'Relance',
        'appel' => 'Appel',
        'email' => 'E-mail',
        'rendez_vous' => 'Rendez-vous',
        'devis' => 'Devis / proposition',
        'autre' => 'Autre',
    ],

    'task_statuses' => [
        'a_faire' => 'À faire',
        'fait' => 'Fait',
        'annule' => 'Annulé',
    ],
];
