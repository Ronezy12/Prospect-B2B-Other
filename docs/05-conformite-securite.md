# Conformité RGPD et sécurité

> Ce module met en place des mesures techniques qui facilitent le respect du RGPD et des règles françaises de prospection (CNIL). **Il ne garantit pas, à lui seul, la conformité juridique** : les bases légales, les durées et le registre doivent être validés par votre DPO ou votre conseil.

## 1. Règles appliquées automatiquement

| Règle | Où | Comment |
|---|---|---|
| Liste d'opposition centralisée | Avant chaque envoi, import, scénario ou synchronisation | Empreinte HMAC de l'adresse (`PM_SUPPRESSION_KEY`). Une adresse opposée n'est jamais réintroduite. |
| Désinscription en un clic | Chaque e-mail de prospection et de newsletter | Lien signé et en-têtes `List-Unsubscribe` / `List-Unsubscribe-Post` (RFC 8058). |
| Particuliers (B2C) | Envois de prospection | Exclus sans consentement enregistré. |
| Pas d'insistance | Envois groupés | Les personnes contactées depuis moins de 30 jours sont exclues par défaut. |
| Suivi des ouvertures et des clics | Désactivé par défaut | Il faut l'activer globalement, sur l'envoi, et avoir le consentement du contact (recommandation CNIL de 2026 sur les pixels). Une ouverture n'est jamais présentée comme une lecture. |
| Réseaux sociaux | Suivi manuel | Aucune collecte ni action automatisée. Les oppositions sur un profil sont conservées sous forme d'empreinte. |
| Envoi « accepté » ≠ « livré » | Statistiques | La livraison n'est affichée que si un fournisseur la confirme (webhooks). |

## 2. Droits des personnes — Conformité RGPD › Demandes des personnes

- **Rechercher** une adresse e-mail pour voir tout ce que l'outil contient sur elle : fiches, e-mails reçus, abonnements, profils sociaux, mentions dans l'historique, opposition.
- **Accès / portabilité** : export JSON lisible.
- **Effacement** : il faut retaper l'adresse pour confirmer.
  - Les fiches contact sont anonymisées puis supprimées.
  - L'adresse est retirée des e-mails envoyés, des événements, des abonnements, de l'historique et du journal d'audit (valeurs masquées).
  - Les profils sociaux sont supprimés et leur opposition est enregistrée.
  - Seule l'empreinte de l'adresse est conservée dans la liste d'opposition, pour qu'elle ne soit pas réimportée.
- **Registre des demandes** : type, date de réception, échéance d'un mois (retards signalés), clôture avec ce qui a été fait ou le motif du refus. L'adresse n'y figure que sous forme d'empreinte.
- **Rectification** : directement sur la fiche contact.

## 3. Liste d'opposition — Conformité RGPD › Liste d'opposition

- Compteurs par motif : opposition, désinscription, plainte, adresse invalide, effacement.
- Vérification d'une adresse (les adresses ne sont pas stockées en clair).
- **Levée** réservée au rôle conformité ou administrateur. Elle exige une justification d'au moins 15 caractères (par exemple un nouveau consentement écrit) et une confirmation. Elle est tracée dans le registre des demandes et dans le journal d'audit.

## 4. Durées de conservation — Conformité RGPD › Conservation

Appliquées chaque nuit à 03:30 par `pm:retention`. La commande `php artisan pm:retention --simulation` montre ce qui serait traité, sans rien modifier.

| Donnée | Durée par défaut | Variable |
|---|---|---|
| Prospect sans aucun échange (ni appel, ni note, ni réponse, ni affaire en cours) | 36 mois, puis anonymisation des interlocuteurs et archivage de la fiche | `PM_RETENTION_PROSPECT_MONTHS` |
| Ouvertures et clics | 13 mois | `PM_RETENTION_TRACKING_MONTHS` |
| Journal d'audit | 60 mois | `PM_RETENTION_AUDIT_MONTHS` |
| Notifications lues | 6 mois | — |
| Fichiers d'import | 30 jours | `PM_IMPORT_RETENTION_DAYS` |
| Liste d'opposition | Sans limite (empreinte uniquement) | — |

