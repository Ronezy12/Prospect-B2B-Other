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
                'title' => 'Accueil',
                'steps' => [
                    ['title' => 'Bienvenue sur votre espace de prospection', 'text' => 'Cette visite vous montre où trouver chaque chose, en quelques clics. Vous pourrez la relancer à tout moment avec le bouton « Visite guidée » en bas à droite.'],
                    ['target' => '.sidebar nav', 'title' => 'Le menu', 'text' => 'Chaque rubrique correspond à une partie de votre travail : les entreprises à démarcher, le suivi des affaires, les rappels, les e-mails…'],
                    ['target' => '.journey', 'title' => 'Le module en 4 étapes', 'text' => 'Constituer la base, suivre les affaires, contacter, puis mesurer. Chaque carte vous emmène au bon endroit.'],
                    ['target' => '[data-tour=today]', 'title' => 'Votre journée', 'text' => 'Les rappels du jour et ceux en retard apparaissent ici. Commencez chaque matin par cette liste.'],
                    ['target' => '.welcome-banner', 'title' => 'Le guide', 'text' => 'Un mot vous échappe ? Le guide explique tout le vocabulaire avec des exemples.'],
                    ['target' => '.icon-btn.bell', 'title' => 'Les notifications', 'text' => 'La cloche vous prévient quand une automatisation vous confie quelque chose ou qu\'un prospect répond.'],
                ],
                'next' => self::link($user, 'prospects.create', 'Ajouter mon premier prospect', 'pm.companies.create')
                    ?? self::link($user, 'prospects.view', 'Voir les prospects', 'pm.companies.index'),
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
                    ['target' => '.page-header', 'title' => 'La fiche de l\'entreprise', 'text' => 'Tout ce qui concerne ce prospect est réuni sur cette page.'],
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
                    ['target' => '#action', 'title' => '3. Notez ce que vous avez fait', 'text' => 'Choisissez l\'action et la date de la prochaine relance : un rappel peut être créé automatiquement.'],
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
