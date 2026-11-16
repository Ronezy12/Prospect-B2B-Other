<?php

// Fiche secteur : cuisinistes et agenceurs.
return [
    'slug' => 'cuisinistes',
    'name' => 'Cuisinistes et agenceurs',
    'icon' => 'chef-hat',
    'sectors' => ['Cuisinistes et agenceurs'],
    'pitch' => 'Une cuisine neuve demande presque toujours de reprendre l’électricité : plaques, four, hotte, éclairage. On prépare le terrain avant la pose pour que l’installation se passe sans surprise.',
    'decider' => 'Le gérant du magasin, le responsable des poses ou le concepteur-vendeur qui suit les projets.',
    'need' => [
        'Des circuits électriques adaptés avant la pose (plaques, four, lave-vaisselle, hotte).',
        'Un électricien disponible au bon moment, coordonné avec le poseur.',
        'Éviter les poses repoussées parce que l’électricité n’est pas prête.',
    ],
    'offer' => [
        'Visite et devis à partir du plan de cuisine fourni par le magasin.',
        'Création des circuits dédiés, prises et éclairage (plan de travail, plafond).',
        'Intervention calée avant la date de pose, en lien avec le poseur.',
        'Possibilité d’intervenir aussi sur salle de bains et dressing.',
    ],
    'gain' => [
        'Des poses qui ne glissent plus à cause de l’électricité.',
        'Un service complet à proposer au client (« on s’occupe aussi de l’électricité »).',
        'Une commission par chantier ou un tarif partenaire.',
    ],
    'deal' => [
        'Commission par chantier réalisé ou tarif préférentiel pour les clients du magasin.',
        'Visite technique sous [à définir] jours après transmission du plan.',
        'Électricité prête au plus tard [à définir] jours avant la date de pose.',
    ],
    'questions' => [
        'Combien de cuisines posez-vous par mois ?',
        'Qui s’occupe de l’électricité aujourd’hui : le client, votre poseur, un électricien ?',
        'Avez-vous déjà repoussé une pose à cause de l’électricité ?',
        'Fournissez-vous un plan d’implantation électrique au client ?',
        'Faites-vous aussi des salles de bains ?',
        'Qui décide du choix des partenaires ?',
    ],
    'objections' => [
        ['q' => 'Nos poseurs font aussi l’électricité.', 'a' => 'Pour les petits branchements, oui. Pour créer des circuits ou reprendre un tableau, un électricien évite les risques et engage sa responsabilité.'],
        ['q' => 'Les clients ont leur propre électricien.', 'a' => 'Certains, oui. Pour les autres, pouvoir proposer une solution complète est un vrai plus commercial.'],
    ],
    'check' => [
        'Demandez toujours le plan d’implantation de la cuisine avant de chiffrer.',
        'Rédigez la commission dans une convention d’apport d’affaires écrite.',
    ],
    'emails' => [
        [
            'subject' => 'L’électricité avant la pose de vos cuisines',
            'body' => "Bonjour {{prenom}},\n\nUne pose de cuisine repoussée parce que l’électricité n’est pas prête, c’est un client mécontent et un poseur immobilisé.\n\nNous sommes électriciens à {{ville}}. Pour les clients de {{entreprise}}, nous préparons les circuits à partir de votre plan, avant la date de pose.\n\nEst-ce que cela vous arrive ?\n\nBien cordialement,",
            'why' => 'Problème concret et coûteux pour le cuisiniste, question fermée simple.',
        ],
        [
            'subject' => 'Re : l’électricité avant la pose',
            'body' => "Bonjour {{prenom}},\n\nConcrètement : vous nous transmettez le plan de la cuisine et le contact du client, nous visitons, chiffrons et intervenons avant la pose.\n\nVous proposez un service complet, sans gérer l’électricité vous-même, et vous touchez une commission sur chaque chantier.\n\nPuis-je passer au magasin 10 minutes cette semaine ?\n\nBien cordialement,",
            'why' => 'Fonctionnement en une phrase et bénéfice commercial clair.',
        ],
        [
            'subject' => 'Un chiffre suffit',
            'body' => "Bonjour {{prenom}},\n\nPour savoir si je dois vous recontacter :\n1 — intéressé, passez au magasin\n2 — plus tard, dans 3 mois\n3 — pas pour nous\n\nMerci et belle journée,",
            'why' => 'Clôture rapide et respectueuse.',
        ],
        [
            'subject' => 'Notre rendez-vous du [à compléter : date]',
            'body' => "Bonjour {{prenom}},\n\nJe vous confirme mon passage au magasin {{entreprise}} le [à compléter : date et heure].\n\nSi vous avez un projet en cours avec un plan de cuisine, je pourrai vous dire tout de suite ce qu’il faudrait prévoir côté électricité.\n\nÀ bientôt,",
            'why' => 'Un conseil immédiat sur un vrai projet prouve l’utilité du partenariat.',
        ],
        [
            'subject' => 'Suite à notre échange',
            'body' => "Bonjour {{prenom}},\n\nMerci pour votre accueil. J’ai retenu :\n- environ [à compléter] cuisines par mois ;\n- aujourd’hui l’électricité est faite par [à compléter] ;\n- votre attente : [à compléter : ex. ne plus repousser de poses].\n\nJe vous envoie notre proposition d’ici [à compléter : date]. Ai-je bien compris ?\n\nBien cordialement,",
            'why' => 'Résumé validé avant la proposition.',
        ],
        [
            'subject' => 'Proposition de partenariat — {{entreprise}}',
            'body' => "Bonjour {{prenom}},\n\nNotre proposition :\n\n1. Vous nous envoyez le plan et le contact du client.\n2. Visite sous [à compléter : délai] et devis clair.\n3. Électricité prête au plus tard [à compléter : délai] avant la date de pose.\n4. Pour chaque chantier réalisé : [à compléter : commission ou tarif partenaire].\n\nOn essaie sur votre prochain projet ?\n\nBien cordialement,",
            'why' => 'Engagement de délai lié à la date de pose : c’est le point qui compte pour le cuisiniste.',
        ],
        [
            'subject' => 'Re : proposition de partenariat',
            'body' => "Bonjour {{prenom}},\n\nAvez-vous pu regarder notre proposition ? Si le délai ou la commission doivent être adaptés, dites-le-moi.\n\nBien cordialement,",
            'why' => 'Relance courte qui invite à négocier.',
        ],
        [
            'subject' => 'Électricité prête chez [à compléter : nom du client]',
            'body' => "Bonjour {{prenom}},\n\nL’électricité est prête chez [à compléter : nom du client] pour la pose du [à compléter : date].\n\nTravaux réalisés : [à compléter]. Votre poseur peut intervenir comme prévu.\n\nOn continue sur vos prochains projets ?\n\nBien cordialement,",
            'why' => 'On confirme que la pose peut avoir lieu : exactement ce que le magasin veut entendre.',
        ],
        [
            'subject' => 'Bienvenue parmi nos partenaires',
            'body' => "Bonjour {{prenom}},\n\nMerci pour votre confiance. Pour nous transmettre un projet : [à compléter : e-mail dédié], avec le plan et le contact du client.\n\nJe peux présenter le fonctionnement à vos concepteurs en 10 minutes. Quel jour vous convient ?\n\nBien cordialement,",
            'why' => 'Les concepteurs-vendeurs sont ceux qui proposeront le service au client.',
        ],
        [
            'subject' => 'Le point du mois',
            'body' => "Bonjour {{prenom}},\n\nLe point du mois :\n- projets transmis : [à compléter] ;\n- chantiers réalisés : [à compléter] ;\n- commission : [à compléter].\n\nQuelles poses sont prévues le mois prochain ? Je peux réserver les créneaux.\n\nBien cordialement,",
            'why' => 'Anticiper les poses sécurise le planning des deux côtés.',
        ],
    ],
];
