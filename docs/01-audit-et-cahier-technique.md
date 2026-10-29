# Prospection & Marketing — Entreprise AR
## Phase 1 (audit) et Phase 2 (cahier technique)

Date : 28/10/2026 — Statut : **validé le 28/10/2026** (décisions ci-dessous).

> **Décisions validées (phase 3)**
> - Projet **autonome**, sans lien avec un CRM existant.
> - Framework : **Laravel** (version 13 installée, PHP ≥ 8.3).
> - Hébergement : **VPS / serveur dédié** (worker de file supervisé + cron).
> - Fournisseur e-mail API : **aucun choisi pour l'instant** ; le module prévoit un emplacement « fournisseur API » configurable (type + clé chiffrée) à activer plus tard. Le SMTP sera fonctionnel en phase 6.
> - Éditeur de modèles : **GrapesJS**.
> - Charte graphique : aucune fournie → charte sobre neutre (modifiable via les variables CSS de `public/css/pm.css`).

---

## 1. Architecture réelle constatée

| Élément | Constat |
|---|---|
| Dépôt `Prospect-B2B-Other` | **Vide** (aucun commit, aucun fichier). Projet démarré de zéro. |
| CRM existant | Hors périmètre sur décision du porteur de projet (28/10/2026) : le module est un **nouveau projet autonome**. |
| PHP disponible (environnement de développement) | 8.3.6 CLI, extensions : pdo_mysql, pdo_sqlite, pdo_pgsql, mbstring, intl, openssl, sodium, curl, zip, gd, fileinfo, redis, pcntl. **Pas d'ext-imap.** |
| Composer | Présent ; Packagist joignable. |
| Base de données | Pilotes MySQL/PostgreSQL/SQLite présents ; **aucun serveur MySQL** dans l'environnement de développement (tests sur SQLite, cible production à confirmer). |
| Node | v22 (utilisable pour compiler les assets front en développement). |
| Hébergement de production | **Inconnu** — décision bloquante (voir §8). |
| Cron / files / sauvegardes / déploiement | Inexistants — à créer. |
| Accès réseau sortant aux API tierces (Brevo…) | Bloqué depuis l'environnement de développement : les intégrations seront testées par des doubles de test + une recette sur l'hébergement réel. |