Les clients ne sont jamais archivés automatiquement.

## 5. Registre des traitements — Conformité RGPD › Registre

Fiche « article 30 » pré-remplie à partir du fonctionnement réel de l'outil : finalités, bases légales, données, destinataires, durées et mesures de sécurité. Elle est imprimable (bouton « Imprimer / PDF »). Les champs entre crochets sont à compléter : responsable, DPO, hébergeur, transferts hors UE (Google, Meta).

## 6. Sécurité

- **Comptes** : mots de passe hachés (bcrypt), 12 caractères minimum avec majuscules, minuscules et chiffres. Connexion limitée à 5 essais par minute et message d'erreur générique. Compte désactivable.
- **Double authentification** (application TOTP : Google Authenticator, Microsoft Authenticator…) :
  - obligatoire pour les rôles listés dans `PM_REQUIRE_2FA_ROLES` (par défaut `administrateur`) ; elle n'est pas imposée en mode démonstration ;
  - facultative pour les autres, dans le menu Sécurité (icône bouclier en bas du menu) ;
  - secret chiffré en base, code non réutilisable, 8 codes de secours hachés à usage unique, essais limités ;
  - QR code généré dans le navigateur, sans service externe.
- **Droits** : chaque action est vérifiée côté serveur, à la fois par permission et par périmètre de données (mes fiches, mon équipe, tout).
- **Secrets** : mots de passe SMTP et IMAP, clés API, jetons Meta et webhooks chiffrés en base. Ils ne sont jamais réaffichés, journalisés ni présents dans le dépôt Git.
- **Web** :
  - protection CSRF et échappement de toutes les sorties ;
  - requêtes SQL préparées (Eloquent) ;
  - politique de sécurité de contenu stricte (`script-src 'self'`, `style-src 'self'`, `object-src 'none'`) ;
  - HSTS en HTTPS, `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy` ;
  - pages connectées non mises en cache.
- **Exports** : protection contre l'injection de formules dans Excel.
- **Webhooks** : jeton secret dans l'URL (Brevo), signature HMAC `X-Hub-Signature-256` (Meta), traitement idempotent.
- **Journal d'audit** : connexions, exports, envois, oppositions, effacements, levées, changements de droits.
- **Dépendances** : `composer audit` ne signale aucune vulnérabilité connue (vérifié lors de la phase 9). À relancer à chaque mise à jour.

## 7. Envoi avec Gmail — points d'attention

- **Signature** : celle configurée dans Gmail n'est **pas** ajoutée aux envois faits par le module (envoi SMTP). Recopiez-la dans Paramètres › Envoi d'e-mails › votre adresse › « Signature ». Elle est ajoutée en bas de chaque e-mail, avant le lien de désinscription, ou à l'emplacement `{{signature}}` d'un modèle.
- **Mot de passe d'application** obligatoire : compte Google › Sécurité › Validation en deux étapes › Mots de passe des applications.
- **L'adresse d'expédition** doit être celle du compte Gmail (ou un alias vérifié dans Gmail).
- **Quotas Google** : environ 500 destinataires par jour pour un compte Gmail gratuit, environ 2 000 pour Google Workspace. Le préréglage fixe une limite prudente (400 par jour, 40 par heure).
- **Plusieurs adresses** : l'envoi se répartit entre les adresses cochées, chacune dans ses limites. Une même personne reçoit toujours nos e-mails de la même adresse.
- **Délivrabilité** : multiplier les comptes Gmail ne contourne pas les filtres anti-spam. Envoyez des volumes modérés à des contacts pertinents. Pour de gros volumes, préférez un domaine professionnel (Google Workspace avec SPF, DKIM et DMARC configurés) ou un fournisseur dédié (Brevo).
