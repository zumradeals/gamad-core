# CAP-CORE-016 — Secrets & Keys

**Nom :** Registre des secrets et clés
**État réel au commit de tête de ce chantier :** voir `docs/capacites/CATALOGUE.md` — ne jamais présumer `GO` à partir de ce seul document.

Cette fiche décrit le code réellement livré. Elle n'est ni une loi, ni un acte d'adoption, ni une preuve d'exécution en production.

## 1. Objectif

Gouverner les références, versions, usages, rotations, révocations et compromissions des secrets et clés du Core, **sans jamais conserver le matériel secret lui-même**. Le registre répond à « quelle version est active, pour qui, jusqu'à quand, a-t-elle été compromise » — jamais à « quelle est la valeur ».

## 2. Frontières

`CAP-CORE-016` possède les références, versions, usages, dépendances historiques, plans et exécutions de rotation, compromissions, et le matériel public (clés publiques, empreintes, certificats) des secrets et clés du Core.

`CAP-CORE-016` ne possède jamais : une valeur secrète, une clé privée, un mot de passe, une phrase secrète, un code de secours, un jeton de session, un jeton fédéré, un challenge WebAuthn — ces valeurs restent dans `CAP-CORE-005` (mots de passe, passkeys, jetons) ou dans les fournisseurs externes déclarés.

## 3. Modèle de données livré

`core/registre-secrets-cles/src/SchemaSecretsCles.php` — dix tables persistantes dans un magasin `gamad_secrets` séparé (`SECRET_REGISTRY_URL`/`SECRET_REGISTRY_PATH`) :

- `secret_ressource` — référence stable (`SEC-GAMAD-…`/`KEY-GAMAD-…`), type, finalité, propriétaire, source, realm, environnement, classification — **aucune colonne de valeur** ;
- `secret_fournisseur` — type (`FICHIER_0600`, `CREDENTIAL_SYSTEMD`, `VARIABLE_ENVIRONNEMENT_TRANSITION`, `TROUSSEAU_GPG`, `AGENT_SSH`, `FOURNISSEUR_EXTERNE`), état (`PREPARATION → ACTIF/DEGRADE/SUSPENDU/RETIRE`) ;
- `secret_version` — couple `(secret_reference, version)` unique, `handle_fournisseur` opaque, clé publique/empreinte facultatives, **clé privée explicitement refusée à l'écriture** (détection de motif `PRIVATE KEY`) ;
- `secret_version_cycle` — ajout seul, états `PREPARATION → ACTIVE_ECRITURE → ACTIVE_LECTURE → DEPRECIEE/SUSPENDUE/REVOQUEE → COMPROMISE/DETRUITE` ;
- `secret_usage`, `secret_dependance` — fermées par `date_fin`, jamais supprimées ;
- `secret_rotation_plan` — `BROUILLON → EN_VALIDATION → VALIDE → EN_COURS → REUSSI/ECHEC/ANNULE` ;
- `secret_rotation_execution` — ajout seul, une ligne par étape, idempotente ;
- `secret_compromission` — ajout seul, `OUVERTE → CONTENUE → ROTATION_EN_COURS → CLOTUREE` ;
- `secret_materiel_public` — clé publique/certificat/empreinte, jamais de matériel privé.

**Garde absolue** (`RegistreSecretsCles::refuserChampsInterdits()`) : tout dossier portant un champ `value`, `secret`, `private_key`, `password`, `passphrase`, `token`, `credential_content`, `cle_privee`, `mot_de_passe`, `phrase_secrete`, `jeton` ou `valeur` est refusé avant toute écriture, quelle que soit la commande — pas seulement à l'inscription.

## 4. Fournisseurs bornés

Trois adaptateurs implémentés, sur les six types déclarés :

- **`FournisseurFichier0600`** — lecture à la demande d'un fichier dédié ; refuse chemin non absolu, lien symbolique, permissions au-delà de `0600`, fichier trop volumineux, répertoire parent accessible au monde.
- **`FournisseurCredentialSystemd`** — lit depuis `$CREDENTIALS_DIRECTORY/<handle>` fourni par systemd ; ferme si le répertoire est absent, jamais de repli.
- **`FournisseurEnvironnementTransition`** — migration progressive ; refuse une variable non déclarée, aucun fallback silencieux.

Aucune méthode d'export général (`exporterTousLesSecrets()` n'existe pas) ; `avecSecret()` transmet la valeur au seul callback interne fourni, jamais à l'appelant.

