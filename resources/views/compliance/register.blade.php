@extends('layouts.app')
@section('title', 'Registre des traitements')

@section('content')
<div class="page-header">
    <div>
        <h1>Registre des traitements</h1>
        <p class="subtitle">Fiche pré-remplie à partir du fonctionnement réel de l'outil (article 30 du RGPD). À compléter et faire valider par votre DPO ou votre conseil.</p>
    </div>
    <div class="actions"><button type="button" class="btn" data-print><x-icon name="download" /> Imprimer / PDF</button></div>
</div>
<div class="alert alert-warning"><x-icon name="circle-alert" /><span>Ce document est une aide : il ne garantit pas la conformité juridique. Les champs entre crochets sont à compléter.</span></div>

<section class="card register">
    <h2>Traitement : prospection commerciale B2B et gestion de la relation prospects</h2>
    <dl class="details">
        <dt>Responsable du traitement</dt><dd>Entreprise AR — [adresse, SIREN, représentant]</dd>
        <dt>Contact DPO / référent</dt><dd>[nom, e-mail]</dd>
        <dt>Finalités</dt><dd>Constitution et suivi d'une base de prospects professionnels ; prise de contact (téléphone, e-mail, réseaux sociaux) ; suivi des affaires ; envoi d'e-mails de prospection et de newsletters ; mesure des résultats ; gestion des oppositions et des demandes des personnes.</dd>
        <dt>Bases légales</dt><dd>Intérêt légitime pour la prospection B2B par e-mail d'adresses professionnelles en lien avec la fonction de la personne ; consentement pour les particuliers, les newsletters et la mesure d'ouverture et de clics (pixels de suivi) ; obligation légale pour la tenue de la liste d'opposition.</dd>
        <dt>Personnes concernées</dt><dd>Interlocuteurs des entreprises prospectées ; abonnés aux newsletters ; utilisateurs internes de l'outil.</dd>
        <dt>Catégories de données</dt><dd>Identité (civilité, nom, prénom), vie professionnelle (fonction, entreprise), coordonnées professionnelles (e-mail, téléphone, profil de réseau social public), historique des échanges, statut commercial, données techniques d'envoi (statut de livraison, réponse, désinscription ; ouvertures et clics seulement avec consentement). Aucune donnée sensible.</dd>
        <dt>Source des données</dt><dd>Saisie par l'équipe, fichiers importés (source et date de collecte enregistrées par fiche), demandes entrantes. Aucune collecte automatisée sur les réseaux sociaux.</dd>
        <dt>Destinataires</dt><dd>Équipes commerciale et marketing d'Entreprise AR selon leur rôle (périmètre « mes fiches », « mon équipe » ou « tout ») ; sous-traitants : hébergeur [nom du VPS], fournisseurs d'envoi d'e-mails ([Google / Gmail], [Brevo]), [Meta si la messagerie de Page est connectée].</dd>
        <dt>Transferts hors UE</dt><dd>[À vérifier selon les fournisseurs : Google et Meta peuvent traiter des données hors UE — clauses contractuelles types / Data Privacy Framework.]</dd>
        <dt>Durées de conservation</dt><dd>Prospects : {{ $rules['inactive_prospect_months'] }} mois après le dernier échange, puis anonymisation automatique ; mesure d'ouverture et de clics : {{ $rules['tracking_months'] }} mois ; journal d'audit : {{ $rules['audit_months'] }} mois ; fichiers d'import : {{ config('prospection.import.retention_days') }} jours ; liste d'opposition : sans limite, sous forme d'empreinte uniquement.</dd>
        <dt>Mesures de sécurité</dt><dd>Accès par compte nominatif et rôles, contrôlés côté serveur ; double authentification des administrateurs ; mots de passe hachés ; secrets (SMTP, API) chiffrés en base ; HTTPS ; protections CSRF, en-têtes de sécurité et politique de contenu stricte ; limitation du nombre de requêtes sensibles ; journal d'audit des actions ; sauvegardes [fréquence, lieu] ; liste d'opposition centralisée vérifiée avant chaque envoi.</dd>
        <dt>Exercice des droits</dt><dd>Demandes enregistrées dans « Conformité RGPD › Demandes des personnes » avec délai d'un mois ; export des données (accès, portabilité), rectification sur la fiche, effacement, opposition (lien de désinscription en un clic dans chaque e-mail de prospection).</dd>
    </dl>
</section>
@endsection
