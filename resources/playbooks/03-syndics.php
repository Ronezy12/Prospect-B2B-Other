<?php

// Fiche secteur : syndics de copropriété.
return [
    'slug' => 'syndics',
    'name' => 'Syndics de copropriété',
    'icon' => 'building',
    'sectors' => ['Syndics de copropriété'],
    'pitch' => 'Les copropriétaires demandent de plus en plus à recharger leur voiture au parking, et les parties communes vieillissent. On apporte au syndic des devis clairs et un électricien qui répond.',
    'decider' => 'Le gestionnaire de copropriété qui suit les immeubles, et le directeur du cabinet pour un partenariat global. Le conseil syndical peut aussi être un relais.',
    'need' => [
        'Les demandes de recharge au parking (droit à la prise) se multiplient.',
        'Les parties communes ont besoin d’entretien : éclairage, minuteries, interphones, colonnes électriques.',
        'Le gestionnaire doit présenter des devis comparables en assemblée générale.',
        'Il cherche des artisans réactifs pour les dépannages.',
    ],
    'offer' => [
        'Étude et devis pour la recharge en parking collectif ou individuel.',
        'Rénovation de l’éclairage des parties communes (détecteurs, LED).',
        'Dépannages et petits travaux avec un délai d’intervention annoncé.',
        'Devis rédigés pour être présentés en assemblée générale.',
    ],
    'gain' => [
        'Des réponses rapides aux demandes des copropriétaires.',
        'Des devis prêts pour l’assemblée générale, sans relances.',
        'Moins d’appels de copropriétaires mécontents.',
        'Un prestataire fiable sur tout le portefeuille d’immeubles.',
    ],
    'deal' => [
        'Pas de commission (le syndic doit rester neutre vis-à-vis des copropriétaires) : l’intérêt du syndic, c’est le service et la réactivité.',
        'Délai de réponse garanti : devis sous 7 jours ouvrés, dépannage sous [à définir].',
        'Tarifs clairs et stables sur l’année pour les petites interventions.',
        'Un compte rendu après chaque intervention.',
    ],
    'questions' => [
        'Combien d’immeubles gérez-vous, et combien ont un parking ?',
        'Recevez-vous des demandes de bornes de recharge ? Combien par an ?',
        'Comment traitez-vous ces demandes aujourd’hui ?',
        'Avez-vous un électricien attitré ? Êtes-vous satisfait de ses délais ?',
        'Quand ont lieu vos prochaines assemblées générales ?',
        'Qui choisit les prestataires : vous, le directeur, le conseil syndical ?',
    ],
    'objections' => [
        ['q' => 'Nous mettons toujours plusieurs entreprises en concurrence.', 'a' => 'C’est normal. Nous demandons simplement à faire partie des entreprises consultées, avec des devis faciles à comparer.'],
        ['q' => 'La recharge en copropriété, c’est compliqué.', 'a' => 'C’est pour cela que nous préparons un dossier clair : solutions possibles, coûts, et ce qu’il faut faire voter.'],
        ['q' => 'Nous avons déjà nos prestataires.', 'a' => 'Un deuxième électricien fiable est utile pour les urgences et les périodes chargées. Essayez-nous sur un petit dossier.'],
    ],
    'check' => [
        'Ne proposez pas de commission à un syndic : il doit agir dans l’intérêt des copropriétaires.',
        'Les règles du droit à la prise et les aides à la recharge en copropriété évoluent : vérifiez-les avant de les citer.',
        'Pour les bornes de plus de 3,7 kW, la qualification IRVE est nécessaire.',
    ],
    'emails' => [
        [
            'subject' => 'Demandes de bornes dans vos copropriétés',
            'body' => "Bonjour {{prenom}},\n\nDe plus en plus de copropriétaires demandent à recharger leur voiture au parking, et ces demandes arrivent sur votre bureau.\n\nNous sommes électriciens à {{ville}}. Pour les immeubles gérés par {{entreprise}}, nous préparons des devis clairs, prêts à être présentés en assemblée générale.\n\nRecevez-vous déjà ce type de demandes ?\n\nBien cordialement,",
            'why' => 'Le sujet est d’actualité pour tous les syndics. On met en avant ce qui leur fait gagner du temps : un devis prêt pour l’AG.',
        ],
        [
            'subject' => 'Re : demandes de bornes dans vos copropriétés',
            'body' => "Bonjour {{prenom}},\n\nJe reviens vers vous avec une autre idée : l’éclairage des parties communes. Remplacer de vieilles minuteries par des détecteurs et des LED réduit souvent la facture d’électricité de l’immeuble, un argument apprécié en assemblée.\n\nNous pouvons visiter un de vos immeubles et vous remettre un devis, sans engagement.\n\nAvez-vous un immeuble en tête ?\n\nBien cordialement,",
            'why' => 'Deuxième angle utile si la recharge n’est pas encore un sujet : les économies sur les parties communes.',
        ],
        [
            'subject' => 'Une réponse en un chiffre ?',
            'body' => "Bonjour {{prenom}},\n\nPour ne pas vous relancer inutilement, une réponse en un chiffre me suffit :\n1 — intéressé, appelez-moi\n2 — recontactez-moi avant la prochaine saison d’assemblées\n3 — pas pour nous\n\nMerci et belle journée,",
            'why' => 'Le choix 2 est adapté au rythme des syndics, qui préparent leurs AG à des périodes précises.',
        ],
        [
            'subject' => 'Notre rendez-vous du [à compléter : date]',
            'body' => "Bonjour {{prenom}},\n\nJe vous confirme notre rendez-vous le [à compléter : date et heure].\n\nSi vous avez une demande de borne ou un problème électrique en attente dans un immeuble, apportez le dossier : nous verrons comment le traiter rapidement.\n\nÀ bientôt,",
            'why' => 'Partir d’un vrai dossier en attente rend le rendez-vous utile immédiatement.',
        ],
        [
            'subject' => 'Suite à notre échange',
            'body' => "Bonjour {{prenom}},\n\nMerci pour votre temps. J’ai retenu :\n- [à compléter : nombre] immeubles, dont [à compléter] avec parking ;\n- demandes en attente : [à compléter] ;\n- prochaines assemblées : [à compléter : période].\n\nJe vous envoie une proposition de fonctionnement d’ici [à compléter : date]. Ai-je bien résumé ?\n\nBien cordialement,",
            'why' => 'Noter le calendrier des AG permet de proposer les bons devis au bon moment.',
        ],
        [
            'subject' => 'Notre proposition pour vos immeubles',
            'body' => "Bonjour {{prenom}},\n\nVoici ce que nous proposons à {{entreprise}} :\n\n1. Devis sous 7 jours ouvrés après visite, rédigés pour être présentés en AG.\n2. Dépannages : intervention sous [à compléter : délai].\n3. Tarifs fixes sur l’année pour les petites interventions courantes.\n4. Compte rendu envoyé après chaque intervention.\n\nPour commencer, voulez-vous nous confier un premier immeuble ?\n\nBien cordialement,",
            'why' => 'Pas de commission avec un syndic : on vend la réactivité et la clarté. On finit sur un premier immeuble test.',
        ],
        [
            'subject' => 'Re : notre proposition',
            'body' => "Bonjour {{prenom}},\n\nAvez-vous eu le temps de regarder notre proposition ? Si un délai ou un tarif doit être ajusté, dites-le-moi.\n\nVoulez-vous qu’on se cale 5 minutes au téléphone ?\n\nBien cordialement,",
            'why' => 'Une relance courte qui invite à poser ses conditions.',
        ],
        [
            'subject' => 'Intervention terminée — [à compléter : adresse]',
            'body' => "Bonjour {{prenom}},\n\nL’intervention à [à compléter : adresse de l’immeuble] est terminée : [à compléter : travaux réalisés].\n\nVous trouverez ci-dessous le compte rendu à transmettre au conseil syndical si besoin.\n\n[à compléter : compte rendu court]\n\nPouvons-nous continuer sur vos autres immeubles ?\n\nBien cordialement,",
            'why' => 'Un compte rendu prêt à transférer fait gagner du temps au gestionnaire : c’est exactement ce qu’il cherche.',
        ],
        [
            'subject' => 'Bienvenue — votre contact électricité',
            'body' => "Bonjour {{prenom}},\n\nMerci pour votre confiance. Pour toutes vos demandes :\n- devis et travaux : [à compléter : e-mail dédié] ;\n- urgences : [à compléter : téléphone].\n\nVoulez-vous que je présente notre fonctionnement à vos collègues gestionnaires ?\n\nBien cordialement,",
            'why' => 'Les autres gestionnaires du cabinet sont autant de nouvelles sources de chantiers.',
        ],
        [
            'subject' => 'Le point du mois',
            'body' => "Bonjour {{prenom}},\n\nLe point du mois pour vos immeubles :\n- demandes reçues : [à compléter] ;\n- devis envoyés : [à compléter] ;\n- interventions terminées : [à compléter].\n\nY a-t-il des assemblées à venir pour lesquelles je dois préparer des devis ?\n\nBien cordialement,",
            'why' => 'Anticiper les assemblées générales place vos devis à l’ordre du jour.',
        ],
    ],
];
