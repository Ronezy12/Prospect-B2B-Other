<?php

// Fiche secteur : hôtels, restaurants et commerces.
return [
    'slug' => 'hotels-restaurants',
    'name' => 'Hôtels, restaurants et commerces',
    'icon' => 'utensils-crossed',
    'sectors' => ['Restaurants et commerces'],
    'pitch' => 'Une panne en plein service coûte cher, et les clients en voiture électrique cherchent où recharger. On assure le dépannage rapide, la mise en valeur par l’éclairage et les bornes pour attirer des clients.',
    'decider' => 'Le gérant ou le propriétaire. Dans les hôtels de chaîne : le directeur de l’établissement.',
    'need' => [
        'Dépannage rapide : une panne en plein service fait perdre du chiffre d’affaires.',
        'Éclairage qui met en valeur la salle, la vitrine ou la terrasse.',
        'Bornes de recharge pour attirer les clients en voiture électrique (hôtels, restaurants avec parking).',
        'Installation sûre et contrôlée (établissements recevant du public).',
    ],
    'offer' => [
        'Dépannage prioritaire avec délai d’intervention annoncé.',
        'Rénovation de l’éclairage intérieur, de la vitrine et de la terrasse.',
        'Bornes de recharge pour les clients.',
        'Travaux hors des heures d’ouverture pour ne pas gêner l’activité.',
    ],
    'gain' => [
        'Moins de risque de fermer à cause d’une panne.',
        'Un établissement plus accueillant.',
        'De nouveaux clients grâce à la recharge.',
    ],
    'deal' => [
        'Client direct : installation puis contrat d’entretien avec dépannage prioritaire.',
        'Travaux planifiés hors des heures d’ouverture.',
        'Bonus : demander une recommandation auprès des commerçants voisins.',
    ],
    'questions' => [
        'Avez-vous déjà eu une panne pendant le service ? Combien de temps pour être dépanné ?',
        'Votre éclairage met-il bien en valeur la salle et la vitrine ?',
        'Avez-vous un parking ? Des clients demandent-ils à recharger ?',
        'Qui s’occupe de l’électricité aujourd’hui ?',
        'Quels sont vos jours et heures de fermeture ?',
        'Qui décide des travaux ?',
    ],
    'objections' => [
        ['q' => 'Je n’ai pas le temps de faire des travaux.', 'a' => 'Nous intervenons sur vos jours de fermeture ou tôt le matin : votre activité n’est pas interrompue.'],
        ['q' => 'J’ai déjà un électricien.', 'a' => 'Est-il disponible en urgence un samedi soir ? Avoir un deuxième contact prioritaire, c’est une assurance.'],
    ],
    'check' => [
        'Les établissements recevant du public ont des obligations de vérification : ne promettez rien sans vérifier la catégorie de l’établissement.',
    ],
    'emails' => [
        [
            'subject' => 'Une panne en plein service ?',
            'body' => "Bonjour {{prenom}},\n\nUne panne électrique en plein service, c’est une soirée perdue.\n\nNous sommes électriciens à {{ville}}. Nous proposons à {{entreprise}} un dépannage prioritaire, et nous faisons nos travaux pendant vos jours de fermeture pour ne jamais gêner votre activité.\n\nAvez-vous déjà vécu ce genre de situation ?\n\nBien cordialement,",
            'why' => 'Une peur concrète de tout restaurateur ; l’argument « jours de fermeture » lève l’objection du temps.',
        ],
        [
            'subject' => 'Re : une panne en plein service',
            'body' => "Bonjour {{prenom}},\n\nAutre idée : l’éclairage. Une vitrine et une terrasse bien éclairées attirent l’œil des passants, et des LED consomment beaucoup moins.\n\nJe peux passer un matin vous faire quelques propositions, sans engagement. Quel jour vous arrange ?\n\nBien cordialement,",
            'why' => 'Deuxième angle commercial : attirer des clients.',
        ],
        [
            'subject' => 'Un chiffre suffit',
            'body' => "Bonjour {{prenom}},\n\nPour ne pas vous déranger inutilement :\n1 — intéressé, passez me voir\n2 — plus tard, après la saison\n3 — pas pour nous\n\nMerci et bon service,",
            'why' => 'Le choix 2 respecte la saisonnalité de la restauration.',
        ],
        [
            'subject' => 'Notre rendez-vous du [à compléter : date]',
            'body' => "Bonjour {{prenom}},\n\nJe vous confirme mon passage le [à compléter : date et heure], en dehors de votre service.\n\nJe regarderai rapidement votre tableau électrique et votre éclairage pour vous faire des propositions concrètes.\n\nÀ bientôt,",
            'why' => 'Venir en dehors du service montre qu’on comprend leur métier.',
        ],
        [
            'subject' => 'Suite à mon passage',
            'body' => "Bonjour {{prenom}},\n\nMerci pour votre accueil. J’ai retenu :\n- [à compléter : état de l’installation] ;\n- vos priorités : [à compléter : dépannage, éclairage, recharge] ;\n- vos jours de fermeture : [à compléter].\n\nJe vous envoie ma proposition d’ici [à compléter : date]. Est-ce bien cela ?\n\nBien cordialement,",
            'why' => 'Résumé validé.',
        ],
        [
            'subject' => 'Notre proposition pour {{entreprise}}',
            'body' => "Bonjour {{prenom}},\n\nVoici notre proposition :\n\n1. [à compléter : travaux proposés, ex. éclairage vitrine et terrasse].\n2. Réalisation pendant vos jours de fermeture : [à compléter].\n3. Montant : [à compléter].\n4. Option : contrat d’entretien avec dépannage prioritaire sous [à compléter].\n\nQuelle suite souhaitez-vous donner ?\n\nBien cordialement,",
            'why' => 'Le dépannage prioritaire en option prépare la relation long terme.',
        ],
        [
            'subject' => 'Re : notre proposition',
            'body' => "Bonjour {{prenom}},\n\nAvez-vous pu regarder notre proposition ? Nous pouvons aussi la découper en plusieurs étapes si c’est plus simple pour votre budget.\n\nBien cordialement,",
            'why' => 'Proposer d’étaler lève l’objection budgétaire.',
        ],
        [
            'subject' => 'Travaux terminés',
            'body' => "Bonjour {{prenom}},\n\nLes travaux sont terminés depuis le [à compléter : date] : [à compléter]. Tout est prêt pour votre prochain service.\n\nSi vos voisins commerçants ont besoin d’un électricien, n’hésitez pas à leur donner nos coordonnées.\n\nBien cordialement,",
            'why' => 'Les commerçants se recommandent entre voisins : c’est le bon moment pour le demander.',
        ],
        [
            'subject' => 'Votre numéro de dépannage prioritaire',
            'body' => "Bonjour {{prenom}},\n\nMerci pour votre confiance. En cas de panne : [à compléter : téléphone prioritaire].\n\nGardez-le près de votre tableau électrique ou dans votre téléphone.\n\nBien cordialement,",
            'why' => 'Un numéro prioritaire visible = l’établissement pense à vous à chaque problème.',
        ],
        [
            'subject' => 'Tout se passe bien ?',
            'body' => "Bonjour {{prenom}},\n\nJe prends de vos nouvelles. Tout fonctionne bien ? Avez-vous des projets pour la prochaine saison (terrasse, recharge, aménagement) ?\n\nBien cordialement,",
            'why' => 'Un contact régulier fait émerger les projets de la saison suivante.',
        ],
    ],
];
