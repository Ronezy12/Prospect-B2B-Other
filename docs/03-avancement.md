# Avancement par phase

| Phase | État |
|---|---|
| 1 — Audit | Terminé (dépôt vide, projet autonome) |
| 2 — Cahier technique | Terminé — [01-audit-et-cahier-technique.md](01-audit-et-cahier-technique.md) |
| 3 — Validation | Terminé (décisions consignées dans le cahier) |
| 4 — Socle et gestion des prospects | **Terminé** (détail ci-dessous) |
| 4 bis — Refonte du design et environnement de test | **Terminé** (détail ci-dessous) |
| 5 — Pipelines, tâches, permissions avancées | **Terminé** (détail ci-dessous) |
| 6 — E-mails, modèles, programmation | **Terminé** (détail ci-dessous) |
| 7 — Statistiques, webhooks, automatisations | **Terminé** (détail ci-dessous) |
| 8 — Réseaux sociaux | **Terminé** (détail ci-dessous) |
| 9 — Conformité, sécurité, tests, documentation finale | À faire (fondations posées dès la phase 4) |

## Phase 4 — livré

**Socle** : Laravel 13, authentification par session (pas d'inscription publique, comptes créés par un administrateur ou `pm:create-admin`), limitation à 5 tentatives de connexion par minute et par couple e-mail/IP, déconnexion immédiate d'un compte désactivé, en-têtes de sécurité et CSP stricte (aucun script ni style en ligne), pages d'erreur en français sans divulgation technique.

**Rôles et permissions** (spatie/laravel-permission, vérifiés côté serveur par policies) :

| Rôle | Périmètre | Droits principaux |
|---|---|---|
| Administrateur | tout | tous |
| Responsable commercial | son équipe + fiches non assignées | création, modification, suppression, masse, assignation, import, export, conversion |
| Commercial | ses fiches | création, modification, import |
| Marketing | tout (lecture) | consultation (campagnes en phase 6) |
| Conformité / DPO | tout (lecture) | oppositions, journal d'audit |
| Lecture seule | tout (lecture) | consultation |

**Prospects** : entreprises distinctes de leurs interlocuteurs (plusieurs contacts par entreprise), SIREN/SIRET, secteur/sous-secteur, site web, coordonnées, source et date d'acquisition, statut commercial, responsable, tags, notes, historique d'activités, journal d'audit. Liste paginée (25/page), filtres combinés (recherche, cycle, secteur, statut, source, responsable, ville, code postal/département, tag), tri, actions groupées (assignation, statut, secteur, tag, suppression) limitées au périmètre, export CSV des résultats filtrés (plafonné, audité, protégé contre l'injection de formules).

**Séparation prospects / clients** : champ `lifecycle`. La conversion est une action explicite (permission `prospects.convert`, case de confirmation, date et auteur conservés, audit). Les imports ne modifient jamais une entreprise déjà cliente.

**Imports CSV / Excel** : téléversement (20 Mo, CSV/XLSX/XLS, nom aléatoire hors racine web), détection d'encodage (UTF-8 / Windows-1252) et de séparateur, aperçu, correspondance automatique puis modifiable des colonnes, valeurs par défaut, confirmation de l'origine licite des données, traitement en file d'attente avec suivi de progression, annulation, rapport ligne à ligne téléchargeable, reprise idempotente après interruption, purge planifiée des fichiers.

**Doublons** : SIREN, puis domaine du site web, puis raison sociale normalisée (formes juridiques et accents ignorés) + code postal ; contacts par e-mail. Stratégies : ignorer, compléter les champs vides, écraser. Fiches supprimées ou hors périmètre jamais modifiées par un import.

**Opposition** : liste centrale `pm_suppressions` stockant uniquement une empreinte HMAC-SHA256 de l'adresse normalisée, par périmètre (toutes communications, prospection, newsletters). Vérifiée à la saisie manuelle et à l'import : une adresse opposée ne peut pas être réintroduite. L'empreinte survit à la suppression de la fiche.

