@extends('layouts.app')
@section('title', 'Guide')

@section('content')
@php($u = auth()->user())
<div class="page-header">
    <div>
        <h1>Guide de démarrage</h1>
        <p class="subtitle">Tout comprendre en 5 minutes : les mots utilisés, puis comment faire, étape par étape.</p>
    </div>
</div>

<nav class="guide-toc" aria-label="Sommaire du guide">
    <a href="#principe">Le principe</a>
    <a href="#mots">Les mots à connaître</a>
    <a href="#prospects">Ajouter des prospects</a>
    <a href="#affaires">Suivre une affaire</a>
    <a href="#emails">Envoyer un e-mail à plusieurs entreprises</a>
    <a href="#reseaux">Réseaux sociaux</a>
    <a href="#automatisations">Automatisations</a>
    <a href="#refus">Quand quelqu'un dit non</a>
    <a href="#questions">Questions fréquentes</a>
</nav>

<section class="card guide-section" id="principe">
    <h2>Le principe, en une phrase</h2>
    <p class="guide-lead">Vous rassemblez les entreprises que vous voulez démarcher, vous les contactez (téléphone, e-mail, réseaux sociaux), et l'outil vous rappelle quoi faire et quand, jusqu'à la signature.</p>
    <ol class="journey">
        <li class="journey-step"><span class="journey-num">1</span><strong>Constituer la base</strong><span class="muted small">Les entreprises à démarcher et les personnes à joindre.</span></li>
        <li class="journey-step"><span class="journey-num">2</span><strong>Suivre les affaires</strong><span class="muted small">Où en est chaque discussion, du premier contact au devis.</span></li>
        <li class="journey-step"><span class="journey-num">3</span><strong>Contacter</strong><span class="muted small">Un e-mail à une personne ou à plusieurs entreprises d'un coup, ou un message sur LinkedIn.</span></li>
        <li class="journey-step"><span class="journey-num">4</span><strong>Ne rien oublier</strong><span class="muted small">Les tâches et les automatisations rappellent les relances.</span></li>
    </ol>
</section>

<section class="card guide-section" id="mots">
    <h2>Les mots à connaître</h2>
    <div class="glossary">
        <dl><dt>Prospect</dt><dd>Une entreprise que vous aimeriez avoir comme client.</dd><dd class="ex">Ex. : Agence Horizon Immobilier</dd></dl>
        <dl><dt>Contact</dt><dd>Une personne qui travaille dans cette entreprise.</dd><dd class="ex">Ex. : Claire Morel, gérante</dd></dl>
        <dl><dt>Affaire (opportunité)</dt><dd>Une vente possible avec un prospect, avec un montant estimé.</dd><dd class="ex">Ex. : Rénovation électrique des bureaux — 12 000 €</dd></dl>
        <dl><dt>Pipeline</dt><dd>Le tableau en colonnes qui montre où en est chaque affaire. On déplace les cartes d'une colonne à l'autre.</dd><dd class="ex">Ex. : À contacter → Rendez-vous → Devis envoyé → Gagné</dd></dl>
        <dl><dt>Tâche (relance)</dt><dd>Un rappel de quelque chose à faire, à une date.</dd><dd class="ex">Ex. : Rappeler Claire jeudi</dd></dl>
        <dl><dt>E-mail groupé (campagne)</dt><dd>Un même e-mail envoyé à plusieurs entreprises en une fois, avec le prénom de chacun.</dd><dd class="ex">Ex. : Présentation de nos services aux agences de Lyon</dd></dl>
        <dl><dt>Liste de destinataires</dt><dd>Qui va recevoir l'e-mail groupé, choisi par critères. Elle se met à jour toute seule.</dd><dd class="ex">Ex. : Agences immobilières du Rhône</dd></dl>
        <dl><dt>Modèle d'e-mail</dt><dd>Le texte et la mise en page, réutilisables. « &#123;&#123;prenom&#125;&#125; » devient le vrai prénom.</dd><dd class="ex">Ex. : « Bonjour &#123;&#123;prenom&#125;&#125; » → « Bonjour Claire »</dd></dl>
        <dl><dt>Newsletter</dt><dd>Un e-mail d'actualités, envoyé seulement aux personnes abonnées.</dd><dd class="ex">Ex. : La lettre mensuelle aux syndics</dd></dl>
        <dl><dt>Opposition</dt><dd>La personne a demandé à ne plus être contactée. Elle ne recevra plus rien, même si quelqu'un la réimporte.</dd><dd class="ex">Ex. : « Merci de me retirer de vos envois »</dd></dl>
        <dl><dt>Automatisation</dt><dd>Une règle « Quand… alors… » qui fait une tâche répétitive à votre place.</dd><dd class="ex">Ex. : Quand un prospect est créé, alors créer la tâche « Appeler »</dd></dl>
        <dl><dt>Quand / Alors / Seulement si</dt><dd>Les 3 briques d'une automatisation : l'événement qui la lance, ce qu'elle fait, et une vérification facultative.</dd><dd class="ex">Ex. : Seulement si le prospect n'a pas répondu</dd></dl>
    </div>
