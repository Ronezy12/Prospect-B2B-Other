<?php

// Fiche secteur : concessionnaires automobiles.
return [
    'slug' => 'concessionnaires',
    'name' => 'Concessionnaires automobiles',
    'icon' => 'car',
    'sectors' => ['Concessionnaires automobiles'],
    'pitch' => 'Chaque voiture électrique vendue est un client qui a besoin d’une borne ou d’une prise renforcée chez lui. On s’en occupe pour la concession, vite et proprement.',
    'decider' => 'Le directeur de la concession, le chef des ventes ou le responsable après-vente. Dans les grands groupes : le responsable « véhicules électriques » ou le directeur de plaque.',
    'need' => [
        'Les acheteurs de véhicules électriques ou hybrides rechargeables veulent recharger chez eux dès la livraison.',
        'Une prise classique n’est pas faite pour recharger tous les jours : il faut une prise renforcée ou une borne, et souvent vérifier le tableau électrique.',
        'Le vendeur ne sait pas répondre aux questions techniques, et un client sans solution de recharge peut renoncer à l’achat.',
    ],
    'offer' => [
        'Visite technique au domicile du client sous quelques jours, avec un devis clair.',
        'Pose de prise renforcée ou de borne murale, mise à niveau du tableau si nécessaire.',
        'Installation possible pour la concession elle-même : bornes pour l’atelier, les véhicules de démonstration et les clients.',
        'Un seul interlocuteur pour les vendeurs, avec un retour après chaque intervention.',
    ],
    'gain' => [
        'Un argument de vente concret : « la recharge est réglée avant la livraison ».',
        'Moins de ventes perdues à cause de la question de la recharge.',
        'Une commission ou une remise pour ses clients, au choix de la concession.',
        'Des clients satisfaits qui reviennent à l’atelier.',
    ],
    'deal' => [
        'Commission fixe par installation réalisée (pas par devis), versée chaque mois.',
        'Ou, si la concession préfère : remise réservée à ses clients, qu’elle présente comme un avantage.',
        'Délai garanti : prise de contact avec le client sous 48 h ouvrées.',
        'Point mensuel : clients envoyés, chantiers faits, commissions dues.',
    ],
    'questions' => [
        'Combien de véhicules électriques ou hybrides rechargeables vendez-vous par mois ?',
        'Que répondent vos vendeurs quand un client demande comment recharger chez lui ?',
        'Avez-vous déjà un installateur ? Êtes-vous satisfait des délais ?',
        'La marque vous impose-t-elle un installateur partenaire ?',
        'Qui décide de ce type de partenariat dans la concession ?',
        'Vos propres places de parking sont-elles équipées de bornes ?',
    ],
    'objections' => [
        ['q' => 'La marque a déjà un partenaire national.', 'a' => 'Très bien. Ces partenaires sont souvent loin et lents. Nous pouvons être la solution locale pour l’occasion, les autres marques, ou quand le partenaire national ne répond pas assez vite.'],
        ['q' => 'Mes vendeurs n’ont pas le temps.', 'a' => 'Ils n’ont qu’une chose à faire : nous transmettre le nom et le téléphone du client. Nous rappelons, nous organisons tout et nous les tenons informés.'],
        ['q' => 'Les clients se débrouillent seuls.', 'a' => 'Certains, oui. Mais un client mal équipé recharge lentement et se plaint de sa voiture. Une bonne installation, c’est un client content de son achat.'],
        ['q' => 'Combien ça me coûte ?', 'a' => 'Rien. Le client paie son installation ; vous touchez une commission ou vous offrez une remise à vos clients.'],
    ],
    'check' => [
        'Pour installer des bornes de plus de 3,7 kW, l’installateur doit être qualifié IRVE : vérifiez votre qualification avant de la mettre en avant.',
        'Les aides et crédits d’impôt pour les bornes évoluent souvent : vérifiez les montants en vigueur avant de les citer.',
        'Rédigez la commission dans une convention d’apport d’affaires écrite.',
    ],
    'emails' => [
        [
            'subject' => 'La recharge de vos clients électriques',
            'body' => "Bonjour {{prenom}},\n\nQuand un client de {{entreprise}} achète une voiture électrique, la première question qui suit est souvent : « Comment je recharge chez moi ? »\n\nNous sommes électriciens à {{ville}} et nous installons des prises renforcées et des bornes à domicile. Votre vendeur nous transmet le contact, nous faisons le reste : visite, devis, pose, et un retour à votre équipe.\n\nEst-ce un sujet que vous rencontrez avec vos clients ?\n\nBien cordialement,",
            'why' => 'On part du problème du client final, pas de notre entreprise. Une seule question fermée à la fin : facile d’y répondre en une ligne.',
        ],
        [
            'subject' => 'Re : la recharge de vos clients électriques',
            'body' => "Bonjour {{prenom}},\n\nJe reviens vers vous avec un chiffre simple : un client qui repart sans solution de recharge, c’est un client qui hésite ou qui reporte son achat.\n\nNotre proposition pour {{entreprise}} : vos vendeurs peuvent dire « la recharge, on s’en occupe ». Pour vous, aucun coût et une commission sur chaque installation réalisée.\n\nPuis-je vous appeler 10 minutes cette semaine pour vous expliquer comment ça marche ?\n\nBien cordialement,",
            'why' => 'Deuxième angle : le gain pour la concession (ventes, commission). On propose un appel court et précis.',
        ],
        [
            'subject' => 'Je ne veux pas vous encombrer',
            'body' => "Bonjour {{prenom}},\n\nJe vous ai écrit deux fois au sujet de la recharge à domicile de vos clients, sans réponse : je suppose que ce n’est pas le bon moment, ou pas la bonne personne.\n\nPour que je sache quoi faire, pouvez-vous simplement me répondre par un chiffre ?\n1 — ça m’intéresse, appelez-moi\n2 — plus tard, recontactez-moi dans 3 mois\n3 — ce n’est pas pour nous\n\nMerci d’avance, et bonne journée.\n\nBien cordialement,",
            'why' => 'Le message de « clôture » obtient souvent le plus de réponses : répondre ne demande qu’un chiffre et la personne se sent respectée.',
        ],
        [
            'subject' => 'Notre rendez-vous du [à compléter : date]',
            'body' => "Bonjour {{prenom}},\n\nMerci pour votre accord. Je vous confirme notre rendez-vous le [à compléter : date et heure] à la concession {{entreprise}}.\n\nPour que ces 20 minutes vous soient utiles, j’aimerais savoir :\n- combien de véhicules électriques vous vendez environ par mois ;\n- comment vos vendeurs gèrent aujourd’hui la question de la recharge.\n\nJe viendrai avec des exemples d’installations et une proposition simple.\n\nÀ bientôt,",
            'why' => 'Confirmer par écrit réduit les rendez-vous oubliés. Les deux questions préparent la découverte et montrent qu’on vient pour eux.',
        ],
        [
            'subject' => 'Suite à notre échange : ce que nous avons retenu',
            'body' => "Bonjour {{prenom}},\n\nMerci pour votre accueil aujourd’hui. Voici ce que j’ai retenu :\n- vous vendez environ [à compléter : nombre] véhicules électriques par mois ;\n- aujourd’hui, [à compléter : situation actuelle, ex. les clients se débrouillent seuls] ;\n- votre priorité : [à compléter : ex. rassurer les clients avant la livraison].\n\nComme convenu, je vous envoie notre proposition de partenariat d’ici [à compléter : date].\n\nAi-je bien résumé ? N’hésitez pas à corriger.\n\nBien cordialement,",
            'why' => 'Le compte rendu prouve qu’on a écouté et fait valider les besoins : la proposition qui suit sera acceptée plus facilement.',
        ],
        [
            'subject' => 'Proposition de partenariat recharge — {{entreprise}}',
            'body' => "Bonjour {{prenom}},\n\nComme promis, voici notre proposition en quelques lignes :\n\n1. Vos vendeurs nous transmettent le nom et le téléphone du client (par e-mail ou par téléphone).\n2. Nous rappelons le client sous 48 h ouvrées et organisons la visite.\n3. Nous installons la prise renforcée ou la borne, puis nous prévenons votre équipe.\n4. Pour chaque installation réalisée : [à compléter : commission ou remise client], versée chaque mois.\n\nAucun engagement de volume et aucun frais pour la concession.\n\nVoulez-vous qu’on commence par un premier client test ?\n\nBien cordialement,",
            'why' => 'Une proposition courte, chiffrée et sans risque. Finir sur un « premier client test » rend le oui facile.',
        ],
        [
            'subject' => 'Re : proposition de partenariat recharge',
            'body' => "Bonjour {{prenom}},\n\nAvez-vous pu regarder notre proposition ? Si un point vous freine (la commission, le délai, la façon de nous transmettre les clients), dites-le-moi : nous pouvons l’adapter.\n\nL’idée est que ce soit simple pour vos vendeurs, rentable pour vous et rassurant pour vos clients.\n\nQuel serait le bon moment pour en parler 5 minutes ?\n\nBien cordialement,",
            'why' => 'On invite à exprimer l’objection plutôt qu’à dire non. On rappelle l’équilibre gagnant-gagnant.',
        ],
        [
            'subject' => 'Premier client : c’est fait',
            'body' => "Bonjour {{prenom}},\n\nBonne nouvelle : l’installation chez [à compléter : nom du client] est terminée le [à compléter : date].\n\nCe que nous avons fait : [à compléter : ex. prise renforcée dans le garage et contrôle du tableau]. Le client est satisfait et sait que la recommandation vient de {{entreprise}}.\n\nOn continue ? Si oui, je vous envoie la convention de partenariat pour officialiser.\n\nBien cordialement,",
            'why' => 'La preuve par l’exemple. On rappelle que le client attribue le service à la concession : c’est le gain d’image du partenaire.',
        ],
        [
            'subject' => 'Bienvenue parmi nos partenaires',
            'body' => "Bonjour {{prenom}},\n\nMerci pour votre confiance : {{entreprise}} est désormais partenaire d’Entreprise AR.\n\nPour vos vendeurs, c’est très simple :\n- un client a besoin de recharger chez lui ? Ils nous envoient son nom et son téléphone à [à compléter : e-mail ou téléphone dédié] ;\n- nous le rappelons sous 48 h et tenons le vendeur informé.\n\nJe peux passer 15 minutes présenter le fonctionnement à l’équipe de vente. Quel jour vous arrange ?\n\nBien cordialement,",
            'why' => 'Le partenariat se gagne à la signature mais vit grâce aux vendeurs : on propose tout de suite de les former.',
        ],
        [
            'subject' => 'Votre point du mois',
            'body' => "Bonjour {{prenom}},\n\nVoici le point du mois pour {{entreprise}} :\n- clients transmis : [à compléter] ;\n- installations réalisées : [à compléter] ;\n- commission du mois : [à compléter].\n\nY a-t-il un vendeur à qui je devrais réexpliquer le fonctionnement, ou un client à rappeler en priorité ?\n\nMerci pour cette collaboration,",
            'why' => 'Le suivi mensuel transparent entretient la confiance et rappelle le partenariat à ceux qui l’oublient. La question finale relance les envois.',
        ],
    ],
];
