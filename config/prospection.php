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

    'mail' => [
        // Envois par lot et reprise des erreurs temporaires.
        'batch_size' => (int) env('PM_MAIL_BATCH_SIZE', 50),
        'max_attempts' => 3,
        'retry_minutes' => [5, 30, 120],
        // Un envoi resté « en cours » au-delà de ce délai est mis « à vérifier » (pas de renvoi automatique).
        'stale_minutes' => 15,
        'test_max_recipients' => 5,
    ],

    'mail_drivers' => [
        'smtp' => 'Serveur SMTP',
        'brevo' => 'API Brevo (à activer avec une clé API)',
        'log' => 'Journal local (test, aucun envoi réel)',
    ],

    'campaign_types' => [
        'prospection' => 'Prospection commerciale',
        'newsletter' => 'Newsletter',
        'operationnel' => 'Communication opérationnelle',
        'individuel' => 'E-mail individuel',
    ],

    'campaign_statuses' => [
        'brouillon' => 'Brouillon',
        'programmee' => 'Programmée',
        'en_cours' => 'Envoi en cours',
        'en_pause' => 'En pause',
        'terminee' => 'Terminée',
        'annulee' => 'Annulée',
    ],

    'recipient_statuses' => [
        'en_attente' => 'En attente',
        'envoi' => 'Envoi en cours',
        'accepte' => 'Accepté par le serveur d\'envoi',
        'echec' => 'Échec',
        'exclu' => 'Exclu (contrôle de conformité)',
        'annule' => 'Annulé',
        'a_verifier' => 'À vérifier (envoi interrompu)',
    ],

    'exclusion_reasons' => [
        'opposition' => 'Opposition ou désabonnement enregistré',
        'adresse_invalide' => 'Adresse invalide (rebond définitif)',
        'sans_email' => 'Pas d\'adresse e-mail',
        'b2c_sans_consentement' => 'Particulier sans consentement',
        'non_abonne' => 'Non abonné à la liste',
        'fiche_supprimee' => 'Fiche supprimée',
        'doublon_adresse' => 'Adresse déjà présente dans la campagne',
        'exclusion_tag' => 'Exclu par un tag',
        'contacte_recemment' => 'Déjà contacté récemment',
    ],

    // Variables utilisables dans les modèles : {{prenom}}, {{entreprise}}…
    'template_variables' => [
        'prenom' => 'Prénom du contact',
        'nom' => 'Nom du contact',
        'civilite' => 'Civilité',
        'fonction' => 'Fonction',
        'entreprise' => 'Nom de l\'entreprise',
        'ville' => 'Ville de l\'entreprise',
        'expediteur' => 'Nom de l\'expéditeur',
        'lien_desinscription' => 'Lien de désinscription',
    ],
];