**Non livrés dans ce chantier :** `FournisseurTrousseauGpg` et `FournisseurAgentSsh` (types déclarés dans le vocabulaire fermé de `PolitiqueSecretsCles`, mais `AdaptateurParType::resoudre()` ne les résout pas encore) ; `FOURNISSEUR_EXTERNE` n'a pas d'adaptateur générique. Une ressource déclarant l'un de ces types reste gouvernable (référence, version, usage) mais aucune résolution réelle ni vérification serveur n'est possible tant que l'adaptateur correspondant n'est pas ajouté.

## 5. Résolution interne bornée

`ResolveurSecret::avecSecret()` — API PHP interne, jamais une route HTTP. Vérifie realm, environnement, choisit la version active selon le mode (écriture exige `ACTIVE_ECRITURE` ; lecture accepte `ACTIVE_ECRITURE` ou `ACTIVE_LECTURE`), refuse une version compromise/détruite/suspendue, résout l'adaptateur par type de fournisseur (`AdaptateurParType`, partagé avec la CLI), puis délègue au fournisseur.

**L'activation d'une version revérifie toujours le fournisseur en direct côté serveur** (`AccesSecrets::activerVersion()` appelle `RegistreSecretsCles::verifierVersion()` avec l'adaptateur réel avant d'activer) — un client ne peut jamais se déclarer lui-même vérifié en passant un booléen. Ce point a été corrigé en cours de chantier : la première version de l'API acceptait un champ `verifiee` fourni par l'appelant.

## 6. Vocabulaire, politique et contrats

- **Politique (`CAP-CORE-007`)** — `POL-SECRETS-CLES-V1`, dix-sept actions, réservées à `AUT-GAMAD-001` (autorité unique confirmée pour le Core, comme `POL-EVENEMENTS-V1`).
- **Contrats (`CAP-CORE-009`)** — neuf contrats techniques `CTR-GAMAD-SECRETS-*` décrivant les opérations de CAP-CORE-016 elle-même.
- **Vocabulaire (`CAP-CORE-010`)** — **non intégré dans ce chantier.** Les types, états et modes sont des listes PHP fermées dans `PolitiqueSecretsCles`, vérifiées en code, jamais dérivées de texte libre — même limite assumée que `PolitiqueEvenements` pour CAP-CORE-014, documentée comme chantier ultérieur non bloquant dans son propre code.

## 7. Inventaire réel bootstrapé

`core/registre-secrets-cles/resources/bootstrap-secrets-cles-v1.json` (empreinte SHA-256 vérifiée), fondé sur un audit du dépôt sans lecture d'aucune valeur réelle :

- **`SEC-GAMAD-APP-KEY`** — clé applicative Laravel : chiffrement, sessions/cookies, HMAC des descripteurs WebAuthn factices ;
- **douze `SEC-GAMAD-DB-*`** — une par magasin PostgreSQL (index, accès, identités, produits, sources, politiques, contrats, vocabulaire, organisations, realms, journal, événements) ;
- **quatre références de copie hors machine** (`KEY-GAMAD-OFFSITE-GPG`, `SEC-GAMAD-OFFSITE-PASSPHRASE`, `KEY-GAMAD-OFFSITE-SSH`, `SEC-GAMAD-OFFSITE-FTP`) — inscrites en fiche de ressource **seule**, sans fournisseur ni version fictifs, car `GAMAD_OFFSITE_DEST` est vide en production (vérifié par l'audit de cette session) : la copie hors machine reste désactivée.

Treize références obtiennent un fournisseur `VARIABLE_ENVIRONNEMENT_TRANSITION` et une version `1` en `PREPARATION` — **aucune activation automatique**. Le bootstrap est idempotent (vérifié par deux exécutions successives, zéro doublon à la seconde).

## 8. API et console

API v1 (métadonnées seules, jamais de valeur) : 23 routes sous `/api/v1/secrets-cles*`, `/api/v1/fournisseurs-secrets*` et `/api/v1/rotations-secrets/{reference}/{validation,execution}`. Aucune route d'export (`GET .../valeur` n'existe pas). Les réponses de version masquent systématiquement `handle_fournisseur`.

Console (`SecretConsoleController`, 5 routes) : tableau de bord (agrégats de gouvernance, jamais de valeur), fiche ressource (versions, usages, rotations, dépendances), actions de suspension/révocation/compromission avec confirmation JavaScript.

## 9. CLI d'exploitation