**Tests** : 119 tests automatisés au total (connexion, limitation, périmètres et droits, validation, doublons, conversion, export, imports CSV/XLSX, encodages, stratégies de doublons, opposition, idempotence, purge).

## Règles de conformité implémentées — validation humaine requise

- Chaque contact porte un type (B2B/B2C), une base légale, une source et une date de collecte. Un contact B2C sans consentement est signalé ; il sera **exclu automatiquement** des campagnes de prospection (contrôle bloquant prévu en phase 6).
- L'import exige la confirmation que l'origine des données est licite et que les personnes seront informées.
- Ces règles sont une traduction technique de la réglementation (RGPD, art. L34-5 CPCE, recommandations CNIL) et **ne constituent pas une garantie de conformité** : elles doivent être validées par un juriste ou le DPO.

## Limites connues / points ouverts

- Pas de serveur MySQL dans l'environnement de développement : migrations et tests exécutés sur SQLite. Une recette sur MySQL est à faire lors de la première installation sur le VPS.
- La levée d'une opposition, l'interface de la liste d'opposition, le registre des traitements et les droits des personnes (accès, effacement) arrivent en phase 9.
- La gestion des secteurs/sources depuis l'interface (ajout, renommage) arrive avec les pipelines en phase 5 ; les secteurs par défaut sont créés par le seeder.
- La fusion manuelle de deux fiches existantes n'est pas encore disponible (les doublons sont empêchés à la création et à l'import).
- Sur mobile, le bloc de filtres de la liste est long ; il pourra être replié dans une prochaine itération.

## Phase 4 bis — design et environnement de test

- **Design SaaS** : panneaux flottants, dégradé violet → rose → orange réservé aux actions principales, police Inter auto-hébergée (licence OFL), icônes Lucide intégrées en SVG (licence ISC), thème clair / sombre (préférence système ou bouton), page de connexion en deux panneaux, tableau de bord avec bandeau d'accueil, avatars des responsables, cartes d'interlocuteurs, étapes d'import. Aucune ressource externe : la CSP stricte est conservée.
- **Navigation** : les sections des phases suivantes apparaissent grisées avec leur numéro de phase, sans lien actif.
- **Données de démonstration** : `php artisan pm:demo` crée 5 comptes (un par rôle) et 24 entreprises fictives (domaines `.test`, tag « Exemple démo »). Commande refusée en production.
- **Docker** : `docker compose up --build` lance l'application de test (Apache + PHP 8.3 + SQLite, worker et planificateur inclus) sur http://localhost:8000.
- **Aperçu cliquable** publié à partir des écrans réellement rendus : https://claude.ai/artifact/UX9eoxccjdTrMgRy7XvDTD

Limite : l'environnement de développement bloque l'accès aux dépôts Debian et limite Docker Hub. L'image Docker n'a donc pas pu être construite jusqu'au bout ici. Le démarrage de l'application, les migrations, la commande de démonstration et le worker ont été validés hors conteneur. La première construction de l'image se fera sur votre poste ou votre VPS.

## Phase 5 — pipelines, tâches et paramétrage

**Modèle de données** (migrations réversibles) : `pm_pipelines` (une par secteur principal, unicité sur le secteur), `pm_pipeline_stages` (nom unique par pipeline, ordre, type en cours / gagné / perdu, probabilité), `pm_opportunities` (entreprise, interlocuteur, pipeline, étape, priorité, montant, prochaine action, date de relance, position dans la colonne, clôture, motif de perte, responsable, suppression logique), `pm_stage_history` (ancienne et nouvelle étape, noms figés, auteur, date), `pm_tasks` (rattachement à une entreprise et/ou une opportunité, type, priorité, échéance, statut, réalisation, responsable).