Conséquences directes :
- Il n'y a **pas d'authentification, de table utilisateurs ni de design existants** à réutiliser : le module doit les fournir lui-même (justification de la règle « pas de seconde authentification » : il n'en existe pas de première).
- La consigne « reprendre le design du CRM » ne peut pas s'appliquer : une charte sobre et professionnelle sera proposée, sans identité visuelle tapageuse (à valider).

## 2. Fonctionnalités existantes à réutiliser

Aucune dans le dépôt. On s'appuiera sur des composants éprouvés (§7) plutôt que de réécrire authentification, CSRF, migrations, files d'attente et planificateur.

## 3. Risques identifiés

| Risque | Mitigation |
|---|---|
| Hébergement mutualisé sans processus long (pas de worker permanent) | File d'attente en base + cron toutes les minutes qui traite des lots bornés en temps. Fonctionne aussi sur VPS (worker supervisé). |
| Double envoi après crash / cron concurrent | Verrous de traitement, clé d'idempotence unique par (campagne, destinataire), statut machine à états, `X-Mailin-custom`/message-id fournisseur. |
| Webhooks dupliqués / désordonnés | Table d'événements avec clé unique (fournisseur, id événement) ; statut agrégé calculé par priorité, jamais régressif. |
| Réintroduction d'une personne opposée | Liste de suppression centralisée par **empreinte HMAC de l'e-mail normalisé**, conservée même après effacement de la fiche ; vérifiée à l'import, dans les scénarios et juste avant chaque appel fournisseur. |
| Pixel d'ouverture non conforme (CNIL 2026) | Suivi des ouvertures **désactivé par défaut**, activable uniquement pour les destinataires ayant consenti (voir §5). |
| Secrets exposés | Secrets en `.env` hors dépôt ; clés fournisseurs saisies dans l'interface chiffrées (libsodium, clé applicative) et jamais renvoyées en clair. |
| Gros imports qui expirent | Import découpé en tâches de file (lots de 500 lignes), progression persistée, reprise possible. |

## 4. Choix techniques recommandés

| Besoin | Recommandation | Licence | Pourquoi |
|---|---|---|---|
| Framework | **Laravel** (dernière version stable compatible PHP 8.3) | MIT | Auth, CSRF, validation, policies, migrations réversibles, files d'attente, planificateur, tests : tout ce que le cahier exige, maintenu et documenté. Fonctionne en mutualisé (cron) et VPS. |
| Alternative | PHP « maison » sans framework | — | Plus léger mais impose d'écrire et sécuriser soi-même routeur, CSRF, migrations, file, planificateur → plus de risque. **Non recommandé.** |
| Rôles & droits | spatie/laravel-permission | MIT | Rôles + permissions par action, standard de fait. Le périmètre de données (« mes prospects » / « mon équipe » / « tous ») est géré par des policies propres au module. |
| Import CSV | league/csv | MIT | Lecture en flux, gros fichiers. |
| Import Excel | phpoffice/phpspreadsheet (lecture en mode « read data only » + chunk) | MIT | Seule bibliothèque PHP mature pour .xlsx. |
| SMTP | Symfony Mailer (inclus dans Laravel) | MIT | — |
| API Brevo | Client HTTP Laravel (pas de SDK) | — | Moins de dépendances, contrôle total des erreurs/idempotence. |
| Réponses (IMAP) | webklex/php-imap | MIT | Pur PHP (ext-imap absente et retirée du cœur PHP 8.4). |
| Interface | Blade + Alpine.js | MIT | Rendu serveur rapide, peu de JavaScript, pas de SPA à maintenir. |
| Kanban | SortableJS | MIT | Glisser-déposer accessible et léger. |
| Éditeur d'e-mails | **GrapesJS + preset newsletter** | BSD-3 | Éditeur visuel par blocs pour non-techniciens, export HTML. Alternative plus simple : éditeur de blocs maison (texte, image, bouton, colonnes). À valider. |
| Graphiques | Chart.js | MIT | — |

Base de données : **MySQL 8 / MariaDB 10.6+** en production (standard des hébergeurs français), SQLite pour les tests automatisés. Le schéma reste portable (pas de fonctionnalités propres à un moteur).

## 5. Fournisseur e-mail et conformité du suivi

### 5.1 Architecture hybride
Interface commune `MailProvider` :
`send(Message): SendResult`, `sendBatch(array): array`, `parseWebhook(Request): Event[]`, `verifyWebhook(Request): bool`, `capabilities(): Capabilities`, `quota(): ?Quota`.

Implémentations : `SmtpProvider` (Symfony Mailer) et `BrevoProvider` (API v3). Chaque campagne référence un **compte fournisseur** configuré ; un fournisseur sans webhooks (SMTP) affiche les statistiques correspondantes comme **« indisponibles »** et non à zéro.

Statuts d'un envoi (jamais confondus) : `planifié → en_file → demandé → accepté_fournisseur → délivré | rebond_temporaire | rebond_définitif | bloqué | échec` ; ouvertures, clics, réponses, désabonnements sont des **événements** séparés.

### 5.2 Brevo (proposé comme fournisseur API principal — à confirmer)
Relevé public au 06/07/2026 (source tierce, **à revérifier sur brevo.com avant souscription**) : offre gratuite 300 e-mails/jour ; Starter dès 7 €/mois pour 5 000 e-mails ; Standard dès 15 €/mois ; transactionnel inclus dans tous les forfaits. Atouts : société française, hébergement UE, API transactionnelle + webhooks (delivered, soft/hard bounce, opened, click, unsubscribed, spam, blocked), gestion native des listes de blocage.
Alternatives à comparer si besoin : Mailjet (FR), Scaleway TEM (FR, très bon marché, statistiques plus limitées), Amazon SES eu-west-3 (Paris, le moins cher à fort volume, plus technique).
Le module gardera **sa propre liste de suppression** qui fait foi, synchronisée vers le fournisseur.

### 5.3 Suivi des ouvertures — recommandation CNIL 2026
La CNIL a adopté le 12/03/2026 (délibération n° 2026-042, publiée le 14/04/2026) une recommandation sur les pixels de suivi : **consentement préalable par principe** (art. 82 loi Informatique et Libertés), exceptions limitées (sécurité/authentification ; mesure de délivrabilité pour retirer les inactifs, pour des e-mails sollicités, hors prospection promotionnelle). Implémentation proposée :
- Pixel d'ouverture **désactivé par défaut** au niveau global et par campagne.
- Insérable uniquement pour les contacts ayant un consentement « mesure d'audience e-mail » tracé (date, source, preuve).
- Ouvertures toujours libellées « ouvertures détectées (indicatif) », jamais « lectures ».
- Suivi des clics : configurable séparément, mêmes règles d'information.
Ces points sont une interprétation technique : **validation par un juriste/DPO requise** avant activation.

### 5.4 Règles de prospection implémentées (France/UE)
| Cible / finalité | Règle encodée par défaut |
|---|---|
| B2B, e-mail professionnel nominatif, offre en rapport avec la fonction | Envoi possible sans consentement préalable (intérêt légitime), information + lien d'opposition obligatoires dans chaque message. |
| B2C / adresse personnelle | **Consentement préalable requis** (bloquant). |
| Clients existants, produits analogues | Autorisé sauf opposition, information à la collecte. |
| Newsletter | Abonnement explicite (double opt-in recommandé et activable). |
| Opérationnel (facture, RDV) | Hors marketing ; ne contient pas de contenu promotionnel ; non bloqué par une désinscription marketing, bloqué par une adresse invalide. |
Chaque contact porte le type de cible (B2B/B2C), la base légale, la source et la date de collecte. Une campagne **ne peut pas être lancée** si un destinataire ne satisfait pas la règle de sa catégorie (il est exclu et la raison est journalisée).

## 6. Réseaux sociaux (revérifié en phase 8, octobre 2026 — voir 03-avancement.md)
- **LinkedIn** : les API de messagerie et d'accès aux profils sont réservées à des partenaires approuvés ; aucune API ouverte pour la prospection automatisée. → Suivi **manuel/assisté** (URL de profil, statut de prise de contact, lien direct).
- **Facebook / Instagram (Meta Graph API)** : messagerie autorisée uniquement en réponse à une personne ayant contacté la Page (fenêtre de 24 h). → Suivi manuel ; connexion officielle de Page envisageable plus tard pour importer les conversations entrantes.
- Aucun scraping, aucune automatisation d'actions non permise.

## 7. Schéma de données proposé

Conventions : tables préfixées `pm_` (module), clés `BIGINT UNSIGNED` auto-incrémentées, `created_at`/`updated_at`, suppression logique (`deleted_at`) sur les données métier, clés étrangères explicites. Migrations Laravel avec `down()` systématique.

| Table | Rôle | Relations / contraintes / index clés | Suppression |
|---|---|---|---|
| `users` | Utilisateurs du module (n'existe pas ailleurs) | unique(email) | logique |
| `roles`, `permissions`, pivots | spatie/laravel-permission | — | cascade pivots |
| `pm_teams`, `pm_team_user` | Périmètre « mon équipe » | unique(team_id,user_id) | cascade pivot |
| `pm_sectors` | Secteurs (immobilier, auto, restaurants, syndics, tous secteurs, autres) | unique(slug), parent_id (sous-secteur) | restrict si utilisé |
| `pm_sources` | Sources d'acquisition | unique(name) | restrict |
| `pm_companies` | Entreprises prospects | siren/siret (nullable, unique si présent), sector_id, owner_id, status, index(name), index(owner_id,status), index(postal_code) | logique |
| `pm_contacts` | Interlocuteurs | company_id (nullable), email_normalized (index), audience_type (b2b/b2c), legal_basis, collected_at, source_id, owner_id | logique |
| `pm_tags`, `pm_taggables` | Tags polymorphes | unique(tag_id,taggable_type,taggable_id) | cascade |
| `pm_custom_fields` (option) | Champs libres par secteur | — | — |
| `pm_pipelines` | 1 pipeline par secteur (+ globale) | unique(sector_id) | restrict |
| `pm_pipeline_stages` | Étapes configurables, ordre, type (ouvert/gagné/perdu) | unique(pipeline_id,position) | restrict si opportunités |
| `pm_opportunities` | Affaire liée à entreprise/contact | pipeline_id, stage_id, owner_id, priority, next_action, next_action_at (index), value | logique |
| `pm_stage_history` | Historique des changements d'étape | opportunity_id, from/to, user_id, at | cascade |
| `pm_activities` | Journal (appel, e-mail, note, RDV, réseau social) | subject polymorphe, user_id, occurred_at (index) | logique |
| `pm_tasks` | Tâches et relances | assignee_id, due_at (index), status, subject polymorphe | logique |
| `pm_imports`, `pm_import_rows` | Import, mapping, rapport ligne à ligne (erreurs, doublon, action) | import_id, status | cascade lignes, purge planifiée |
| `pm_mail_accounts` | Comptes fournisseurs (smtp/brevo), secrets chiffrés, quotas | — | restrict si utilisés |
| `pm_templates`, `pm_template_versions` | Modèles et versions immuables | unique(template_id,version) | logique |
| `pm_segments` | Filtres enregistrés (JSON validé) | — | logique |
| `pm_campaigns` | Campagne : type (individuel/prospection/newsletter/opérationnel), version figée, expéditeur, segment, programmation, état | mail_account_id, template_version_id, index(status,scheduled_at) | logique |
| `pm_campaign_recipients` | Destinataire figé + statut d'envoi | unique(campaign_id,contact_id), unique(idempotency_key), provider_message_id (index) | cascade |
| `pm_email_events` | Événements fournisseur bruts et normalisés | unique(provider,provider_event_id), recipient_id, type, occurred_at | conservation configurable |
| `pm_newsletter_lists`, `pm_subscriptions` | Abonnements, double opt-in, preuve | unique(list_id,contact_id) | — |
| `pm_consents` | Preuves de consentement par finalité/canal (historisées) | contact_id, purpose, channel, granted, source, at | jamais modifiées (append-only) |
| `pm_suppressions` | Liste d'opposition centrale | unique(email_hash, scope), reason, at | **jamais supprimée** par effacement de fiche |
| `pm_automations`, `pm_automation_versions` | Scénarios et versions (graphe JSON validé) | unique(automation_id,version) | logique |
| `pm_automation_runs`, `pm_automation_steps` | Exécutions persistantes, étape courante, `run_at`, tentatives | unique(automation_version_id,subject,trigger_event_id), index(status,run_at) | purge planifiée |
| `pm_social_profiles`, `pm_social_interactions` | Profils/pages et prises de contact | platform, url, contact/company, owner, status, next_action_at | logique |
| `pm_settings` | Paramètres (rétention, suivi, plafonds) | unique(key) | — |
| `pm_audit_logs` | Journal d'audit (qui, quoi, quand, avant/après sans secrets) | index(subject), index(user_id,created_at) | append-only, rétention configurable |
| `jobs`, `failed_jobs`, `job_batches`, `cache_locks` | Infrastructure Laravel | — | — |

Séparation prospects/clients : un champ `lifecycle` (`prospect` / `client`) sur `pm_companies`, conversion **uniquement** par action explicite (permission dédiée, journalisée), jamais par import ou automatisation.

## 8. Fichiers et composants à créer

```
app/Modules/Prospection/   (Domain : Models, Services, Policies, Jobs, Providers/Mail, Automation, Compliance, Import)
app/Http/Controllers/Prospection/*
resources/views/prospection/*        (16 écrans du cahier)
database/migrations/*_pm_*.php
database/seeders/ (secteurs, étapes par défaut, rôles)
routes/web.php (groupe /prospection, middleware auth + permissions), routes/webhooks.php
tests/Feature/*, tests/Unit/*
docs/ (installation, configuration, sauvegardes, maintenance, registre des traitements)
```

Rôles proposés : Administrateur, Responsable commercial (périmètre équipe), Commercial (périmètre personnel), Marketing (campagnes, pas d'export complet), Lecture seule, DPO/Conformité (oppositions, audit, droits des personnes).

## 9. Dépendances externes nécessaires
- Hébergement PHP ≥ 8.2 avec cron (1 min), MySQL/MariaDB, HTTPS.
- Compte Brevo (ou équivalent) + domaine d'envoi authentifié **SPF, DKIM, DMARC**.
- Optionnel : boîte IMAP dédiée aux réponses (ou adresse de réponse Brevo inbound parsing).
- Validation juridique (DPO/avocat) des règles §5.3–5.4.

## 10. Plan de développement
| Phase | Contenu | Livrable vérifiable |
|---|---|---|
| 3 | Validation de ce document | Décisions consignées |
| 4 | Squelette Laravel, auth, rôles, entreprises/contacts, création/édition/masse, imports CSV/Excel avec mapping, doublons, export | Tests verts, import d'un fichier réel |
| 5 | Secteurs, pipelines configurables, Kanban, tâches/relances, périmètres de données | Tests de droits |
| 6 | Comptes fournisseurs, modèles/éditeur, segments, campagnes, file d'envoi, programmation, arrêt | Envoi réel via SMTP de test |
| 7 | Webhooks Brevo, statistiques, moteur d'automatisation, IMAP | Rejeu de webhooks dupliqués en test |
| 8 | Suivi réseaux sociaux | — |
| 9 | Conformité (droits, rétention, registre), revue sécurité, documentation | Checklist de recette §16 |

## 11. Points bloquants / décisions à confirmer
1. Framework : Laravel (recommandé) ou PHP sans framework.
2. Hébergement cible : mutualisé (o2switch, OVH…) ou VPS/serveur dédié.
3. Fournisseur API : Brevo (recommandé) ou autre.
4. Éditeur d'e-mails : GrapesJS ou éditeur de blocs simplifié.
5. Charte graphique : couleurs/logo Entreprise AR à fournir, sinon charte sobre neutre.
6. Plateformes sociales concernées (LinkedIn, Facebook, Instagram, autres ?).
7. Interlocuteur juridique pour valider les règles de conformité.

---
Sources consultées : [it-connect.fr – pixels de suivi CNIL](https://www.it-connect.fr/pixels-de-suivi-emails-cnil-consentement/), [Norton Rose Fulbright – CNIL recommendation](https://connections.nortonrosefulbright.com/post/102o01q/tracking-pixels-in-emails-a-cnil-recommendation-to-clarify-practices-and-secure), [Lextenso – pixels de suivi](https://www.labase-lextenso.fr/l-essentiel-droit-du-numerique/2026-n6/pixels-de-suivi-dans-les-courriels-la-cnil-etend-la-logique-des-cookies-a-l-e-mailing-DNU100f8), [La Fabrique du Net – tarifs Brevo](https://www.lafabriquedunet.fr/email-marketing/articles/tarifs-brevo/), [Brevo – logiciel newsletter](https://www.brevo.com/fr/logiciel-newsletter/).
