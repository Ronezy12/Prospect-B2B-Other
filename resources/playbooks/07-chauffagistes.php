<?php

// Fiche secteur : plombiers-chauffagistes et installateurs de pompes à chaleur.
return [
    'slug' => 'chauffagistes',
    'name' => 'Plombiers-chauffagistes',
    'icon' => 'flame',
    'sectors' => ['Plombiers-chauffagistes'],
    'pitch' => 'Une pompe à chaleur ou un chauffe-eau thermodynamique demande souvent une adaptation électrique. On s’occupe de la partie électricité pour que le chauffagiste se concentre sur son métier.',
    'decider' => 'Le gérant de l’entreprise de plomberie-chauffage, ou le responsable technique dans les entreprises plus grandes.',
    'need' => [
        'Alimentation électrique dédiée pour pompes à chaleur, chaudières, chauffe-eau.',
        'Tableau électrique parfois trop ancien ou trop juste pour le nouvel équipement.',
        'Ne pas perdre de temps sur une partie qui n’est pas leur cœur de métier.',
    ],
    'offer' => [
        'Création des lignes dédiées et des protections pour les équipements de chauffage.',
        'Mise à niveau du tableau si nécessaire.',
        'Intervention coordonnée avec la date d’installation du chauffagiste.',
        'Échange de clients : nous leur envoyons aussi nos clients qui ont un projet de chauffage.',
    ],
    'gain' => [
        'Des installations terminées sans attendre un électricien.',
        'Une partie électrique faite dans les règles, sous la responsabilité d’un électricien.',
        'Des clients envoyés en retour : le partenariat marche dans les deux sens.',
    ],
    'deal' => [
        'Échange de recommandations dans les deux sens, sans commission, ou commission réciproque identique.',
        'Délai d’intervention aligné sur leurs dates de pose.',
        'Tarifs connus d’avance pour les interventions courantes (ligne dédiée, protection).',
    ],
    'questions' => [
        'Combien de pompes à chaleur ou d’équipements installez-vous par mois ?',
        'Qui fait la partie électrique aujourd’hui ?',
        'Avez-vous déjà dû attendre un électricien pour terminer une installation ?',
        'Vos clients vous demandent-ils des travaux électriques en plus ?',
        'Seriez-vous intéressé par des clients que nous vous enverrions ?',
        'Qui décide dans l’entreprise ?',
    ],
    'objections' => [
        ['q' => 'Nous faisons nous-mêmes le branchement.', 'a' => 'Pour le raccordement de l’appareil, bien sûr. Pour créer une ligne, changer des protections ou reprendre le tableau, un électricien sécurise votre installation.'],
        ['q' => 'Nous avons déjà un électricien.', 'a' => 'Gardez-le. Nous pouvons être le relais quand il est complet, et surtout vous envoyer nos propres clients en chauffage.'],
    ],
    'check' => [
        'Mettez l’échange de clients par écrit, même simplement, pour que chacun s’y retrouve.',
    ],
    'emails' => [
        [
            'subject' => 'La partie électrique de vos pompes à chaleur',
            'body' => "Bonjour {{prenom}},\n\nUne pompe à chaleur bien posée, mais un tableau électrique trop juste : l’installation attend l’électricien.\n\nNous sommes électriciens à {{ville}}. Nous proposons à {{entreprise}} de prendre en charge la partie électrique de vos installations, calée sur vos dates de pose. Et nos clients qui ont un projet de chauffage, nous vous les envoyons.\n\nCela vous intéresse-t-il ?\n\nBien cordialement,",
            'why' => 'On propose un échange dans les deux sens : c’est le meilleur accroche entre artisans.',
        ],
        [
            'subject' => 'Re : la partie électrique de vos pompes à chaleur',
            'body' => "Bonjour {{prenom}},\n\nJe reviens vers vous : nos clients nous demandent régulièrement un chauffagiste de confiance. Nous préférons les envoyer à une entreprise avec qui nous travaillons déjà.\n\nPuis-je vous appeler 10 minutes pour voir comment travailler ensemble ?\n\nBien cordialement,",
            'why' => 'On met en avant ce que le chauffagiste reçoit (des clients) : très motivant pour un artisan.',
        ],
        [
            'subject' => 'On en reste là ?',
            'body' => "Bonjour {{prenom}},\n\nUne réponse en un chiffre :\n1 — intéressé, appelez-moi\n2 — plus tard, dans 3 mois\n3 — pas pour nous\n\nMerci et bonne journée,",
            'why' => 'Clôture simple.',
        ],
        [
            'subject' => 'Notre rendez-vous du [à compléter : date]',
            'body' => "Bonjour {{prenom}},\n\nJe vous confirme notre rendez-vous le [à compléter : date et heure].\n\nNous pourrons parler de vos prochaines installations et des clients que nous pouvons vous envoyer.\n\nÀ bientôt,",
            'why' => 'Rappeler l’échange de clients dès la confirmation.',
        ],
        [
            'subject' => 'Suite à notre échange',
            'body' => "Bonjour {{prenom}},\n\nMerci pour cet échange. J’ai retenu :\n- environ [à compléter] installations par mois ;\n- la partie électrique est faite par [à compléter] ;\n- vous êtes intéressé par [à compléter : ex. des clients en pompe à chaleur].\n\nJe vous propose un fonctionnement simple d’ici [à compléter : date]. Est-ce bien cela ?\n\nBien cordialement,",
            'why' => 'Résumé validé.',
        ],
        [
            'subject' => 'Proposition : travaillons ensemble',
            'body' => "Bonjour {{prenom}},\n\nNotre proposition :\n\n1. Vous nous envoyez la partie électrique de vos chantiers ; nous intervenons avant ou le jour de votre pose.\n2. Nous vous envoyons nos clients qui ont un projet de chauffage ou de plomberie.\n3. Tarifs connus d’avance pour les interventions courantes : [à compléter].\n4. [à compléter : sans commission, ou commission réciproque].\n\nOn commence par votre prochain chantier ?\n\nBien cordialement,",
            'why' => 'Équilibre affiché : chacun envoie des clients à l’autre.',
        ],
        [
            'subject' => 'Re : travaillons ensemble',
            'body' => "Bonjour {{prenom}},\n\nAvez-vous pu réfléchir à notre proposition ? J’ai justement un client qui cherche [à compléter : ex. un chauffagiste pour une pompe à chaleur] : voulez-vous que je lui donne vos coordonnées ?\n\nBien cordialement,",
            'why' => 'Envoyer un client en premier est le moyen le plus fort de déclencher le partenariat.',
        ],
        [
            'subject' => 'Chantier [à compléter : nom] : électricité terminée',
            'body' => "Bonjour {{prenom}},\n\nLa partie électrique du chantier [à compléter : nom ou adresse] est terminée : [à compléter : travaux]. Vous pouvez mettre en service.\n\nMerci pour ce premier chantier ensemble. On continue ?\n\nBien cordialement,",
            'why' => 'Le message dit ce que le partenaire veut savoir : il peut mettre en service.',
        ],
        [
            'subject' => 'Bienvenue — on travaille ensemble',
            'body' => "Bonjour {{prenom}},\n\nRavi de travailler avec vous. Pour nous envoyer un chantier : [à compléter : e-mail ou téléphone].\n\nDe notre côté, nous donnerons vos coordonnées à nos clients en chauffage. Avez-vous une plaquette ou une carte à nous transmettre ?\n\nBien cordialement,",
            'why' => 'Demander leur plaquette montre qu’on va vraiment les recommander.',
        ],
        [
            'subject' => 'Le point du mois',
            'body' => "Bonjour {{prenom}},\n\nLe point du mois :\n- chantiers que vous nous avez confiés : [à compléter] ;\n- clients que nous vous avons envoyés : [à compléter].\n\nDes installations prévues le mois prochain ?\n\nBien cordialement,",
            'why' => 'Montrer l’équilibre des échanges entretient la confiance.',
        ],
    ],
];