**Pipelines** :
- Une pipeline par secteur, avec les 10 étapes par défaut. Un nouveau secteur créé dans l'interface reçoit automatiquement sa pipeline.
- Kanban par secteur avec glisser-déposer : le déplacement est enregistré côté serveur et l'ordre des cartes est conservé. Sur mobile ou sans JavaScript, chaque carte propose un menu « Déplacer vers ».
- Vue liste : filtres combinés (recherche, pipeline, étape, état en cours / relance en retard / clôturées, responsable, priorité) et pagination.
- Vue globale consolidée : effectifs et montants par étape pour chaque pipeline, opportunités en cours, gagnées et perdues, relances en retard, montant pondéré par la probabilité de chaque étape.
- Fiche opportunité : frise des étapes, détails, changement d'étape, historique complet, tâches liées.
- Règles : une opportunité ne change d'étape qu'à l'intérieur de sa pipeline (refus 422 sinon). Une étape gagnée ou perdue renseigne la date de clôture, et rouvrir l'opportunité l'efface. Chaque changement est historisé, ajouté à l'historique de l'entreprise et au journal d'audit. Aucune opportunité ne peut être créée sur une entreprise déjà cliente. Le passage en « Gagné » ne convertit pas l'entreprise en client : la conversion reste une action explicite.
- Les étapes commerciales sont distinctes du statut de la fiche entreprise, des événements e-mail (phase 7) et des statuts de conformité.

**Tâches et relances** :
- Création depuis une fiche entreprise, une opportunité ou la page Tâches.
- Vues « en retard », « aujourd'hui », « à venir » et « terminées », avec compteur des tâches en retard dans la navigation et liste du jour sur le tableau de bord.
- Une tâche se marque faite, s'annule ou se rouvre ; une tâche faite est ajoutée à l'historique de l'entreprise.
- Sans droit d'assignation, une tâche est toujours attribuée à son créateur.
- Périmètre : chacun voit ses tâches ; un responsable voit celles de son équipe.