</section>

<section class="card guide-section" id="prospects">
    <h2><x-icon name="building-2" /> Ajouter des prospects</h2>
    <ol class="howto">
        <li><strong>Un par un</strong> : Prospects › <em>Nouveau prospect</em>. Remplissez le nom de l'entreprise et, si vous l'avez, la personne à contacter. Si l'entreprise existe déjà, l'outil vous prévient.</li>
        <li><strong>Plusieurs d'un coup</strong> : Prospects › onglet <em>Imports</em> › <em>Nouvel import</em>. Envoyez votre fichier Excel ou CSV : l'outil reconnaît les colonnes, vous montre un aperçu, puis vous dit ligne par ligne ce qui a été créé, ignoré ou refusé.</li>
        <li>Sur la fiche d'un prospect, tout est au même endroit : ses contacts, ses affaires, ses tâches et l'historique de tout ce qui a été fait.</li>
    </ol>
    <div class="guide-actions">
        @can('prospects.create')<a class="btn btn-primary btn-sm" href="{{ route('pm.companies.create') }}"><x-icon name="plus" /> Ajouter un prospect</a>@endcan
        @can('prospects.import')<a class="btn btn-sm" href="{{ route('pm.imports.create') }}"><x-icon name="upload" /> Importer un fichier</a>@endcan
    </div>
</section>

<section class="card guide-section" id="affaires">
    <h2><x-icon name="kanban" /> Suivre une affaire</h2>
    <ol class="howto">
        <li>Depuis la fiche du prospect, cliquez sur <em>Nouvelle opportunité</em> : elle apparaît dans le pipeline de son secteur.</li>
        <li>Dans <em>Pipeline</em>, faites glisser la carte vers la colonne suivante quand l'affaire avance (rendez-vous obtenu, devis envoyé…).</li>
        <li>Planifiez la prochaine relance : elle apparaît dans <em>Tâches</em> le jour venu, et en rouge si elle est en retard.</li>
        <li>Affaire signée ? Mettez-la dans « Gagné » puis convertissez le prospect en client.</li>
    </ol>
    <div class="guide-actions"><a class="btn btn-sm" href="{{ route('pm.pipelines.show') }}">Ouvrir le pipeline <x-icon name="arrow-right" /></a><a class="btn btn-sm" href="{{ route('pm.tasks.index') }}">Mes tâches <x-icon name="arrow-right" /></a></div>
</section>

<section class="card guide-section" id="emails">
    <h2><x-icon name="mail" /> Envoyer un e-mail à plusieurs entreprises</h2>
    <p class="muted">Pour écrire à <strong>une seule</strong> personne, c'est plus simple : ouvrez sa fiche et cliquez sur <em>E-mail</em> à côté de son nom. Pour en toucher <strong>plusieurs</strong>, suivez ces 5 étapes :</p>
    <ol class="howto big">
        <li><strong>Écrire le message</strong> — E-mails › <em>Modèles</em> › <em>Nouveau modèle</em>. Glissez les blocs (texte, image, bouton), ajoutez « Prénom » ou « Entreprise » pour personnaliser.</li>
        <li><strong>Choisir à qui l'envoyer</strong> — E-mails › <em>Listes de destinataires</em> › <em>Nouvelle liste</em>. Cochez les critères (secteur, ville, statut…). Le nombre d'entreprises concernées s'affiche.</li>
        <li><strong>Préparer l'envoi</strong> — E-mails › <em>E-mails groupés</em> › <em>Nouvel e-mail groupé</em>. Choisissez le modèle et la liste.</li>
        <li><strong>Vérifier</strong> — cliquez sur <em>Envoyer un test</em> pour le recevoir vous-même. L'outil vous dit combien de personnes vont le recevoir et combien sont retirées (personnes opposées, adresses invalides…).</li>
        <li><strong>Envoyer</strong> — maintenant, ou à une date choisie. Vous pouvez mettre en pause ou annuler à tout moment.</li>
    </ol>
    <p class="small muted">« Envoyé » veut dire que le serveur a accepté l'e-mail, pas forcément qu'il est arrivé ou qu'il a été lu. Les personnes qui se désinscrivent sont retirées automatiquement des envois suivants.</p>
    @can('campaigns.view')<div class="guide-actions"><a class="btn btn-primary btn-sm" href="{{ route('pm.campaigns.index') }}">Aller aux e-mails groupés <x-icon name="arrow-right" /></a></div>@endcan
