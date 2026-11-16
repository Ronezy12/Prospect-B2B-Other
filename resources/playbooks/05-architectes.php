<?php

// Fiche secteur : architectes et maîtres d'œuvre.
return [
    'slug' => 'architectes',
    'name' => 'Architectes et maîtres d’œuvre',
    'icon' => 'drafting-compass',
    'sectors' => ['Architectes et maîtres d’œuvre'],
    'pitch' => 'Sur un chantier, le lot électricité en retard bloque tout le monde. On propose à l’architecte un électricien qui respecte le planning, chiffre clairement et suit les plans.',
    'decider' => 'L’architecte associé ou le gérant du cabinet de maîtrise d’œuvre. Dans les cabinets plus grands : le conducteur de travaux ou l’économiste.',
    'need' => [
        'Trouver des entreprises fiables pour chaque lot, surtout en rénovation.',
        'Recevoir des devis détaillés et comparables dans les délais de consultation.',
        'Un électricien qui respecte le planning et laisse le chantier propre.',
    ],
    'offer' => [
        'Chiffrage détaillé du lot électricité à partir des plans et du descriptif.',
        'Réalisation conforme aux plans, avec un chef de chantier identifié.',
        'Présence aux réunions de chantier quand c’est nécessaire.',
        'Conseil en amont sur les choix techniques (tableau, éclairage, domotique, recharge).',
    ],
    'gain' => [
        'Un lot électricité qui ne retarde pas le chantier.',
        'Des devis rendus à temps pour les consultations.',
        'Des clients satisfaits du résultat, donc des recommandations pour le cabinet.',
    ],
    'deal' => [
        'Pas de commission : relation d’entreprise consultée. L’intérêt de l’architecte, c’est la fiabilité.',
        'Engagement de délai de réponse aux consultations (ex. 10 jours ouvrés).',
        'Respect du planning, avec alerte immédiate en cas d’imprévu.',
        'Visite d’un chantier terminé possible pour juger du travail.',
    ],
    'questions' => [
        'Combien de chantiers de rénovation ou de construction suivez-vous par an ?',
        'Quel type de projets : maisons, appartements, locaux professionnels ?',
        'Comment choisissez-vous les électriciens consultés ?',
        'Avez-vous eu des retards à cause du lot électricité ?',
        'Avez-vous une consultation en cours ou à venir ?',
        'Qui dans le cabinet constitue la liste des entreprises ?',
    ],
    'objections' => [
        ['q' => 'Nous travaillons toujours avec les mêmes entreprises.', 'a' => 'C’est une force. Ajouter un électricien à votre liste vous protège quand votre habituel est complet. Consultez-nous sur un prochain projet pour comparer.'],
        ['q' => 'Les électriciens ne rendent jamais leurs devis à temps.', 'a' => 'Nous nous engageons sur un délai de réponse. Testez-nous sur votre prochaine consultation.'],
    ],
    'check' => [
        'Ayez votre attestation d’assurance décennale à jour : l’architecte la demandera.',
        'Ne promettez pas un délai de chantier sans avoir vu le planning.',
    ],
    'emails' => [
        [
            'subject' => 'Votre lot électricité',
            'body' => "Bonjour {{prenom}},\n\nSur un chantier de rénovation, le lot électricité en retard bloque souvent les autres corps de métier.\n\nNous sommes électriciens à {{ville}} et nous travaillons avec des maîtres d’œuvre : chiffrage détaillé à partir de vos plans, respect du planning, chef de chantier identifié.\n\nAccepteriez-vous de nous consulter sur un prochain projet ?\n\nBien cordialement,",
            'why' => 'On parle de la vraie peur de l’architecte (le retard) et on demande peu : être consulté une fois.',
        ],
        [
            'subject' => 'Re : votre lot électricité',
            'body' => "Bonjour {{prenom}},\n\nPour vous permettre de juger sur pièce, nous pouvons vous faire visiter un chantier que nous avons terminé récemment à {{ville}} ou à proximité.\n\nAvez-vous une consultation en cours ou prévue dans les prochaines semaines ?\n\nBien cordialement,",
            'why' => 'Proposer de voir un chantier fini rassure plus que n’importe quel argument.',
        ],
        [
            'subject' => 'Une réponse en un chiffre ?',
            'body' => "Bonjour {{prenom}},\n\nPour ne pas vous relancer inutilement :\n1 — consultez-moi sur un prochain projet\n2 — recontactez-moi dans 3 mois\n3 — pas pour nous\n\nMerci et bonne journée,",
            'why' => 'Les architectes reçoivent beaucoup de sollicitations : une réponse en un chiffre est respectueuse de leur temps.',
        ],
        [
            'subject' => 'Notre rendez-vous du [à compléter : date]',
            'body' => "Bonjour {{prenom}},\n\nJe vous confirme notre rendez-vous le [à compléter : date et heure].\n\nSi vous avez un projet en cours, n’hésitez pas à partager les plans : je peux vous donner un premier avis technique.\n\nÀ bientôt,",
            'why' => 'Offrir un avis technique sur un vrai projet démontre la compétence dès le rendez-vous.',
        ],
        [
            'subject' => 'Suite à notre échange',
            'body' => "Bonjour {{prenom}},\n\nMerci pour cet échange. J’ai retenu :\n- environ [à compléter] chantiers par an, surtout [à compléter : type] ;\n- vos attentes : [à compléter : ex. respect du planning, devis détaillés] ;\n- prochaine consultation : [à compléter].\n\nJe reste disponible pour cette consultation. Ai-je bien résumé ?\n\nBien cordialement,",
            'why' => 'Rappeler la prochaine consultation garde l’opportunité concrète.',
        ],
        [
            'subject' => 'Nos engagements pour vos chantiers',
            'body' => "Bonjour {{prenom}},\n\nVoici ce que nous vous proposons :\n\n1. Réponse à vos consultations sous [à compléter : délai] avec un chiffrage détaillé poste par poste.\n2. Un chef de chantier identifié, joignable directement.\n3. Respect du planning, avec alerte immédiate en cas d’imprévu.\n4. Attestation d’assurance décennale fournie à chaque consultation.\n\nPouvez-vous nous ajouter à votre liste d’entreprises consultées ?\n\nBien cordialement,",
            'why' => 'Avec un architecte, le partenariat, c’est entrer dans la liste des entreprises consultées.',
        ],
        [
            'subject' => 'Re : nos engagements',
            'body' => "Bonjour {{prenom}},\n\nAvez-vous pu lire nos engagements ? Y a-t-il un point qui compte pour vous et que je n’ai pas mentionné ?\n\nBien cordialement,",
            'why' => 'Question ouverte qui fait ressortir le critère de choix réel.',
        ],
        [
            'subject' => 'Chantier [à compléter : nom] : lot électricité terminé',
            'body' => "Bonjour {{prenom}},\n\nLe lot électricité du chantier [à compléter : nom ou adresse] est terminé le [à compléter : date], dans le planning prévu.\n\n[à compléter : points particuliers, réserves levées]\n\nMerci pour votre confiance. Nous serions ravis d’être consultés sur vos prochains projets.\n\nBien cordialement,",
            'why' => 'Souligner le respect du planning, c’est rappeler la promesse tenue.',
        ],
        [
            'subject' => 'Merci — à vos côtés pour les prochains projets',
            'body' => "Bonjour {{prenom}},\n\nMerci de nous avoir ajoutés à votre liste d’entreprises. Pour nous envoyer une consultation : [à compléter : e-mail dédié].\n\nSi vos collègues du cabinet cherchent aussi un électricien, je me tiens à leur disposition.\n\nBien cordialement,",
            'why' => 'Ouvrir aux autres architectes du cabinet multiplie les consultations.',
        ],
        [
            'subject' => 'Des projets pour les prochains mois ?',
            'body' => "Bonjour {{prenom}},\n\nJe prends de vos nouvelles. Avez-vous des projets qui démarrent dans les prochains mois et pour lesquels nous pourrions réserver une équipe ?\n\nBien cordialement,",
            'why' => 'Rappel régulier et utile : réserver une équipe est un vrai service pour l’architecte.',
        ],
    ],
];
