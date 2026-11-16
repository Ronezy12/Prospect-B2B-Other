<?php

// Fiche secteur : installateurs de panneaux solaires.
return [
    'slug' => 'solaire',
    'name' => 'Installateurs solaires',
    'icon' => 'sun',
    'sectors' => ['Installateurs solaires'],
    'pitch' => 'Les clients qui posent des panneaux veulent souvent une borne, un tableau à jour ou une maison mieux équipée. On complète l’offre de l’installateur solaire sans qu’il ait à embaucher.',
    'decider' => 'Le gérant de l’entreprise d’installation, ou le responsable commercial dans les sociétés plus grandes.',
    'need' => [
        'Tableau électrique à reprendre avant de raccorder les panneaux.',
        'Clients qui veulent une borne de recharge pour profiter de leur production.',
        'Pics d’activité où les équipes manquent.',
    ],
    'offer' => [
        'Mise à niveau du tableau électrique avant la pose.',
        'Installation de bornes de recharge, avec pilotage selon la production si l’équipement le permet.',
        'Renfort sur la partie électrique en période chargée.',
    ],
    'gain' => [
        'Une offre plus complète (solaire + recharge) sans embaucher.',
        'Des chantiers qui ne bloquent pas sur le tableau électrique.',
        'Une commission ou un échange de clients.',
    ],
    'deal' => [
        'Commission par chantier réalisé, ou échange de clients dans les deux sens.',
        'Délai d’intervention calé sur leur planning de pose.',
        'Prix connus d’avance pour les interventions courantes.',
    ],
    'questions' => [
        'Combien d’installations faites-vous par mois ?',
        'Combien de vos clients ont ou vont avoir une voiture électrique ?',
        'Qui reprend le tableau électrique quand c’est nécessaire ?',
        'Avez-vous des périodes où vos équipes sont débordées ?',
        'Seriez-vous intéressé par nos clients qui veulent des panneaux ?',
        'Qui décide des partenariats ?',
    ],
    'objections' => [
        ['q' => 'Nous avons des électriciens en interne.', 'a' => 'Parfait. Nous pouvons intervenir en renfort lors des pics, ou sur la recharge si ce n’est pas votre spécialité.'],
        ['q' => 'La borne, le client la fera plus tard.', 'a' => 'Justement : la proposer au moment du solaire, c’est un chantier de plus pour vous via la commission, et un client mieux équipé.'],
    ],
    'check' => [
        'Pour les bornes de plus de 3,7 kW, la qualification IRVE est nécessaire.',
        'Ne promettez pas d’économies chiffrées sans étude.',
    ],
    'emails' => [
        [
            'subject' => 'Panneaux solaires + recharge pour vos clients',
            'body' => "Bonjour {{prenom}},\n\nVos clients qui posent des panneaux ont souvent une voiture électrique, ou vont en avoir une. Recharger avec leur propre électricité, c’est un vrai argument.\n\nNous sommes électriciens à {{ville}}. Nous proposons à {{entreprise}} de compléter votre offre : borne de recharge, mise à niveau du tableau, sans que vous ayez à embaucher.\n\nEst-ce que vos clients vous en parlent ?\n\nBien cordialement,",
            'why' => 'On aide l’installateur à vendre plus (solaire + recharge) : intérêt immédiat.',
        ],
        [
            'subject' => 'Re : panneaux solaires + recharge',
            'body' => "Bonjour {{prenom}},\n\nAutre point fréquent : un tableau électrique trop ancien qui retarde la pose des panneaux. Nous pouvons intervenir avant votre équipe pour que le chantier se déroule sans attente.\n\nPuis-je vous appeler 10 minutes cette semaine ?\n\nBien cordialement,",
            'why' => 'Deuxième besoin, opérationnel : le tableau qui retarde les chantiers.',
        ],
        [
            'subject' => 'Un chiffre et je ne vous embête plus',
            'body' => "Bonjour {{prenom}},\n\nPour savoir quoi faire :\n1 — intéressé, appelez-moi\n2 — plus tard, dans 3 mois\n3 — pas pour nous\n\nMerci et bonne journée,",
            'why' => 'Clôture simple et sympathique.',
        ],
        [
            'subject' => 'Notre rendez-vous du [à compléter : date]',
            'body' => "Bonjour {{prenom}},\n\nJe vous confirme notre rendez-vous le [à compléter : date et heure].\n\nNous parlerons de vos prochains chantiers et de la façon dont la recharge peut compléter votre offre.\n\nÀ bientôt,",
            'why' => 'Confirmation simple, centrée sur leurs chantiers.',
        ],
        [
            'subject' => 'Suite à notre échange',
            'body' => "Bonjour {{prenom}},\n\nMerci pour cet échange. J’ai retenu :\n- environ [à compléter] installations par mois ;\n- besoin principal : [à compléter : recharge, tableau, renfort] ;\n- périodes chargées : [à compléter].\n\nJe vous envoie notre proposition d’ici [à compléter : date]. Est-ce bien cela ?\n\nBien cordialement,",
            'why' => 'Résumé validé.',
        ],
        [
            'subject' => 'Proposition de partenariat — {{entreprise}}',
            'body' => "Bonjour {{prenom}},\n\nNotre proposition :\n\n1. Recharge : vous proposez une borne à vos clients, nous l’installons.\n2. Tableau : nous intervenons avant votre pose quand c’est nécessaire.\n3. Renfort sur la partie électrique en période chargée.\n4. [à compléter : commission par chantier ou échange de clients].\n\nOn teste sur un prochain client ?\n\nBien cordialement,",
            'why' => 'Trois services clairs et une rémunération simple.',
        ],
        [
            'subject' => 'Re : proposition de partenariat',
            'body' => "Bonjour {{prenom}},\n\nAvez-vous pu regarder notre proposition ? Si un point doit être ajusté, dites-le-moi : l’objectif est que ce soit rentable pour vous aussi.\n\nBien cordialement,",
            'why' => 'Rappel de l’équilibre gagnant-gagnant.',
        ],
        [
            'subject' => 'Borne installée chez [à compléter : nom du client]',
            'body' => "Bonjour {{prenom}},\n\nLa borne est installée chez [à compléter : nom du client] depuis le [à compléter : date]. Le client sait que la recommandation vient de {{entreprise}}.\n\nOn officialise notre collaboration ?\n\nBien cordialement,",
            'why' => 'Le partenaire garde le crédit auprès de son client.',
        ],
        [
            'subject' => 'Bienvenue parmi nos partenaires',
            'body' => "Bonjour {{prenom}},\n\nMerci pour votre confiance. Pour nous transmettre un client : [à compléter : e-mail ou téléphone dédié].\n\nVoulez-vous une fiche d’une page sur la recharge à remettre à vos clients ?\n\nBien cordialement,",
            'why' => 'Fournir un support de vente facilite la recommandation.',
        ],
        [
            'subject' => 'Le point du mois',
            'body' => "Bonjour {{prenom}},\n\nLe point du mois :\n- clients transmis : [à compléter] ;\n- chantiers réalisés : [à compléter] ;\n- commission : [à compléter].\n\nDes chantiers prévus le mois prochain où nous pouvons aider ?\n\nBien cordialement,",
            'why' => 'Suivi transparent et proposition d’aide.',
        ],
    ],
];
