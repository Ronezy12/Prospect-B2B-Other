<?php

// Fiche secteur : bailleurs et gestionnaires locatifs.
return [
    'slug' => 'bailleurs',
    'name' => 'Bailleurs et gestionnaires locatifs',
    'icon' => 'house',
    'sectors' => ['Bailleurs et gestionnaires locatifs'],
    'pitch' => 'Un logement vide coûte de l’argent chaque jour. On remet l’électricité en état entre deux locataires, vite, et on dépanne les locataires sans que le gestionnaire ait à courir.',
    'decider' => 'Le responsable de la gestion locative ou technique, le gérant d’une société de gestion, ou directement un propriétaire qui possède plusieurs logements.',
    'need' => [
        'Remettre en état un logement entre deux locataires, le plus vite possible.',
        'Répondre aux pannes signalées par les locataires sans perdre de temps.',
        'Garder des logements sûrs et conformes pour éviter les litiges.',
    ],
    'offer' => [
        'Remise en état électrique entre deux locations, dans un délai court.',
        'Dépannages chez les locataires, avec prise de rendez-vous directe.',
        'Petits travaux d’amélioration : éclairage, prises, chauffage électrique, tableau.',
        'Photos et compte rendu après chaque intervention.',
    ],
    'gain' => [
        'Moins de jours de vacance locative.',
        'Plus besoin de gérer les rendez-vous avec les locataires : on s’en charge.',
        'Un suivi écrit de chaque intervention, utile en cas de litige.',
        'Des tarifs stables et prévisibles.',
    ],
    'deal' => [
        'Tarifs annuels fixes pour les interventions courantes (déplacement, remplacement de prise, etc.).',
        'Délai garanti pour les remises en état entre deux locations.',
        'Facturation regroupée mensuelle si le gestionnaire le souhaite.',
        'Compte rendu avec photos systématique.',
    ],
    'questions' => [
        'Combien de logements gérez-vous ?',
        'Combien de changements de locataires par mois environ ?',
        'Qui gère aujourd’hui les pannes signalées par les locataires ?',
        'Combien de temps un logement reste-t-il vide entre deux locations ?',
        'Avez-vous un électricien attitré ? Est-il disponible rapidement ?',
        'Qui valide les devis : vous ou le propriétaire ?',
    ],
    'objections' => [
        ['q' => 'Les propriétaires veulent payer le moins possible.', 'a' => 'Des tarifs fixes et connus d’avance évitent les mauvaises surprises, et un logement reloué plus vite rapporte plus que la différence de prix.'],
        ['q' => 'Nous avons déjà quelqu’un.', 'a' => 'Gardez-le pour le quotidien. Nous pouvons prendre le relais quand il est indisponible ou en période chargée.'],
    ],
    'check' => [
        'Précisez par écrit qui valide et paie les devis (gestionnaire ou propriétaire).',
        'Prenez des photos avant et après chaque intervention.',
    ],
    'emails' => [
        [
            'subject' => 'Logements entre deux locataires',
            'body' => "Bonjour {{prenom}},\n\nChaque jour où un logement reste vide parce que l’électricité n’est pas en état, c’est un loyer perdu.\n\nNous sommes électriciens à {{ville}}. Pour {{entreprise}}, nous intervenons rapidement entre deux locations et nous gérons directement les rendez-vous avec les locataires pour les dépannages.\n\nEst-ce un sujet chez vous en ce moment ?\n\nBien cordialement,",
            'why' => 'On parle d’argent perdu (vacance locative) : c’est la vraie préoccupation d’un gestionnaire.',
        ],
        [
            'subject' => 'Re : logements entre deux locataires',
            'body' => "Bonjour {{prenom}},\n\nUne précision sur ce que nous proposons : des tarifs fixes sur l’année pour les interventions courantes, et une photo avant/après à chaque passage.\n\nVous savez combien vous payez, et vous avez une trace écrite en cas de litige avec un locataire.\n\nPuis-je vous appeler 10 minutes cette semaine ?\n\nBien cordialement,",
            'why' => 'Deux arguments concrets : prix prévisible et preuve en cas de litige.',
        ],
        [
            'subject' => 'Je clôture de mon côté ?',
            'body' => "Bonjour {{prenom}},\n\nPour ne pas vous relancer inutilement, répondez simplement :\n1 — intéressé, appelez-moi\n2 — plus tard, dans 3 mois\n3 — pas pour nous\n\nMerci et bonne journée,",
            'why' => 'La clôture polie obtient des réponses même des personnes très occupées.',
        ],
        [
            'subject' => 'Notre rendez-vous du [à compléter : date]',
            'body' => "Bonjour {{prenom}},\n\nJe vous confirme notre rendez-vous le [à compléter : date et heure].\n\nSi vous avez un logement vide ou une panne en attente, nous pourrons en parler concrètement.\n\nÀ bientôt,",
            'why' => 'Ancrer le rendez-vous sur un cas réel.',
        ],
        [
            'subject' => 'Suite à notre échange',
            'body' => "Bonjour {{prenom}},\n\nMerci pour votre temps. J’ai retenu :\n- environ [à compléter] logements gérés ;\n- [à compléter] changements de locataires par mois ;\n- votre priorité : [à compléter : ex. réduire les délais de remise en état].\n\nJe vous envoie notre proposition de tarifs d’ici [à compléter : date]. Est-ce bien cela ?\n\nBien cordialement,",
            'why' => 'Valider les chiffres avant de proposer des tarifs annuels.',
        ],
        [
            'subject' => 'Proposition — tarifs et délais pour {{entreprise}}',
            'body' => "Bonjour {{prenom}},\n\nVoici notre proposition :\n\n1. Remise en état entre deux locations : intervention sous [à compléter : délai].\n2. Dépannages locataires : nous les appelons directement pour fixer le rendez-vous.\n3. Grille de tarifs fixes pour l’année : [à compléter : principaux tarifs].\n4. Photos et compte rendu après chaque intervention, facture regroupée chaque mois si vous le souhaitez.\n\nOn teste sur le prochain logement qui se libère ?\n\nBien cordialement,",
            'why' => 'Tout est prévisible pour le gestionnaire. Le test sur un logement réduit le risque perçu.',
        ],
        [
            'subject' => 'Re : notre proposition',
            'body' => "Bonjour {{prenom}},\n\nAvez-vous pu regarder la grille de tarifs ? Si une ligne vous paraît trop haute ou s’il manque une intervention fréquente chez vous, dites-le-moi : nous l’ajustons.\n\nBien cordialement,",
            'why' => 'On invite à négocier une ligne précise plutôt qu’à refuser l’ensemble.',
        ],
        [
            'subject' => 'Logement prêt — [à compléter : adresse]',
            'body' => "Bonjour {{prenom}},\n\nLe logement [à compléter : adresse] est prêt à être reloué depuis le [à compléter : date].\n\nTravaux réalisés : [à compléter]. Photos avant/après jointes au compte rendu.\n\nOn continue ensemble sur vos autres logements ?\n\nBien cordialement,",
            'why' => 'Le résultat attendu (logement prêt à relouer) est dans l’objet : impossible à rater.',
        ],
        [
            'subject' => 'Bienvenue — vos contacts',
            'body' => "Bonjour {{prenom}},\n\nMerci pour votre confiance. Pour nous joindre :\n- remise en état et devis : [à compléter : e-mail dédié] ;\n- dépannage locataire : [à compléter : téléphone].\n\nVous pouvez donner directement ce numéro à vos locataires : nous vous tiendrons informé.\n\nBien cordialement,",
            'why' => 'Donner un numéro pour les locataires retire une charge au gestionnaire dès le premier jour.',
        ],
        [
            'subject' => 'Le point du mois',
            'body' => "Bonjour {{prenom}},\n\nLe point du mois :\n- interventions : [à compléter] ;\n- logements remis en état : [à compléter] ;\n- délai moyen : [à compléter].\n\nDes logements vont-ils se libérer le mois prochain ? Nous pouvons réserver les créneaux.\n\nBien cordialement,",
            'why' => 'Réserver les créneaux à l’avance montre qu’on anticipe et sécurise les prochains chantiers.',
        ],
    ],
];
