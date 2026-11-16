<?php

// Fiche secteur : agences immobilières.
return [
    'slug' => 'agences-immobilieres',
    'name' => 'Agences immobilières',
    'icon' => 'key-round',
    'sectors' => ['Agences immobilières'],
    'pitch' => 'Un diagnostic électrique défavorable fait peur aux acheteurs et ralentit les ventes. On intervient vite pour remettre le logement en sécurité, avec un devis clair que l’agence peut montrer.',
    'decider' => 'Le directeur ou la directrice d’agence. Dans les réseaux, le gérant de l’agence (franchisé). Pour la location : le responsable gestion locative.',
    'need' => [
        'Le diagnostic électrique, obligatoire pour vendre ou louer un logement ancien, signale souvent des anomalies.',
        'Les acheteurs négocient fort ou hésitent quand ils voient ces anomalies.',
        'Les propriétaires bailleurs doivent remettre en état avant une nouvelle location.',
        'L’agence a besoin d’un artisan qui répond vite et donne un devis lisible.',
    ],
    'offer' => [
        'Devis de mise en sécurité à partir du diagnostic, sous quelques jours.',
        'Travaux de remise en sécurité : tableau, prises de terre, protections, prises et éclairage.',
        'Intervention entre deux locataires pour la gestion locative.',
        'Un interlocuteur unique qui tient l’agence informée de l’avancement.',
    ],
    'gain' => [
        'Des ventes qui se débloquent : un devis chiffré rassure l’acheteur et limite la négociation.',
        'Un service en plus pour les vendeurs et les bailleurs, qui distingue l’agence.',
        'Une commission d’apport ou un tarif privilégié pour ses clients.',
        'Moins de relances et d’appels à gérer pour les négociateurs.',
    ],
    'deal' => [
        'Commission fixe par chantier signé et réalisé, ou tarif préférentiel pour les clients de l’agence.',
        'Devis envoyé sous 5 jours ouvrés après visite.',
        'Copie du devis et de l’attestation de travaux à l’agence (avec l’accord du client).',
        'Point mensuel sur les dossiers en cours.',
    ],
    'questions' => [
        'Combien de ventes ou de locations gérez-vous par mois ?',
        'À quelle fréquence le diagnostic électrique bloque-t-il ou fait-il baisser un prix ?',
        'Que proposez-vous aujourd’hui à un vendeur dont le diagnostic est mauvais ?',
        'Avez-vous un électricien habituel ? Répond-il assez vite ?',
        'Faites-vous de la gestion locative ? Combien de lots ?',
        'Qui décide du choix des artisans recommandés par l’agence ?',
    ],
    'objections' => [
        ['q' => 'Nous ne recommandons pas d’artisans, par prudence.', 'a' => 'C’est compréhensible. Vous pouvez simplement transmettre nos coordonnées parmi d’autres : le client reste libre de choisir, et nous vous tenons informé.'],
        ['q' => 'Nous avons déjà un électricien.', 'a' => 'Gardez-le ! Un deuxième contact est utile quand il est complet ou pour les urgences avant une signature.'],
        ['q' => 'Les vendeurs ne veulent pas faire de travaux.', 'a' => 'Justement : un devis chiffré suffit souvent à rassurer l’acheteur, même si les travaux sont faits après la vente.'],
    ],
    'check' => [
        'Ne promettez pas qu’un diagnostic deviendra « favorable » : seul le diagnostiqueur le constate.',
        'Transmettre un devis à l’agence nécessite l’accord du client.',
        'Rédigez la commission dans une convention d’apport d’affaires écrite.',
    ],
    'emails' => [
        [
            'subject' => 'Diagnostic électrique défavorable : une solution rapide',
            'body' => "Bonjour {{prenom}},\n\nUn diagnostic électrique avec des anomalies, c’est souvent un acheteur qui hésite ou qui négocie fort.\n\nNous sommes électriciens à {{ville}}. Pour les clients de {{entreprise}}, nous faisons un devis de mise en sécurité rapidement à partir du diagnostic : de quoi rassurer l’acheteur avec un chiffre clair.\n\nEst-ce une situation que vous rencontrez souvent ?\n\nBien cordialement,",
            'why' => 'On parle d’un problème que tous les agents connaissent. La question finale est facile : oui ou non.',
        ],
        [
            'subject' => 'Re : diagnostic électrique défavorable',
            'body' => "Bonjour {{prenom}},\n\nJe me permets de revenir vers vous. Concrètement, voici ce que nous proposons à {{entreprise}} :\n- vous nous transmettez le diagnostic (avec l’accord du client) ;\n- nous visitons et envoyons un devis clair sous 5 jours ;\n- vous pouvez le montrer à l’acheteur.\n\nPour vous, aucun coût, et un service en plus pour vos vendeurs.\n\nPuis-je vous appeler 10 minutes cette semaine ?\n\nBien cordialement,",
            'why' => 'On montre le fonctionnement en 3 étapes : le lecteur visualise tout de suite comment ça marche pour lui.',
        ],
        [
            'subject' => 'Dernier message de ma part',
            'body' => "Bonjour {{prenom}},\n\nJe ne veux pas encombrer votre boîte. Une réponse en un chiffre me suffit :\n1 — intéressé, appelez-moi\n2 — pas maintenant, recontactez-moi dans 3 mois\n3 — pas pour nous\n\nMerci et belle journée,",
            'why' => 'Message de clôture très court : beaucoup de gens répondent enfin quand c’est aussi simple.',
        ],
        [
            'subject' => 'Notre rendez-vous du [à compléter : date]',
            'body' => "Bonjour {{prenom}},\n\nJe vous confirme notre rendez-vous le [à compléter : date et heure] à l’agence {{entreprise}}.\n\nSi vous avez un dossier en cours avec un diagnostic électrique compliqué, apportez-le : nous verrons ensemble ce qu’il est possible de faire.\n\nÀ bientôt,",
            'why' => 'Proposer de travailler sur un vrai dossier transforme le rendez-vous en aide concrète.',
        ],
        [
            'subject' => 'Suite à notre échange',
            'body' => "Bonjour {{prenom}},\n\nMerci pour votre accueil. J’ai retenu :\n- environ [à compléter : nombre] ventes ou locations par mois ;\n- le diagnostic électrique pose problème [à compléter : fréquence] ;\n- votre attente : [à compléter : ex. des devis rapides et lisibles].\n\nJe vous envoie notre proposition d’ici [à compléter : date]. Ai-je bien résumé ?\n\nBien cordialement,",
            'why' => 'Faire valider le résumé engage la personne et évite les malentendus.',
        ],
        [
            'subject' => 'Proposition de partenariat — {{entreprise}}',
            'body' => "Bonjour {{prenom}},\n\nVoici notre proposition :\n\n1. Vous nous transmettez le contact du client et son diagnostic (avec son accord).\n2. Nous le rappelons sous 48 h et visitons le logement.\n3. Devis clair sous 5 jours ouvrés, copie à l’agence si le client est d’accord.\n4. Pour chaque chantier réalisé : [à compléter : commission ou tarif préférentiel].\n\nSans engagement et sans frais pour l’agence.\n\nOn commence avec votre prochain dossier ?\n\nBien cordialement,",
            'why' => 'Une offre concrète et sans risque, qui finit sur une action immédiate.',
        ],
        [
            'subject' => 'Re : proposition de partenariat',
            'body' => "Bonjour {{prenom}},\n\nAvez-vous pu regarder notre proposition ? Si quelque chose ne convient pas (délai, rémunération, façon de transmettre les dossiers), dites-le-moi : nous l’adaptons volontiers.\n\nQuand pourrions-nous en parler 5 minutes ?\n\nBien cordialement,",
            'why' => 'On ouvre la porte à la négociation plutôt qu’au refus.',
        ],
        [
            'subject' => 'Premier dossier terminé',
            'body' => "Bonjour {{prenom}},\n\nLe chantier chez [à compléter : nom du client] est terminé depuis le [à compléter : date] : [à compléter : travaux réalisés].\n\nLe client a reçu son attestation de travaux, qu’il peut présenter à l’acheteur ou au locataire.\n\nOn officialise notre collaboration ? Je vous envoie la convention.\n\nBien cordialement,",
            'why' => 'Le résultat concret du premier dossier est le meilleur argument pour signer.',
        ],
        [
            'subject' => 'Bienvenue parmi nos partenaires',
            'body' => "Bonjour {{prenom}},\n\nMerci pour votre confiance. Pour vos négociateurs, le fonctionnement est simple :\n- un diagnostic électrique compliqué ? Ils nous envoient le contact du client à [à compléter : e-mail ou téléphone dédié] ;\n- nous rappelons sous 48 h et les tenons informés.\n\nJe peux venir le présenter en 10 minutes à votre réunion d’équipe. Quel jour vous convient ?\n\nBien cordialement,",
            'why' => 'Ce sont les négociateurs qui envoient les dossiers : il faut les convaincre aussi.',
        ],
        [
            'subject' => 'Le point du mois avec {{entreprise}}',
            'body' => "Bonjour {{prenom}},\n\nLe point du mois :\n- dossiers transmis : [à compléter] ;\n- devis envoyés : [à compléter] ;\n- chantiers réalisés : [à compléter] ;\n- commission : [à compléter].\n\nAvez-vous des ventes ou des relocations à venir pour lesquelles je peux anticiper ?\n\nMerci pour votre confiance,",
            'why' => 'Transparence et anticipation : le partenaire pense à vous à chaque nouveau mandat.',
        ],
    ],
];