`core:secrets:{bootstrap,diagnostiquer,fournisseurs-verifier,rotation-simuler,rotation-executer,version-compromettre,version-detruire}`. Aucune valeur en argument. `version-detruire` refuse `--no-interaction` et exige une confirmation explicite. `rotation-executer` est idempotente (une étape déjà réussie n'est pas rejouée).

## 10. Readiness, sauvegarde et restauration

`EtatFondation` inspecte désormais **treize magasins** : la cible `secrets` vérifie les dix tables et l'absence de doublon de version active en écriture. `ops/core-foundation/backup.sh` et `restore-drill.sh` incluent la cible `secrets` (dump, SHA-256, comptages post-restauration sur `secret_ressource`, `secret_version`, `secret_usage`). `postgresql_p0.sh` provisionne `gamad_secrets`/`drill_secrets` et exécute le cycle complet aux côtés des douze autres magasins.

**Rappel structurel (fiche partie 5 §1, §9) :** la sauvegarde du registre protège la *gouvernance*, jamais le matériel des fournisseurs externes. Pour les quatre références de copie hors machine, aucune procédure de sauvegarde de fournisseur n'est documentée dans ce chantier puisqu'aucun fournisseur réel n'y est encore rattaché.

## 11. Analyse de sécurité du dépôt

`ops/core-foundation/tests/secrets_analyse_depot_p1.sh` — recherche les motifs évidents (clé privée, secret AWS, JWT complet, token GitHub, URI avec mot de passe, fichier `.env` ou de clé privée suivi par Git). Prouve d'abord sa propre capacité à échouer avec des canaris synthétiques avant de scanner le dépôt réel.

## 12. Code livré

```text
core/registre-secrets-cles/
├── README.md, .gitignore
├── resources/bootstrap-secrets-cles-v1.json
├── src/
│   ├── Magasin.php, SchemaSecretsCles.php, PolitiqueSecretsCles.php
│   ├── RegistreSecretsCles.php, ExceptionSecret.php
│   ├── FournisseurSecret.php, AdaptateurParType.php
│   ├── FournisseurFichier0600.php, FournisseurCredentialSystemd.php,
│   │   FournisseurEnvironnementTransition.php
│   ├── ResolveurSecret.php
│   └── DescripteurVersion.php, UsageSecret.php, SensitiveValue.php,
│       DiagnosticFournisseur.php, ResultatDestruction.php
└── tests/secrets_cles_p3.php

apps/console-laravel/
├── app/Application/Secrets/AccesSecrets.php
├── app/Http/Controllers/Api/V1/{Secret,FournisseurSecret,RotationSecret}Controller.php
├── app/Http/Controllers/SecretConsoleController.php
├── app/Console/Commands/{Bootstrap,Diagnostiquer,VerifierFournisseurs,
│   SimulerRotation,ExecuterRotation,CompromettreVersion,DetruireVersion}SecretsCommand.php
├── app/Support/EtatFondation.php (cible secrets)
├── resources/views/secrets-cles/{tableau-de-bord,secret}.blade.php
└── tests/Integration/secrets_cles_v1_p1.php

ops/core-foundation/
├── backup.sh, restore-drill.sh (cible secrets)
└── tests/secrets_analyse_depot_p1.sh
```

## 13. Tests exécutés et résultats réels

Exécutés le 4 août 2026, sur ce commit :

- `core/registre-secrets-cles/tests/secrets_cles_p3.php` — **67/67** épreuves (SQLite) : cycle de version complet, fournisseurs bornés (fichier 0600 valide/trop permissif/lien symbolique/trop volumineux, credential systemd, transition explicite/absente), activation sans vérification refusée, bascule automatique en lecture, suspension/révocation, compromission bloquant immédiatement, destruction refusée si active ou dépendance bloquante puis confirmée après fermeture, rotation planifiée/validée/exécutée par étape idempotente avec reprise après échec, diagnostic cohérent, résolveur borné refusant un secret inconnu ou un environnement différent, contre-épreuve finale.
- `apps/console-laravel/tests/Integration/secrets_cles_v1_p1.php` — **21/21** : parcours HTTP complet — inscription, fournisseur fichier 0600 réel, déclaration et **vérification réelle** d'une version, activation revérifiée serveur, usage, suspension/révocation, compromission, réactivation refusée, rotation planifiée/validée/exécutée, aucune valeur dans aucune réponse, chaînage dans l'audit CAP-CORE-013.
- `ops/core-foundation/tests/secrets_analyse_depot_p1.sh` — **établie**, canaris synthétiques détectés, dépôt réel propre.
- `apps/console-laravel/tests/Integration/api_v1_p1.php` — readiness sur **treize** magasins (mise à jour depuis douze).
- `apps/console-laravel/tests/Integration/migration_config_cache_p1.php` — mis à jour et vérifié : `SECRET_REGISTRY_URL` ajouté aux connexions attendues par `core:fondation:migrer`, sans quoi sa propre contre-épreuve aurait échoué (même piège déjà rencontré et corrigé pour CAP-CORE-014 dans cette même série de sessions).
- **PostgreSQL réel** (`postgresql_p0.sh`, sous l'utilisateur `postgres`) — treize magasins réellement PostgreSQL, sauvegarde et restauration sur treize cibles isolées, comptages post-restauration cohérents.
- Reste de la suite d'intégration existante (38 fichiers : fédération, produits, organisations, realms, sources, politiques, contrats, vocabulaire, événements, accès, passkey, import SQLite, baselines CTR) — **sans régression observée**.
- `core/journal-operationnel/tests/fondation_operationnelle_p3.php` — sans régression.
- Syntaxe PHP de l'ensemble `core/` et `apps/` — aucune erreur.

Total des épreuves propres à CAP-CORE-016 : **88** (67 + 21), plus le cycle PostgreSQL réel et l'analyse de sécurité du dépôt.

**Non exécuté dans ce chantier :** la CI GitHub Actions réelle sur une PR (à confirmer une fois ouverte).

## 14. Limites non bloquantes et réserves

- **Rotation `APP_KEY` non exercée en production.** Le mécanisme de rotation (plan → validation → exécution par étape, idempotente, avec reprise après échec) est codé et éprouvé sur un secret de test réel dans les deux suites de tests — mais aucune rotation réelle de `APP_KEY` n'a été exécutée sur le serveur d'exploitation dans ce chantier. C'est une réserve explicite, pas un chantier caché : la fiche autorise « rotation `APP_KEY` éprouvée **ou explicitement bornée par une preuve équivalente** » (partie 5 §24), et la preuve équivalente ici est l'exercice de rotation complet sur `SEC-GAMAD-P1-TEST` dans `secrets_cles_v1_p1.php`, avec la même mécanique de registre qui gouvernerait `APP_KEY`.
- **Trois fournisseurs sur six types déclarés.** `TROUSSEAU_GPG`, `AGENT_SSH` et `FOURNISSEUR_EXTERNE` n'ont pas d'adaptateur borné dans ce chantier — pertinent notamment pour les quatre références de copie hors machine, qui restent en fiche de ressource seule tant qu'aucun de ces adaptateurs n'existe et que `GAMAD_OFFSITE_DEST` reste vide.
- **Aucune rotation de connexion PostgreSQL, de chiffrement de sauvegarde, SSH ou FTP réellement exécutée** en production — les treize références de connexion sont inscrites en `PREPARATION`, jamais activées ; leur activation réelle nécessiterait une procédure de rotation de mot de passe PostgreSQL hors du périmètre testé ici (fiche partie 3 §10).
- **Vocabulaire CAP-CORE-010 non intégré** : types et états sont des listes PHP fermées, pas des termes du registre persistant — limite assumée, alignée sur le précédent de CAP-CORE-014.
- **Propriétaire, source et realm ne sont pas cross-vérifiés** contre CAP-CORE-001/006/012 au niveau du registre — ils sont validés en format et en appartenance à une liste close, pas en existence/activité réelle. Une dépendance facultative non câblée, comme documenté au moment de livrer le registre (§3 du rapport de commit initial).
- **Métriques et alertes externes non implémentées** — aucune capacité du dépôt n'a ce type d'infrastructure à ce jour ; même réserve que pour CAP-CORE-014.
- **Un seul consommateur de conformité** (le parcours HTTP `secrets_cles_v1_p1.php`) exerce la résolution bornée réelle ; aucun consommateur de production (par exemple `ops/core-foundation/backup.sh` lisant réellement une clé de chiffrement via `ResolveurSecret`) n'est encore migré vers ce registre dans ce chantier — la migration progressive (phases A à E de la fiche partie 3 §17) s'arrête à la phase B (registre sans bascule).

## 15. Décision

```text
CAP-CORE-016 — GO
```

Sous réserve explicite des limites listées en §14 — en particulier l'absence de rotation `APP_KEY` réellement exécutée et les trois fournisseurs non livrés — à traiter au premier besoin réel de migration d'un consommateur, pas par anticipation dans ce chantier.
