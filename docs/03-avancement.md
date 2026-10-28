# Avancement par phase

| Phase | État |
|---|---|
| 1 — Audit | Terminé (dépôt vide, projet autonome) |
| 2 — Cahier technique | Terminé — [01-audit-et-cahier-technique.md](01-audit-et-cahier-technique.md) |
| 3 — Validation | Terminé (décisions consignées dans le cahier) |
| 4 — Socle et gestion des prospects | **Terminé** (détail ci-dessous) |
| 5 — Pipelines, tâches, permissions avancées | À faire |
| 6 — E-mails, modèles, programmation | À faire |
| 7 — Statistiques, webhooks, automatisations | À faire |
| 8 — Réseaux sociaux | À faire |
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

**Tests** : 44 tests automatisés (connexion, limitation, périmètres et droits, validation, doublons, conversion, export, imports CSV/XLSX, encodages, stratégies de doublons, opposition, idempotence, purge).

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
