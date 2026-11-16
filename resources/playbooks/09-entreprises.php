<?php

// Fiche secteur : entreprises avec locaux ou parking (flottes, bureaux, ateliers).
return [
    'slug' => 'entreprises',
    'name' => 'Entreprises avec locaux ou parking',
    'icon' => 'briefcase-business',
    'sectors' => ['Professionnels et entreprises tous secteurs'],
    'pitch' => 'Les entreprises passent leurs véhicules à l’électrique et rénovent leurs locaux. On installe les bornes pour la flotte et les salariés, et on modernise l’éclairage et l’électricité des locaux.',
    'decider' => 'Le dirigeant dans une PME ; dans une entreprise plus grande, le responsable des services généraux, de la flotte ou des achats.',
    'need' => [
        'Bornes de recharge pour les véhicules de société et les salariés.',
        'Éclairage plus économique dans les bureaux, ateliers et entrepôts.',
        'Mise en conformité et entretien de l’installation électrique.',
    ],
    'offer' => [
        'Étude et installation de bornes sur le parking de l’entreprise.',
        'Rénovation de l’éclairage (LED, détecteurs).',
        'Contrat d’entretien et dépannage avec délai d’intervention.',
    ],
    'gain' => [
        'Une flotte électrique rechargée sur place, des salariés satisfaits.',
        'Une facture d’électricité d’éclairage réduite.',
        'Un seul interlocuteur pour toute l’électricité.',
    ],
    'deal' => [
        'Client direct (pas d’apport d’affaires) : on vend une installation, puis un contrat d’entretien.',
        'Étude gratuite et devis détaillé.',
        'Contrat d’entretien annuel avec délai d’intervention garanti.',
        'Bonus : demander s’ils connaissent d’autres entreprises intéressées (recommandation).',
    ],
    'questions' => [
        'Combien de véhicules a votre flotte, et combien sont électriques ou le seront ?',
        'Vos salariés demandent-ils à recharger sur place ?',
        'Combien de places de parking avez-vous ?',
        'Votre éclairage date-t-il de plus de 10 ans ?',
        'Qui s’occupe de l’électricité de vos locaux aujourd’hui ?',
        'Qui décide de ce type d’investissement ?',
    ],
    'objections' => [
        ['q' => 'C’est un gros investissement.', 'a' => 'On peut commencer petit : quelques points de recharge, et prévoir le reste. Des aides existent parfois : nous vérifions celles en vigueur pour vous.'],
        ['q' => 'Ce n’est pas une priorité.', 'a' => 'Compris. Une étude gratuite vous permet de prévoir le budget pour l’an prochain sans vous engager.'],
    ],
    'check' => [
        'Les aides à la recharge en entreprise évoluent souvent : vérifiez-les avant de les citer.',
        'Pour les bornes de plus de 3,7 kW, la qualification IRVE est nécessaire.',
    ],
    'emails' => [
        [
            'subject' => 'Recharger vos véhicules sur votre parking',
            'body' => "Bonjour {{prenom}},\n\nDe plus en plus d’entreprises passent leurs véhicules à l’électrique, et les salariés demandent à recharger sur place.\n\nNous sommes électriciens à {{ville}}. Nous étudions gratuitement le parking de {{entreprise}} et vous proposons une solution adaptée, en commençant petit si vous le souhaitez.\n\nEst-ce un sujet chez vous ?\n\nBien cordialement,",
            'why' => 'Une étude gratuite et « commencer petit » enlèvent la peur du gros investissement.',
        ],
        [
            'subject' => 'Re : recharger vos véhicules sur votre parking',
            'body' => "Bonjour {{prenom}},\n\nAutre sujet souvent oublié : l’éclairage. Dans un atelier ou un entrepôt, passer à des LED avec détecteurs réduit souvent nettement la consommation d’éclairage.\n\nNous pouvons regarder les deux lors d’une même visite, sans engagement.\n\nQuand pourrais-je passer ?\n\nBien cordialement,",
            'why' => 'Deuxième angle : les économies sur l’éclairage, utile si la recharge n’est pas prioritaire.',
        ],
        [
            'subject' => 'Je clôture ?',
            'body' => "Bonjour {{prenom}},\n\nUne réponse en un chiffre :\n1 — intéressé, appelez-moi\n2 — plus tard, pour le budget de l’an prochain\n3 — pas pour nous\n\nMerci et bonne journée,",
            'why' => 'Le choix 2 colle au rythme budgétaire des entreprises.',
        ],
        [
            'subject' => 'Notre visite du [à compléter : date]',
            'body' => "Bonjour {{prenom}},\n\nJe vous confirme ma visite le [à compléter : date et heure] chez {{entreprise}}.\n\nPour préparer l’étude, pouvez-vous me dire le nombre de places de parking et l’emplacement du tableau électrique principal ?\n\nÀ bientôt,",
            'why' => 'Les questions préparent une étude sérieuse et montrent le professionnalisme.',
        ],
        [
            'subject' => 'Suite à notre visite',
            'body' => "Bonjour {{prenom}},\n\nMerci pour votre accueil. J’ai retenu :\n- [à compléter] véhicules, dont [à compléter] électriques à terme ;\n- [à compléter] places de parking ;\n- vos priorités : [à compléter].\n\nJe vous envoie l’étude et le devis d’ici [à compléter : date]. Ai-je bien noté ?\n\nBien cordialement,",
            'why' => 'Résumé validé avant le devis.',
        ],
        [
            'subject' => 'Votre étude et notre proposition',
            'body' => "Bonjour {{prenom}},\n\nVoici notre proposition pour {{entreprise}} :\n\n1. [à compléter : solution, ex. 4 points de recharge, extensibles à 10].\n2. Montant : [à compléter], aides éventuelles : [à compléter après vérification].\n3. Délai de réalisation : [à compléter].\n4. Option : contrat d’entretien annuel avec intervention sous [à compléter].\n\nQuelle suite souhaitez-vous donner ?\n\nBien cordialement,",
            'why' => 'Une proposition structurée en 4 points, facile à transmettre à la direction.',
        ],
        [
            'subject' => 'Re : votre étude',
            'body' => "Bonjour {{prenom}},\n\nAvez-vous pu présenter l’étude en interne ? Si un élément manque pour la décision (phasage, budget, calendrier), je peux l’ajouter.\n\nBien cordialement,",
            'why' => 'Aider l’interlocuteur à convaincre sa direction.',
        ],
        [
            'subject' => 'Installation terminée',
            'body' => "Bonjour {{prenom}},\n\nL’installation est terminée depuis le [à compléter : date] : [à compléter : travaux]. Tout fonctionne.\n\nConnaissez-vous d’autres entreprises autour de vous qui réfléchissent au même projet ? Je serais ravi de les aider.\n\nBien cordialement,",
            'why' => 'Le moment idéal pour demander une recommandation : juste après un chantier réussi.',
        ],
        [
            'subject' => 'Bienvenue — votre contact électricité',
            'body' => "Bonjour {{prenom}},\n\nMerci pour votre confiance. Pour toute demande ou dépannage : [à compléter : e-mail et téléphone].\n\nNous vous proposerons une visite de contrôle dans [à compléter : délai].\n\nBien cordialement,",
            'why' => 'Prépare le contrat d’entretien et la relation longue.',
        ],
        [
            'subject' => 'Des nouvelles de votre installation',
            'body' => "Bonjour {{prenom}},\n\nJe prends des nouvelles : tout fonctionne bien ? Avez-vous de nouveaux besoins (plus de bornes, éclairage, aménagement de locaux) ?\n\nBien cordialement,",
            'why' => 'Garder le contact fait naître les chantiers suivants.',
        ],
    ],
];