</section>

<section class="card guide-section" id="reseaux">
    <h2><x-icon name="share-2" /> Prospecter sur LinkedIn, Facebook, Instagram</h2>
    <ol class="howto">
        <li>Sur la fiche d'un prospect, rubrique <em>Réseaux sociaux</em>, collez l'adresse de son profil.</li>
        <li>Dans <em>Réseaux sociaux</em>, ouvrez le profil, copiez le message proposé et envoyez-le <strong>vous-même</strong> sur le réseau.</li>
        <li>Revenez cliquer sur <em>Enregistrer l'action</em> (invitation envoyée, réponse reçue…) et choisissez la date de la prochaine relance.</li>
    </ol>
    <p class="small muted">L'outil ne publie et n'envoie rien à votre place sur LinkedIn : c'est interdit par le réseau et risquerait de faire bloquer votre compte.</p>
</section>

<section class="card guide-section" id="automatisations">
    <h2><x-icon name="sparkles" /> Les automatisations</h2>
    <p>Une automatisation est une règle : <strong>Quand</strong> quelque chose arrive, <strong>alors</strong> l'outil fait une action, éventuellement <strong>seulement si</strong> une condition est vraie.</p>
    <div class="rule-examples">
        <div class="rule"><span class="rule-when">Quand</span> un prospect est créé <span class="rule-then">alors</span> créer la tâche « Appeler sous 24 h »</div>
        <div class="rule"><span class="rule-when">Quand</span> un contact répond à un e-mail <span class="rule-then">alors</span> arrêter les relances automatiques et prévenir le commercial</div>
        <div class="rule"><span class="rule-when">Quand</span> un prospect est créé <span class="rule-then">alors</span> attendre 3 jours, <span class="rule-if">seulement si</span> il n'a pas répondu, créer une relance</div>
    </div>
    <ol class="howto">
        <li>Partez d'un <strong>modèle tout prêt</strong> (Automatisations › <em>Partir d'un modèle</em>) plutôt que d'une page blanche.</li>
        <li>Cliquez sur <em>Lancer le test</em> sur une fiche : l'outil montre ce qu'il ferait, sans rien faire réellement.</li>
        <li>Si le résultat vous convient, cliquez sur <em>Activer</em>. Vous pouvez le mettre en pause ou l'arrêter à tout moment.</li>
    </ol>
    @can('automations.manage')<div class="guide-actions"><a class="btn btn-sm" href="{{ route('pm.automations.index') }}">Voir les automatisations <x-icon name="arrow-right" /></a></div>@endcan
</section>

<section class="card guide-section" id="refus">
    <h2><x-icon name="shield-check" /> Quand quelqu'un dit « ne me contactez plus »</h2>
    <p>Sur sa fiche, cliquez sur <em>Enregistrer une opposition</em> (ou sur le profil social concerné). À partir de là, plus aucun e-mail ni relance ne lui est adressé, même si son adresse est réimportée plus tard. Les personnes qui cliquent sur « Se désinscrire » en bas d'un e-mail sont ajoutées automatiquement.</p>
</section>

<section class="card guide-section" id="questions">
    <h2><x-icon name="circle-help" /> Questions fréquentes</h2>
    <details class="faq"><summary>Pourquoi je ne vois pas toutes les entreprises ?</summary><p>Chacun voit les fiches de son périmètre : un commercial voit les siennes, un responsable celles de son équipe, l'administrateur toutes.</p></details>
    <details class="faq"><summary>Pourquoi certains chiffres sont « indisponibles » dans les statistiques ?</summary><p>Certaines mesures demandent un réglage : la livraison des e-mails nécessite un compte Brevo, les réponses une boîte mail connectée (Paramètres). Plutôt que d'afficher un faux zéro, l'outil indique que la mesure n'est pas disponible.</p></details>
    <details class="faq"><summary>Une « ouverture » veut-elle dire que l'e-mail a été lu ?</summary><p>Non. C'est un indice (une image chargée), qui peut être faussé par certaines messageries. Le suivi est désactivé par défaut et ne s'applique qu'aux personnes qui l'ont accepté.</p></details>
    <details class="faq"><summary>Mon e-mail groupé n'est pas parti à certaines personnes, pourquoi ?</summary><p>Ouvrez l'e-mail groupé : chaque personne retirée a sa raison (opposition, adresse invalide, particulier sans consentement, déjà contacté récemment…).</p></details>
    <details class="faq"><summary>J'ai fait une erreur dans une automatisation active</summary><p>Cliquez sur <em>Pause</em> : plus rien ne se déclenche. Corrigez, testez, puis cliquez sur <em>Publier la dernière version</em>.</p></details>
</section>
@endsection