**Paramétrage** (nouvelle permission `pipelines.manage`, attribuée à l'administrateur) :
- Secteurs et sous-secteurs : ajout, renommage, activation.
- Sources : ajout, renommage, activation.
- Pipelines : nom, activation.
- Étapes : ajout, renommage, type, probabilité, ordre. Une étape qui contient des opportunités ne peut être ni supprimée ni changer de type, et une pipeline garde toujours au moins une étape en cours.

**Tests ajoutés** (22) : étapes par défaut, création historisée, déplacement et clôture, réordonnancement, refus inter-pipeline, périmètres (commercial, équipe, lecture seule), entreprise hors périmètre ou cliente, filtre des relances en retard, opportunités d'une entreprise archivée, tâches (assignation forcée, périmètre, équipe, réalisation, réouverture, compteur), paramétrage (droits, création de secteur, règles des étapes, ordre, sources).

Limites :
- Il n'y a pas de notification par e-mail des tâches : elle arrivera avec le moteur d'automatisation (phase 7).
- Le Kanban charge 60 cartes par colonne ; au-delà, un lien mène à la vue liste filtrée.

## Phase 6 — e-mails, modèles et programmation

**Architecture d'envoi hybride** : une interface commune `MailProvider` (`app/Services/Mail`) et trois implémentations :
- **SMTP** (Symfony Mailer). Les codes 4xx et les erreurs réseau sont traités comme temporaires, les codes 5xx comme définitifs.
- **API Brevo** : emplacement prêt, à activer en saisissant une clé. Les codes 429 et 5xx sont temporaires, les autres 4xx définitifs.
- **Journal local** : pour les tests, aucun envoi réel ; indisponible en production.

Les identifiants (mot de passe SMTP, clé API) sont chiffrés en base avec la clé applicative. Ils ne sont jamais réaffichés, ni renvoyés en JSON, ni écrits dans le journal d'audit ou dans les messages d'erreur. Toute modification d'un compte impose un nouveau test d'envoi réel avant de pouvoir l'utiliser.

**Modèles d'e-mails** :
- Éditeur visuel GrapesJS 0.23.6 avec le preset newsletter 1.0.2 (BSD-3), en français, servi localement, télémétrie désactivée.
- Blocs « Variables » : prénom, nom, civilité, fonction, entreprise, ville, expéditeur, lien de désinscription. Les valeurs sont échappées au rendu.
- Chaque enregistrement crée une version immuable. Une campagne fige sa version au moment de la programmation.
- Contenu nettoyé à l'enregistrement : scripts, formulaires, attributs `on*` et liens `javascript:` sont retirés.
- Aperçu ordinateur et mobile dans un cadre isolé (sans script, CSP `sandbox`).
- E-mails de test (5 adresses au plus, objet préfixé [TEST], données d'exemple).
- Version texte générée automatiquement.

**Segments** : critères sur l'entreprise (cycle prospect/client, secteur, statut, source, ville, département, tag, responsable) et sur le contact (B2B/B2C, base légale). Le segment affiche une estimation du nombre de contacts autorisés et exclus, par motif. Il est appliqué dans le périmètre de la personne qui lance la campagne.

**Newsletters** : listes d'abonnés. Un abonnement exige la source et la date du consentement. Une adresse opposée aux newsletters ne peut pas être réabonnée depuis le CRM. Un désabonnement conserve la preuve.

**Campagnes** (prospection, newsletter, opérationnelle, e-mail individuel) :
- Brouillon : modèle, compte d'envoi, segment ou liste, expéditeur et réponse, exclusions par tag et par délai depuis le dernier envoi.
- Avant tout lancement : e-mail de test, puis une vérification qui bloque en cas de compte non testé ou en échec, de modèle sans objet, de segment ou liste manquant, ou d'aucun destinataire autorisé. Elle avertit aussi : mode journal, absence de test, contenu promotionnel dans un message opérationnel.
- Une case de confirmation de la finalité et de l'information des personnes est obligatoire.
- Envoi immédiat ou programmé ; pause, reprise et arrêt (les destinataires restants sont annulés) ; duplication en brouillon ; historique dans le journal d'audit.
- Destinataires figés au démarrage, chacun avec son statut : en attente, accepté, échec, exclu (et motif), annulé, à vérifier.

**File d'envoi** :
- Tâche `SendCampaignBatch` (file `emails`) par lots de 50, relancée chaque minute par `pm:campaigns-run`. Les envois programmés reprennent donc seuls après un redémarrage.
- Réservation atomique de chaque destinataire, clé d'idempotence unique par campagne et adresse, `Message-ID` dérivé de cette clé, dédoublonnage des adresses.
- Quotas horaire et journalier par compte, avec reprise automatique une fois la fenêtre écoulée.
- Erreurs temporaires retentées 3 fois (après 5, 30 puis 120 minutes) ; erreurs définitives non retentées.
- Un envoi interrompu en plein appel passe « à vérifier » au bout de 15 minutes, sans renvoi automatique.
- Événements enregistrés : demande, acceptation, échec temporaire, échec, désabonnement.

**Conformité** (`ComplianceGate`, appliquée à la constitution de l'audience puis de nouveau juste avant chaque envoi) :
- Adresse invalide (rebond définitif) : exclue de tout envoi.
- Opposition ou désabonnement : exclusion des envois marketing de son périmètre (prospection ou newsletter).
- Prospection et e-mails individuels : un particulier (B2C) sans consentement est exclu.
- Newsletter : abonnement actif exigé.
- Messages opérationnels : non bloqués par une désinscription marketing, sans lien de désinscription.
- Chaque e-mail marketing contient un lien de désinscription (ajouté en pied de page s'il manque dans le modèle) et les en-têtes `List-Unsubscribe` et `List-Unsubscribe-Post` (désinscription en un clic, RFC 8058).
- Désinscription publique par lien signé (impossible à falsifier), avec page de confirmation et clic unique. Elle crée une opposition persistante et un événement, et met à jour l'abonnement.

**E-mails individuels** : depuis la fiche d'un contact (permission `emails.individual`, attribuée aux commerciaux et aux responsables). Ils passent par la même chaîne de contrôle et d'envoi, avec signature, lien d'opposition et trace dans l'historique de l'entreprise.

**Statistiques disponibles** : destinataires figés, acceptés par le serveur d'envoi, en attente, exclus par motif, échecs, à vérifier, désinscriptions. « Accepté » n'est jamais présenté comme « livré ». Livraisons, rebonds, ouvertures et clics sont affichés comme indisponibles avec SMTP et le journal local ; ils arriveront en phase 7 avec les webhooks d'un fournisseur API.

**Rôles** :
- Marketing : campagnes, modèles, segments et newsletters.
- Responsable commercial : consultation des campagnes, e-mails individuels.
- Commercial : e-mails individuels.
- Conformité : consultation.
- Comptes d'envoi : administrateur (`settings.manage`).

**Tests ajoutés** (29), couvrant :
- les règles de conformité de bout en bout ;
- l'échappement des variables ;
- la vérification avant envoi et la confirmation obligatoire ;
- la programmation et le planificateur, et la version de modèle figée ;
- la pause, la reprise et l'arrêt, y compris pendant l'envoi ;
- les erreurs temporaires et définitives, les quotas horaires ;
- l'opposition enregistrée entre la constitution de l'audience et l'envoi ;
- les envois interrompus ;
- les newsletters et les messages opérationnels ;
- les droits ;
- la désinscription : lien signé, falsification, clic unique, périmètre ;
- le consentement des abonnements ;
- les versions et le nettoyage des modèles, l'isolation de l'aperçu, les e-mails de test ;
- le chiffrement des secrets et le test des comptes ;
- l'API Brevo (réponses simulées) ;
- les e-mails individuels et les estimations de segment.

Limites :
- L'API Brevo est testée avec des réponses simulées : la vraie connexion se validera en saisissant votre clé.
- Le suivi des ouvertures et des clics n'est pas activé (recommandation CNIL 2026 : consentement préalable) ; il sera configurable en phase 7.
- Pas encore de formulaire public d'inscription à la newsletter (double opt-in) : prévu en phase 9.
- Les images des modèles se référencent par URL ; il n'y a pas encore de téléversement d'images dans l'éditeur.

## Phase 7 — statistiques, webhooks et automatisations

**Événements e-mail** (`DeliveryTracker`) :
- Idempotent : un événement déjà reçu (même fournisseur, même identifiant) est ignoré, d'où la gestion des webhooks dupliqués.
- Les événements en retard ne font jamais régresser un statut : un « livré » tardif n'efface pas un rebond. Ordre de gravité : différé < rebond temporaire < livré < bloqué < rebond définitif < plainte.
- Rebond définitif → opposition « adresse invalide » (bloquée pour toute finalité) ; plainte → opposition totale ; désinscription → opposition du périmètre et désabonnement.
- Chaque événement est daté par le fournisseur et rattaché au destinataire.

**Webhooks Brevo** (`POST /webhooks/brevo/{jeton}`) :
- Authentifiés par un jeton secret de 48 caractères généré pour chaque compte Brevo (URL affichée dans la fiche du compte, à déclarer chez Brevo).
- Envoi groupé accepté.
- Rattachement par l'en-tête `X-Mailin-custom` (clé d'idempotence), puis par le Message-ID.
- Événements pris en charge : livré, différé, rebond temporaire et définitif, adresse invalide, bloqué, plainte, désinscription, ouverture, clic.

**Suivi des ouvertures et des clics** :
- Interne à l'application, il fonctionne aussi en SMTP.
- **Désactivé par défaut.** Il ne s'applique que si trois conditions sont réunies : activation globale (Paramètres › Suivi des e-mails), option cochée sur la campagne, et **consentement du contact** tracé (date et source, sur la fiche contact), conformément à la recommandation CNIL du 12/03/2026.
- Les ouvertures et clics remontés par Brevo pour des contacts sans consentement ne sont pas conservés.
- Liens de clic signés par HMAC (pas de redirection ouverte) ; le lien de désinscription n'est jamais réécrit.
- Une ouverture est présentée comme « détectée (indicatif) », jamais comme une lecture.

**Détection des réponses (IMAP)** :
- Boîtes configurables (identifiants chiffrés), consultées toutes les 5 minutes par `pm:replies-fetch`. Seuls les en-têtes sont lus, les messages ne sont pas marqués comme lus.
- Rapprochement fiable par `In-Reply-To` / `References`, qui contiennent le Message-ID généré à l'envoi ou celui de Brevo. À défaut, rapprochement probable par l'expéditeur ayant reçu un e-mail dans les 60 jours.
- Une réponse enregistre l'événement, l'historique de l'entreprise et le déclencheur « réponse reçue ».
- Limite : sans lecture du corps, les réponses automatiques (absence) sont comptées comme réponses.

**Statistiques** (page Statistiques, périodes de 30, 90 et 365 jours) :
- Entonnoir : demandés → acceptés → livrés → ouvertures → clics → réponses.
- Qualité de la base : exclus, échecs, rebonds, plaintes, désinscriptions.
- Indicateurs commerciaux : nouveaux prospects, opportunités créées et gagnées, conversions, montant gagné.
- Tableau par campagne.
- Un indicateur que la configuration ne permet pas de mesurer est affiché « indisponible » ou « non mesuré », jamais à zéro.

**Moteur d'automatisation** :
- 7 déclencheurs : nouveau prospect (saisie ou import), changement de statut (unitaire ou en masse), changement d'étape, opportunité gagnée, réponse reçue, désinscription, rebond définitif.
- Étapes : actions, attentes (jours et heures), conditions avec branches « si vrai » et « sinon » vers n'importe quelle étape ou la fin.
- 8 critères de condition, dont « un contact a répondu depuis le début du scénario » et « un contact s'est opposé ».
- 8 actions : créer une tâche, notifier le responsable, changer le statut, ajouter un tag, envoyer un e-mail (même chaîne de conformité que les campagnes), suspendre les relances (tâches de relance annulées, autres scénarios en attente arrêtés), enregistrer la conversion en client, arrêter.
- Limite de fréquence par fiche (N exécutions sur X jours) et dédoublonnage : un même événement ne lance jamais deux exécutions (contrainte unique).
- Exécutions persistantes, file `automations`, reprise chaque minute par `pm:automations-run` :
  - réservation atomique ;
  - une action réalisée n'est jamais rejouée après une erreur ou un redémarrage ;
  - une exécution bloquée plus de 10 minutes est reprise ;
  - 3 tentatives, puis statut « erreur » et notification au créateur ;
  - une boucle infinie est interrompue au-delà de 50 étapes.
- Versionnage : chaque enregistrement crée une version. La version active ne change qu'à la publication, et les exécutions en cours gardent la leur. Le déclencheur d'un scénario actif ne se modifie qu'après une mise en pause.
- Activation, pause (aucun nouveau déclenchement, les exécutions attendent), reprise, arrêt (toutes les exécutions en attente sont arrêtées).
- Mode test sur une fiche réelle : conditions évaluées, actions décrites sans être exécutées, délais ignorés.
- Journal de chaque exécution, étape par étape, avec la valeur évaluée pour chaque condition.
- 6 modèles prêts à l'emploi, correspondant aux exemples du cahier des charges.
- Constructeur visuel par étapes (ajout, suppression, réordonnancement, branches).

**Notifications** : centre de notifications avec compteur dans le menu, alimenté par l'action « notifier le responsable » et les échecs de scénario ; seuls les liens internes sont suivis.

**Rôles** :
- `automations.manage` : administrateur, responsable commercial, marketing.
- `reports.view` : administrateur, responsable commercial, marketing, conformité.

**Tests ajoutés** (22), couvrant :
- les webhooks : jeton, idempotence, absence de régression, envoi groupé, plainte, désinscription, ouvertures sans consentement ignorées ;
- le suivi interne : conditions d'activation, signature, redirection, désactivation par défaut ;
- les réponses IMAP : par identifiant, par expéditeur, sans double comptage, panne de connexion ;
- les pages de statistiques et de réglages, et leurs droits ;
- le moteur d'automatisation : création de tâche, fréquence et dédoublonnage, notification, délai puis condition, pause et arrêt, réponse qui suspend les relances, désinscription, opportunité gagnée, mode test, versions et validation, boucle infinie, envoi d'e-mail soumis à l'opposition, droits.

Limites :
- La réception réelle des webhooks Brevo et la connexion IMAP réelle n'ont pas pu être testées depuis l'environnement de développement (réseau filtré) : elles sont couvertes par des réponses simulées et se valideront à l'installation.
- Les notifications restent dans l'application : pas d'envoi par e-mail pour l'instant.
- Les statistiques e-mail portent sur toutes les campagnes, sans filtre par équipe.

## Phase 8 — réseaux sociaux

Principe : **suivi manuel / assisté**. Le commercial agit lui-même sur la plateforme, puis consigne l'action dans le module. Aucun compte personnel n'est connecté, aucun profil n'est collecté automatiquement (pas de scraping) et aucune action n'est automatisée sur LinkedIn, Facebook, Instagram ou X. Seule l'API officielle de Meta est branchée, pour les messages reçus par la Page.

Règles revérifiées en octobre 2026 :
- **LinkedIn** : l'API de messagerie reste réservée aux partenaires approuvés, et chaque message doit correspondre à une action d'un membre (pas d'envoi automatisé ou programmé). Le suivi LinkedIn reste donc entièrement manuel.
- **Meta** : on ne peut écrire qu'à une personne qui a contacté la Page, dans les 24 h qui suivent son dernier message. L'étiquette « agent humain » (7 jours) demande un examen de l'application par Meta : elle n'est pas activée.
- Les webhooks Meta sont signés (`X-Hub-Signature-256`) et renvoyés en cas d'échec : ils sont traités de manière idempotente.

**Profils suivis** (rubrique « Réseaux sociaux » de la fiche prospect, file de travail « Réseaux sociaux ») :
- LinkedIn, Facebook, Instagram, X et autre réseau ; profil d'un interlocuteur ou page de l'entreprise.
- Adresse validée et normalisée : https obligatoire, domaine vérifié pour le réseau choisi (un faux domaine du type `linkedin.com.exemple.test` est refusé), paramètres de suivi retirés. Un même profil n'est jamais suivi deux fois pour une entreprise.
- Statuts : à contacter, invitation envoyée, connecté, message envoyé, en discussion, rendez-vous obtenu, pas intéressé, ne plus contacter.
- Actions consignées (invitation envoyée ou acceptée, message envoyé, réponse reçue, commentaire, rendez-vous, autre) : statut proposé automatiquement, date de prochaine action, tâche de relance facultative (type « Réseau social », attribuée au responsable de la fiche). Chaque action alimente l'historique de l'entreprise.
- File de travail en 4 vues (à traiter aujourd'hui, en cours, clos, tous), avec filtres par réseau, statut et responsable. Le périmètre suit celui des fiches (tout, équipe, mes fiches).
- Le profil LinkedIn saisi sur une fiche contact est ajouté au suivi automatiquement.

**Opposition** :
- « Ne plus contacter » s'enregistre par une action dédiée (confirmation obligatoire). Le profil sort de la file de travail et les relances sociales ouvertes sont annulées. L'opposition ne peut être levée ni par une saisie, ni par un changement de statut.
- Elle est conservée sous forme d'empreinte HMAC de l'adresse normalisée, même si le profil ou la fiche est supprimé. Le profil ne peut plus être réintroduit : saisie, fiche contact et import vérifient tous cette liste, et chaque refus est signalé dans le rapport d'import.

**Modèles de messages** : textes courts (note d'invitation, premier message, relance, réponse) avec variables (`{{prenom}}`, `{{entreprise}}`, `{{ville}}`, `{{expediteur}}`…). Une variable inconnue est refusée. Chaque fiche de suivi propose les modèles déjà personnalisés, avec un bouton « Copier » et un compteur de caractères (limite indicative de 300 caractères pour une note d'invitation LinkedIn). Rien n'est envoyé automatiquement.

**Intégration officielle Meta** (Paramètres › Connexion Meta, Messages de la Page) :
- Identifiants (secret de l'application, jeton de Page, jeton de vérification) chiffrés en base, jamais réaffichés ni journalisés. Un champ laissé vide conserve la valeur enregistrée.
- Webhook `GET/POST /webhooks/meta/{jeton}` : vérification de l'abonnement, signature HMAC du corps brut contrôlée avant tout traitement, chaque message enregistré une seule fois (identifiant `mid` unique). Les accusés de lecture et de livraison sont ignorés, et les messages envoyés depuis les outils Meta sont repris comme « envoyés hors du module ».
- Messagerie partagée : une conversation non rattachée est visible de tous les utilisateurs habilités ; une fois rattachée à un prospect, elle suit le périmètre de sa fiche. Le rattachement est manuel (aucun rapprochement automatique d'identité).
- Réponse via l'API (type `RESPONSE`) seulement dans la fenêtre de 24 h et si aucune opposition n'est enregistrée, avec un débit limité. Un message accepté est affiché « accepté par Meta », jamais « lu ».
- Test de connexion à la Page ; les erreurs Meta sont affichées sans le jeton.

**Automatisations** : nouveau déclencheur « Réponse reçue sur un réseau social » (saisie manuelle ou message reçu par la Page rattachée), nouvelle condition « opposition enregistrée sur un réseau social ». La condition « un contact a répondu » tient désormais compte des réponses sur les réseaux. Nouveau modèle de scénario : « Réponse sur un réseau social : tâche de suivi ».

**Import** : nouvelles colonnes « page LinkedIn », « page Facebook » et « compte Instagram » de l'entreprise. Le profil LinkedIn du contact est normalisé et ajouté au suivi. Les adresses invalides et les profils opposés sont écartés, avec un message dans le rapport.

**Statistiques** : invitations, messages, réponses, rendez-vous, taux de réponse déclaré et répartition des profils par réseau et par statut. Ces chiffres sont présentés comme issus des saisies de l'équipe, pas comme des mesures des plateformes.

**Rôles** :
- `social.use` : administrateur, responsable commercial, commercial, marketing.
- `social.templates` : administrateur, responsable commercial, marketing.
- `social.inbox` : administrateur, responsable commercial, commercial, marketing.
- La connexion Meta relève de `settings.manage`.

**Tests ajoutés** (13), couvrant :
- la validation et la normalisation des adresses (dont un faux domaine et `javascript:`) ;
- l'absence de doublons et la mise à jour du statut ;
- les tâches de relance ;
- l'opposition durable, refusée par la saisie, la fiche contact et l'import ;
- les modèles et leurs variables, et les droits ;
- le déclencheur d'automatisation ;
- le chiffrement des secrets Meta et leur non-affichage ;
- la vérification du webhook et la signature ;
- l'idempotence des messages reçus et le rattachement ;
- la fenêtre de 24 h et l'appel à l'API (avec réponse simulée) ;
- les erreurs Meta sans fuite du jeton.

Limites :
- La connexion réelle à Meta n'a pas pu être testée ici (pas d'application Meta, réseau filtré). L'appel est vérifié avec une réponse simulée et se validera avec vos identifiants.
- Pour passer en production, l'application Meta doit obtenir les autorisations `pages_messaging` (et `instagram_manage_messages` pour Instagram) via l'examen de Meta.
- Les pièces jointes reçues ne sont pas téléchargées : elles sont signalées « à consulter sur la plateforme ».
- Le nom de la personne n'est pas récupéré automatiquement auprès de Meta : il se saisit lors du rattachement.
- La levée d'une opposition sociale (par la conformité) sera traitée en phase 9 avec les droits des personnes.

