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
        'reseau_social' => 'Réseau social',
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
        'prospection' => 'Prospection (e-mail à des entreprises)',
        'newsletter' => 'Newsletter (abonnés)',
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

    // Résultat de livraison remonté par le fournisseur (webhooks). Un statut plus
    // « grave » n'est jamais écrasé par un événement arrivé en retard.
    'delivery_statuses' => [
        'differe' => 'Livraison différée',
        'rebond_temporaire' => 'Rebond temporaire',
        'livre' => 'Livré',
        'bloque' => 'Bloqué par le fournisseur',
        'rebond_definitif' => 'Rebond définitif',
        'plainte' => 'Signalé comme indésirable',
    ],
    'delivery_precedence' => ['differe' => 1, 'rebond_temporaire' => 2, 'livre' => 3, 'bloque' => 4, 'rebond_definitif' => 5, 'plainte' => 6],

    /*
    | Réseaux sociaux : suivi manuel / assisté. Aucune collecte automatique de profils
    | et aucune action automatisée sur les plateformes (conditions d'utilisation).
    | Seule l'API officielle de Meta (Page Facebook / Instagram professionnel) est
    | branchée, pour recevoir les messages entrants et y répondre dans la fenêtre autorisée.
    */
    'social' => [
        'platforms' => [
            'linkedin' => ['label' => 'LinkedIn', 'hosts' => ['linkedin.com']],
            'facebook' => ['label' => 'Facebook', 'hosts' => ['facebook.com', 'fb.com']],
            'instagram' => ['label' => 'Instagram', 'hosts' => ['instagram.com']],
            'x' => ['label' => 'X (Twitter)', 'hosts' => ['x.com', 'twitter.com']],
            'autre' => ['label' => 'Autre réseau', 'hosts' => []],
        ],
        'statuses' => [
            'a_contacter' => 'À contacter',
            'invitation_envoyee' => 'Invitation envoyée',
            'connecte' => 'Connecté / abonné',
            'message_envoye' => 'Message envoyé',
            'en_discussion' => 'En discussion',
            'rdv_obtenu' => 'Rendez-vous obtenu',
            'pas_interesse' => 'Pas intéressé',
            'ne_plus_contacter' => 'Ne plus contacter (opposition)',
        ],
        // Statuts « fermés » : la fiche sort de la file de travail.
        'closed_statuses' => ['rdv_obtenu', 'pas_interesse', 'ne_plus_contacter'],
        'interaction_types' => [
            'invitation_envoyee' => 'Invitation envoyée',
            'invitation_acceptee' => 'Invitation acceptée',
            'message_envoye' => 'Message envoyé',
            'message_recu' => 'Réponse reçue',
            'commentaire' => 'Commentaire / réaction sur une publication',
            'rdv' => 'Rendez-vous fixé',
            'autre' => 'Autre action',
        ],
        // Statut proposé après chaque type d'action (modifiable lors de la saisie).
        'status_after' => [
            'invitation_envoyee' => 'invitation_envoyee',
            'invitation_acceptee' => 'connecte',
            'message_envoye' => 'message_envoye',
            'message_recu' => 'en_discussion',
            'rdv' => 'rdv_obtenu',
        ],
        'outbound_types' => ['invitation_envoyee', 'message_envoye'],
        'template_kinds' => [
            'invitation' => 'Note d\'invitation',
            'message' => 'Premier message',
            'relance' => 'Relance',
            'reponse' => 'Réponse',
        ],
        // Limites de longueur indicatives (vérifiez-les sur la plateforme, elles évoluent).
        'length_limits' => ['invitation' => 300],
        'template_variables' => ['prenom', 'nom', 'civilite', 'fonction', 'entreprise', 'ville', 'expediteur'],
        'meta' => [
            'graph_url' => env('PM_META_GRAPH_URL', 'https://graph.facebook.com'),
            'graph_version' => env('PM_META_GRAPH_VERSION', 'v25.0'),
            // Fenêtre standard de réponse après le dernier message de la personne.
            'reply_window_hours' => 24,
        ],
        'channels' => [
            'messenger' => 'Messenger (Page Facebook)',
            'instagram' => 'Instagram (messages directs)',
        ],
    ],

    'automation' => [
        'max_steps_per_execution' => 50,
        'max_attempts' => 3,
        'triggers' => [
            'company.created' => 'Nouveau prospect créé',
            'company.status_changed' => 'Changement de statut d\'un prospect',
            'opportunity.stage_changed' => 'Changement d\'étape d\'une opportunité',
            'opportunity.won' => 'Opportunité gagnée',
            'email.replied' => 'Réponse reçue à un e-mail',
            'email.unsubscribed' => 'Désinscription d\'un contact',
            'email.bounced' => 'Adresse invalide (rebond définitif)',
            'social.reply_received' => 'Réponse reçue sur un réseau social',
        ],
        'step_types' => [
            'action' => 'Alors : faire une action',
            'delay' => 'Attendre un délai',
            'condition' => 'Vérifier : seulement si…',
        ],
        'actions' => [
            'create_task' => 'Créer une tâche pour le responsable',
            'notify_owner' => 'Notifier le responsable',
            'set_company_status' => 'Changer le statut du prospect',
            'add_tag' => 'Ajouter un tag à l\'entreprise',
            'send_email' => 'Envoyer un e-mail au contact principal (contrôles de conformité appliqués)',
            'stop_followups' => 'Suspendre les relances (tâches et scénarios en attente)',
            'convert_to_client' => 'Enregistrer la conversion en client',
            'stop' => 'Arrêter le scénario',
        ],
        'conditions' => [
            'company.status' => 'Statut du prospect',
            'company.lifecycle' => 'Cycle (prospect / client)',
            'company.sector_id' => 'Secteur',
            'company.has_owner' => 'A un responsable',
            'company.has_open_opportunity' => 'A une opportunité en cours',
            'company.replied_since_start' => 'Un contact a répondu (e-mail ou réseau social) depuis le début du scénario',
            'company.opposed' => 'Un contact s\'est opposé ou désinscrit',
            'company.social_opposed' => 'Opposition enregistrée sur un réseau social',
            'opportunity.stage_type' => 'Type d\'étape de l\'opportunité (en cours / gagné / perdu)',
        ],
        'operators' => [
            'equals' => 'est égal à',
            'not_equals' => 'est différent de',
            'is_true' => 'est vrai',
            'is_false' => 'est faux',
        ],
    ],
];
