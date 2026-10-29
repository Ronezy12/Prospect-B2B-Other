<?php

namespace App\Support;

use App\Models\User;

/**
 * Visites guidées interactives, une par écran (clé = nom de route).
 * Chaque étape pointe un élément de la page (sélecteur CSS) ; une étape dont
 * l'élément est absent est simplement sautée. La dernière étape peut proposer
 * l'action suivante, uniquement si l'utilisateur a le droit de la faire.
 */
final class Tours
{
    /** @return array{title: string, steps: array<int, array{target?: string, title: string, text: string}>, next?: array{label: string, url: string}}|null */
    public static function for(?string $route, User $user): ?array
    {
        $tour = match ($route) {
            'pm.dashboard' => [
                'title' => 'Ma journée',
                'steps' => [
                    ['title' => 'Bienvenue !', 'text' => 'Ici, tout est fait pour aller vite : vous appelez, vous cliquez sur le résultat, et l\'outil s\'occupe du reste (statut, suivi, prochain rappel).'],
                    ['target' => '.big-actions', 'title' => 'Les actions principales', 'text' => 'Ajouter un prospect, écrire à plusieurs entreprises, voir vos affaires : un clic.'],
                    ['target' => '[data-tour=today]', 'title' => 'À faire aujourd\'hui', 'text' => 'Vos rappels du jour. Le numéro de téléphone est à côté : appelez directement.'],
                    ['target' => '.outcome-menu', 'title' => 'Le bouton « Résultat »', 'text' => 'Après l\'appel, cliquez ici et choisissez ce qui s\'est passé : pas de réponse, intéressé, rendez-vous… Le prochain rappel se crée tout seul.'],
                    ['target' => '[data-tour=tocall]', 'title' => 'Qui appeler en premier', 'text' => 'Les nouveaux prospects jamais contactés.'],
                    ['target' => '.icon-btn.bell', 'title' => 'Les notifications', 'text' => 'La cloche vous prévient quand quelqu\'un répond ou qu\'une relance vous est confiée.'],
                ],
                'next' => self::link($user, 'prospects.create', 'Ajouter mon premier prospect', 'pm.companies.create')
                    ?? self::link($user, 'prospects.view', 'Voir les prospects', 'pm.companies.index'),
            ],
            'pm.simple-email.create' => [
                'title' => 'Écrire à plusieurs entreprises',
                'steps' => [
                    ['target' => '.wizard-steps', 'title' => '3 étapes seulement', 'text' => 'À qui, quel message, puis vérifier et envoyer. Rien ne part avant votre clic final.'],
                    ['target' => '#sector_id', 'title' => 'Choisissez les entreprises', 'text' => 'Un secteur, une ville… ou rien pour écrire à tous vos prospects. Cliquez sur « Voir » pour compter.'],
                    ['target' => '.wizard-count', 'title' => 'Combien vont le recevoir', 'text' => 'Les personnes qui ont refusé d\'être contactées sont retirées automatiquement.'],
                ],
            ],
            'pm.simple-email.message' => [
                'title' => 'Le message',
                'steps' => [
                    ['target' => '.example-picker', 'title' => 'Partez d\'un exemple', 'text' => 'Choisissez un texte tout prêt, puis adaptez-le.'],
                    ['target' => '.insert-bar', 'title' => 'Personnalisez', 'text' => 'Ces boutons ajoutent le prénom ou le nom de l\'entreprise : il sera remplacé pour chaque destinataire.'],
                ],
            ],
            'pm.simple-email.confirm' => [
                'title' => 'Vérifier et envoyer',
                'steps' => [
                    ['target' => '.mail-preview', 'title' => 'L\'aperçu', 'text' => 'Voici l\'e-mail tel que le recevra la première personne de la liste.'],
                    ['target' => '.wizard-count form', 'title' => 'Envoyer', 'text' => 'Cochez la case, choisissez maintenant ou plus tard, puis envoyez. Vous pourrez suivre l\'envoi ensuite.'],
                ],
            ],
            'pm.automations.simple' => [
                'title' => 'Relances automatiques',
                'steps' => [
                    ['target' => '.rule-list', 'title' => 'Des interrupteurs', 'text' => 'Chaque ligne est une chose que l\'outil peut faire à votre place. Cliquez sur l\'interrupteur pour l\'activer ou la désactiver.'],
                ],
            ],
            'pm.companies.index' => [
                'title' => 'Base de prospects',
                'steps' => [
                    ['target' => '.page-header h1', 'title' => 'Vos prospects', 'text' => 'Toutes les entreprises que vous démarchez. Vous ne voyez que celles de votre périmètre.'],
                    ['target' => '.page-header .btn-primary', 'title' => 'Ajouter un prospect', 'text' => 'Pour créer une entreprise à la main. L\'outil vous prévient si elle existe déjà.'],
                    ['target' => '#q', 'title' => 'Rechercher', 'text' => 'Tapez un nom, une ville, un e-mail ou un SIREN.'],
                    ['target' => '.filters', 'title' => 'Filtrer', 'text' => 'Affinez par secteur, statut, responsable, ville…'],
                    ['target' => '.bulk-bar', 'title' => 'Actions groupées', 'text' => 'Cochez plusieurs lignes pour changer leur statut ou leur responsable en une fois.'],
                    ['target' => 'table tbody tr', 'title' => 'Une fiche', 'text' => 'Cliquez sur le nom d\'une entreprise pour ouvrir sa fiche : contacts, affaires, rappels et historique.'],
                ],
                'next' => self::link($user, 'prospects.import', 'Importer un fichier Excel', 'pm.imports.create'),
            ],
            'pm.companies.create' => [
                'title' => 'Nouveau prospect',
                'steps' => [
                    ['target' => '#name', 'title' => 'Le nom de l\'entreprise', 'text' => 'Seul champ obligatoire. Le reste peut être complété plus tard.'],
                    ['target' => '#contact_first_name', 'title' => 'La personne à contacter', 'text' => 'Si vous connaissez votre interlocuteur, ajoutez-le directement ici.'],
                    ['target' => '#contact_legal_basis', 'title' => 'Pourquoi peut-on lui écrire ?', 'text' => 'Pour une adresse professionnelle, « Intérêt légitime » suffit en général. Pour un particulier, il faut son accord (« Consentement »).'],
                    ['target' => 'form .form-actions .btn-primary', 'title' => 'Enregistrer', 'text' => 'La fiche est créée et vous arrivez dessus.'],
                ],
            ],
            'pm.companies.show' => [
                'title' => 'Fiche prospect',
                'steps' => [
                    ['target' => '[data-tour=outcomes]', 'title' => 'Après chaque appel', 'text' => 'Cliquez sur ce qui s\'est passé. L\'outil met à jour le statut, l\'affaire et crée le prochain rappel.'],
                    ['target' => '[data-tour=contacts]', 'title' => 'Les interlocuteurs', 'text' => 'Les personnes à joindre. Le bouton « E-mail » permet de leur écrire directement.'],
                    ['target' => '#reseaux', 'title' => 'Réseaux sociaux', 'text' => 'Collez l\'adresse de son profil LinkedIn ou de sa page Facebook pour suivre vos prises de contact.'],
                    ['target' => '#opportunites', 'title' => 'Les affaires', 'text' => 'Créez une opportunité dès qu\'une vente se dessine : elle apparaît dans le pipeline.'],
                    ['target' => '#taches', 'title' => 'Les rappels', 'text' => '« Planifier une relance » pour ne pas oublier de rappeler.'],
                    ['target' => '#historique', 'title' => 'L\'historique', 'text' => 'Notez vos appels et rendez-vous : toute l\'équipe sait où vous en êtes.'],
                ],
            ],
            'pm.imports.create' => [
                'title' => 'Import',
                'steps' => [
                    ['target' => '.steps', 'title' => '3 étapes', 'text' => 'Vous envoyez le fichier, vous vérifiez les colonnes, puis l\'outil crée les fiches.'],
                    ['target' => '#file', 'title' => 'Votre fichier', 'text' => 'Excel ou CSV, avec les titres de colonnes sur la première ligne.'],
                    ['target' => '.grid-sidebar > .card:last-child, aside.card', 'title' => 'Colonnes reconnues', 'text' => 'Si vos colonnes portent ces noms, elles seront associées automatiquement.'],
                ],
            ],
            'pm.pipelines.show' => [
                'title' => 'Pipeline',
                'steps' => [
                    ['target' => '.board', 'title' => 'Votre pipeline', 'text' => 'Une vue d\'ensemble de toutes les affaires en cours, de gauche (début) à droite (signature).'],
                    ['target' => '.column-head', 'title' => 'Une colonne = une étape', 'text' => 'Par exemple « Rendez-vous » ou « Devis envoyé ». Le total de la colonne s\'affiche en haut.'],
                    ['target' => '.deal', 'title' => 'Une carte = une affaire', 'text' => 'Faites-la glisser vers la colonne suivante quand elle avance. Cliquez dessus pour voir le détail.'],
                    ['target' => '.subnav', 'title' => 'D\'autres vues', 'text' => 'La vue globale regroupe tous les secteurs ; la liste permet de trier et filtrer.'],
                ],
                'next' => self::link($user, 'prospects.view', 'Voir mes tâches', 'pm.tasks.index'),
            ],
            'pm.tasks.index' => [
                'title' => 'Tâches',
                'steps' => [
                    ['target' => '.tabs:not(.subnav)', 'title' => 'Vos rappels par échéance', 'text' => 'En retard, aujourd\'hui, à venir : traitez d\'abord les retards.'],
                    ['target' => '.task-row', 'title' => 'Une tâche', 'text' => 'Cochez-la quand c\'est fait. Elle reste dans l\'historique.'],
                    ['target' => '.page-header .btn-primary', 'title' => 'Ajouter un rappel', 'text' => 'Un appel, une relance, un rendez-vous… à la date de votre choix.'],
                ],
            ],
            'pm.campaigns.index' => [
                'title' => 'E-mails groupés',
                'steps' => [
                    ['target' => '.section-help', 'title' => 'Écrire à plusieurs entreprises', 'text' => 'Il faut 3 choses : un modèle (le message), une liste de destinataires (à qui), et l\'e-mail groupé (l\'envoi).'],
                    ['target' => '.subnav', 'title' => 'Les 3 ingrédients', 'text' => 'Les onglets « Modèles » et « Listes de destinataires » se préparent une fois et se réutilisent.'],
                    ['target' => '.page-header .btn-primary', 'title' => 'Préparer un envoi', 'text' => 'Choisissez le modèle et la liste ; rien ne part avant votre validation.'],
                    ['target' => 'table', 'title' => 'Vos envois', 'text' => 'Ouvrez un envoi pour voir qui l\'a reçu, qui a été retiré et pourquoi.'],
                ],
                'next' => self::link($user, 'templates.manage', 'Préparer un modèle', 'pm.templates.index'),
            ],
            'pm.campaigns.create', 'pm.campaigns.edit' => [
                'title' => 'Préparer un e-mail groupé',
                'steps' => [
                    ['target' => '#name', 'title' => 'Un nom pour vous retrouver', 'text' => 'Il n\'est pas visible par les destinataires.'],
                    ['target' => '#template_id', 'title' => 'Le message', 'text' => 'Le modèle à envoyer. Les prénoms et noms d\'entreprise seront remplis automatiquement.'],
                    ['target' => '#segment_id, #list_id', 'title' => 'À qui ?', 'text' => 'La liste de destinataires. Les personnes opposées sont retirées automatiquement.'],
                    ['target' => '#mail_account_id', 'title' => 'Depuis quelle adresse ?', 'text' => 'Le compte d\'envoi configuré dans les paramètres.'],
                    ['target' => '#ex-days', 'title' => 'Ne pas insister', 'text' => 'Évitez d\'écrire à quelqu\'un déjà sollicité récemment.'],
                ],
            ],
            'pm.campaigns.show' => [
                'title' => 'Envoyer',
                'steps' => [
                    ['target' => '[data-tour=test]', 'title' => '1. Testez', 'text' => 'Envoyez-vous l\'e-mail pour vérifier le rendu.'],
                    ['target' => '[data-tour=verif]', 'title' => '2. Vérifiez', 'text' => 'L\'outil indique combien de personnes vont le recevoir et lesquelles sont retirées, avec la raison.'],
                    ['target' => '[data-tour=envoi]', 'title' => '3. Envoyez', 'text' => 'Maintenant ou à une date précise. Vous pourrez mettre en pause ou annuler.'],
                    ['target' => '.stats', 'title' => 'Les résultats', 'text' => '« Accepté » veut dire que le serveur l\'a pris en charge, pas qu\'il a été lu.'],
                ],
            ],
            'pm.templates.index' => [
                'title' => 'Modèles',
                'steps' => [
                    ['target' => '.page-header .btn-primary', 'title' => 'Créer un modèle', 'text' => 'Choisissez une mise en page, puis écrivez votre message dans l\'éditeur.'],
                    ['target' => 'table, .card.flush', 'title' => 'Vos modèles', 'text' => 'Chaque modification est gardée : un envoi passé conserve exactement le contenu qu\'il a envoyé.'],
                ],
            ],
            'pm.templates.edit' => [
                'title' => 'Éditeur d\'e-mail',
                'steps' => [
                    ['target' => '#subject', 'title' => 'L\'objet', 'text' => 'C\'est ce que la personne voit en premier dans sa boîte. Court et concret.'],
                    ['target' => '#preheader', 'title' => 'La phrase d\'aperçu', 'text' => 'Quelques mots affichés après l\'objet par la plupart des messageries.'],
                    ['target' => '#gjs', 'title' => 'Le contenu', 'text' => 'Glissez les blocs (texte, image, bouton, prénom…) depuis le panneau de droite, puis cliquez dans le texte pour l\'écrire.'],
                ],
            ],
            'pm.segments.create', 'pm.segments.edit' => [
                'title' => 'Liste de destinataires',
                'steps' => [
                    ['target' => '#name', 'title' => 'Donnez-lui un nom', 'text' => 'Par exemple « Agences immobilières du Rhône ».'],
                    ['target' => '#f-sector', 'title' => 'Choisissez les critères', 'text' => 'Secteur, ville, statut, tag… Seules les entreprises qui correspondent recevront l\'e-mail.'],
                    ['target' => '.stats', 'title' => 'Combien de personnes ?', 'text' => 'Le nombre de destinataires est recalculé : vérifiez-le avant d\'envoyer.'],
                ],
                'next' => self::link($user, 'campaigns.manage', 'Préparer un e-mail groupé', 'pm.campaigns.create'),
            ],
            'pm.social.index' => [
                'title' => 'Réseaux sociaux',
                'steps' => [
                    ['target' => '.hint-line', 'title' => 'Le principe', 'text' => 'Vous contactez la personne vous-même sur le réseau, puis vous notez ici ce que vous avez fait.'],
                    ['target' => '.tabs:not(.subnav)', 'title' => 'Votre file de travail', 'text' => '« À traiter » liste les profils à contacter aujourd\'hui.'],
                    ['target' => '.task-row .btn-primary', 'title' => 'Traiter un profil', 'text' => 'Ouvrez la fiche : message prêt à copier, puis enregistrez l\'action.'],
                ],
            ],
            'pm.social.show' => [
                'title' => 'Suivi d\'un profil',
                'steps' => [
                    ['target' => '.page-header .btn-primary', 'title' => '1. Ouvrez le profil', 'text' => 'Il s\'ouvre dans un nouvel onglet, sur le réseau.'],
                    ['target' => '.copy-block', 'title' => '2. Copiez un message', 'text' => 'Le prénom et l\'entreprise sont déjà remplis. Relisez, puis collez-le sur le réseau.'],
                    ['target' => '#action', 'title' => '3. Cliquez sur ce que vous avez fait', 'text' => 'Un clic : l\'action est notée et le prochain rappel est créé tout seul.'],
                ],
            ],
            'pm.automations.index' => [
                'title' => 'Automatisations',
                'steps' => [
                    ['target' => '.section-help', 'title' => 'Quand… alors…', 'text' => 'Une automatisation est une règle : quand quelque chose arrive, l\'outil fait une action à votre place.'],
                    ['target' => '.preset-grid', 'title' => 'Partez d\'un modèle', 'text' => 'Choisissez un scénario tout prêt : il est créé en brouillon, rien ne se passe avant que vous l\'activiez.'],
                ],
            ],
            'pm.automations.edit' => [
                'title' => 'Construire une automatisation',
                'steps' => [
                    ['target' => '#trigger', 'title' => 'Quand…', 'text' => 'L\'événement qui lance la règle, par exemple « Nouveau prospect créé ».'],
                    ['target' => '[data-steps]', 'title' => 'Alors…', 'text' => 'Les étapes, dans l\'ordre : une action, un délai d\'attente, ou une vérification « seulement si ».'],
                    ['target' => '[data-add-step]', 'title' => 'Ajouter une étape', 'text' => 'Ajoutez autant d\'étapes que nécessaire, puis enregistrez.'],
                ],
            ],
            'pm.automations.show' => [
                'title' => 'Tester et activer',
                'steps' => [
                    ['target' => '.flow', 'title' => 'Le déroulé', 'text' => 'Relisez la règle étape par étape.'],
                    ['target' => '#company_id', 'title' => 'Testez sans risque', 'text' => 'Choisissez une fiche et lancez le test : l\'outil montre ce qu\'il ferait, sans rien modifier.'],
                    ['target' => '.page-header .actions', 'title' => 'Activez', 'text' => 'Quand le test vous convient, activez. Pause et arrêt sont possibles à tout moment.'],
                ],
            ],
            'pm.reports.index' => [
                'title' => 'Statistiques',
                'steps' => [
                    ['target' => '.stats', 'title' => 'Les chiffres clés', 'text' => 'Prospects ajoutés, affaires créées et gagnées sur la période choisie.'],
                    ['target' => '.funnel', 'title' => 'Le parcours des e-mails', 'text' => 'De l\'envoi à la réponse. « Indisponible » veut dire que la mesure demande un réglage, pas qu\'elle vaut zéro.'],
                ],
            ],
            'pm.settings.index' => [
                'title' => 'Paramètres',
                'steps' => [
                    ['target' => '.tile-grid', 'title' => 'Les réglages', 'text' => 'Chaque carte indique à quoi sert le réglage et s\'il est en place. Les cartes « Facultatif » peuvent attendre.'],
                ],
            ],
            default => null,
        };

        if ($tour === null) {
            return null;
        }
        $tour['next'] ??= null;

        return $tour;
    }

    /** @return array{label: string, url: string}|null */
    private static function link(User $user, string $permission, string $label, string $route): ?array
    {
        return $user->can($permission) ? ['label' => $label, 'url' => route($route)] : null;
    }
}
