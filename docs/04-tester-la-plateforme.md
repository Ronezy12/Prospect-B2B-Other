# Tester la plateforme

## 1. Aperçu immédiat (sans installation)

Un aperçu cliquable des écrans réels, avec des données fictives, est publié ici : https://claude.ai/artifact/UX9eoxccjdTrMgRy7XvDTD
La navigation fonctionne ; les enregistrements, filtres et imports y sont désactivés.

## 2. Tester l'application réelle avec Docker (ordinateur ou VPS)

Prérequis : [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Windows / macOS) ou Docker Engine (Linux).

```bash
git clone -b claude/prospection-marketing-module-yphs51 https://github.com/Ronezy12/Prospect-B2B-Other.git
cd Prospect-B2B-Other
docker compose up --build
```

Ouvrir ensuite http://localhost:8000.

Comptes de démonstration (mot de passe commun : `Demo-Prospection-2026`) :

| Rôle | E-mail | Ce qu'il permet de vérifier |
|---|---|---|
| Administrateur | admin@demo.test | tout, gestion des utilisateurs, journal d'audit |
| Responsable commercial | responsable@demo.test | périmètre équipe, actions groupées, export |
| Commercial | commercial@demo.test | uniquement ses fiches |
| Commercial hors équipe | commercial2@demo.test | ne voit pas les fiches de l'équipe |
| Conformité / DPO | conformite@demo.test | lecture, oppositions, audit |

Les 24 entreprises de démonstration sont fictives (domaines `.test`, tag « Exemple démo »). Les données sont conservées dans le volume Docker `prospection-data` ; pour repartir de zéro : `docker compose down -v`.

Ce mode de démonstration (SQLite, `APP_ENV=demo`) sert au test. Pour la production, suivre [02-installation-exploitation.md](02-installation-exploitation.md) (MySQL, HTTPS, Supervisor, sauvegardes) ; la commande `pm:demo` y est refusée.

## 3. Scénarios de recette suggérés

1. Se connecter en commercial, vérifier qu'on ne voit que ses fiches ; ouvrir l'URL d'une fiche d'un collègue → accès refusé.
2. Créer un prospect avec un interlocuteur ; recréer la même entreprise → alerte de doublon.
3. Importer un CSV (séparateur `;` ou `,`, export Excel accepté) ; vérifier l'aperçu, la correspondance des colonnes, puis le rapport ligne à ligne.
4. Enregistrer une opposition sur un contact, puis tenter de le réimporter → ligne « Contact exclu (opposition) ».
5. En responsable : sélectionner plusieurs fiches, changer le statut en masse ; exporter la liste filtrée.
6. En administrateur : convertir un prospect en client, vérifier qu'il disparaît de la base de prospects et apparaît dans le filtre « Convertis en clients ».
7. Ouvrir « Pipelines », glisser une carte vers une autre colonne, recharger la page : la carte est restée à sa place. Ouvrir la fiche de l'opportunité pour voir l'historique des étapes.
8. Depuis une fiche prospect, créer une opportunité puis « Planifier une relance » ; la retrouver dans « Tâches et relances » et la marquer comme faite.
9. En administrateur, « Secteurs et pipelines » : ajouter un secteur (sa pipeline est créée), renommer une étape, tenter de supprimer une étape non vide (refusé).
10. Basculer le thème clair / sombre (icône lune en bas de la barre latérale) et tester sur mobile.
