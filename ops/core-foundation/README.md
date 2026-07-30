# Core Operational Foundation v1 — exploitation

Ce lot remplace le démarrage direct de `core/registre-normes/public` par le
monolithe Laravel. Il ne déploie pas vingt-et-un microservices.

La cible unique est le serveur actuel :

```text
Nginx HTTPS
→ /var/www/gamad-core/apps/console-laravel/public/index.php
→ PHP 8.3 FPM
→ quatre bases PostgreSQL locales dédiées
```

L'ancien `nixpacks.toml`, qui décrivait un déploiement Railway abandonné, est
retiré du dépôt afin qu'il ne puisse plus être pris pour une cible active.

## Topologie minimale

Quatre bases PostgreSQL physiquement distinctes sont attendues :

| Cible | Variable applicative | Contenu |
|---|---|---|
| index | `DATABASE_URL` | index documentaire reconstructible |
| accès | `MAGASIN_URL` | authentificateurs et sessions Core |
| identités | `IDENTITY_REGISTRY_URL` | Identity Registry persistant |
| journal | `JOURNAL_OPERATIONNEL_URL` | événements d'exploitation et audit |

Laravel stocke aussi ses sessions web dans la cible `gamad_access` avec :

```text
DB_CONNECTION=gamad_access
SESSION_DRIVER=database
SESSION_CONNECTION=gamad_access
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
```

En production, définir `GAMAD_INDEX_DRIVER`, `GAMAD_ACCESS_DRIVER`,
`GAMAD_IDENTITY_DRIVER` et `GAMAD_JOURNAL_DRIVER` à `pgsql`. La readiness
refuse SQLite en production.

Pour les essais SQLite locaux uniquement, les répertoires `var/` des modules
doivent appartenir à l'utilisateur PHP-FPM. En production PostgreSQL, aucun
fichier de données applicatif ne doit y être créé.

## Ordre d'une livraison sur ce serveur

La livraison locale doit exécuter, sous l'utilisateur applicatif, l'ordre
suivant avant le redémarrage du service :

```text
php artisan migrate --force
php artisan core:fondation:migrer --force
php artisan registre:reindexer
php artisan core:journal:verifier
```

La mise en service n'a lieu que si `/api/v1/health/ready` retourne HTTP 200.
Le cache Laravel est reconstruit localement avec `php artisan optimize` après
les migrations et avant le rechargement de PHP-FPM.
Les migrations de ce lot sont additives. Un rollback applicatif consiste à
revenir au commit/release précédent puis à recharger PHP-FPM;
aucune suppression de table n'est exécutée pendant ce rollback.

## Activer l'authentification forte A2

La console utilise WebAuthn/passkeys : le Core conserve la clé publique et le
compteur de signature, jamais la clé privée ni la biométrie de l'utilisateur.
La production exige une origine HTTPS fermée et un identifiant RP égal à
l'hôte de `APP_URL` :

```text
GAMAD_PASSKEY_RP_NAME=GAMAD Core
GAMAD_PASSKEY_RP_ID=console.dgafrique.com
GAMAD_PASSKEY_ALLOWED_ORIGINS=https://console.dgafrique.com
```

Après les migrations, un opérateur prépare un enrôlement à usage unique :

```text
php artisan identite:preparer-passkey AUT-GAMAD-001
```

Le jeton n'est affiché qu'une fois et seul son SHA-256 est conservé. Il expire
après dix minutes par défaut. L'utilisateur ouvre ensuite
`/passkeys/enrolement`; la vérification utilisateur de l'authenticator est
obligatoire. Les challenges WebAuthn expirent après cinq minutes et sont
consommés dès la première réponse, valide ou non.

Une passkey perdue ou compromise se révoque immédiatement :

```text
php artisan identite:revoquer-passkey AUT-GAMAD-001 PASS-REFERENCE
```

La révocation invalide aussi toutes les sessions ouvertes par cette passkey et
produit un événement de sécurité dans le journal opérationnel.

La configuration Nginx active sert déjà le bon front-controller Laravel et
redirige HTTP vers HTTPS. Avant tout rechargement :

```text
nginx -t
systemctl reload php8.3-fpm
systemctl reload nginx
curl --fail --silent https://console.dgafrique.com/api/v1/health/ready
```

Ces commandes ne sont pas exécutées automatiquement par le dépôt : le
déploiement reste une action explicite sur ce serveur.

## Sauvegarde sans secret en ligne de commande

Installer quatre services dans `pg_service.conf`, avec mots de passe dans un
fichier `.pgpass` protégé, puis exporter uniquement leurs noms :

```text
GAMAD_INDEX_PGSERVICE=gamad_index
GAMAD_ACCESS_PGSERVICE=gamad_access
GAMAD_IDENTITY_PGSERVICE=gamad_identity
GAMAD_JOURNAL_PGSERVICE=gamad_journal
GAMAD_BACKUP_DIR=/var/backups/gamad-core/daily
PGSERVICEFILE=/etc/gamad-core/pg_service.conf
PGPASSFILE=/etc/gamad-core/pgpass
```

Sur ce serveur local, `PGHOST`, `PGPORT` et `PGUSER` peuvent aussi venir de
l'environnement protégé, avec les noms de bases non secrets :

```text
GAMAD_INDEX_PGDATABASE=registre_normes
GAMAD_ACCESS_PGDATABASE=registre_acces
GAMAD_IDENTITY_PGDATABASE=registre_identites
GAMAD_JOURNAL_PGDATABASE=journal_operationnel
```

Exécuter :

```text
ops/core-foundation/backup.sh
```

Chaque lot contient quatre archives au format custom et un manifeste
`SHA256SUMS`.

## Import initial des magasins SQLite

Après création et migration de cibles PostgreSQL vides :

```text
php artisan core:fondation:importer-sqlite \
  --acces=/chemin/existant/magasin.sqlite \
  --identites=/chemin/existant/registre.sqlite \
  --force
```

L'import refuse une cible non vide, omet seulement les identifiants techniques
auto-incrémentés et contrôle la cardinalité de chaque table avant validation de
la transaction. Il ne supprime ni ne renomme les fichiers SQLite sources.

## Exercice de restauration

Ne jamais viser les bases de production. Préparer quatre bases isolées et
quatre services `pg_service.conf` distincts, puis définir :

```text
GAMAD_RESTORE_SOURCE=/srv/backups/gamad-core/AAAAMMJJTHHMMSSZ
GAMAD_RESTORE_INDEX_PGSERVICE=drill_index
GAMAD_RESTORE_ACCESS_PGSERVICE=drill_access
GAMAD_RESTORE_IDENTITY_PGSERVICE=drill_identity
GAMAD_RESTORE_JOURNAL_PGSERVICE=drill_journal
GAMAD_RESTORE_DRILL_CONFIRM=isolated-empty-databases
```

Les variantes `GAMAD_RESTORE_*_PGDATABASE` sont également acceptées avec les
variables PostgreSQL communes.

Lancer `ops/core-foundation/restore-drill.sh`. Le script vérifie les empreintes,
restaure en transaction et exécute une lecture minimale de chaque base. Le
résultat et la durée doivent ensuite être inscrits au registre opérationnel.

La CI exécute ce cycle complet sur huit bases PostgreSQL temporaires (quatre
sources et quatre cibles isolées) via
`apps/console-laravel/tests/Integration/postgresql_p0.sh`.

## Sondes et alertes initiales

- liveness : `GET /api/v1/health/live`;
- readiness : `GET /api/v1/health/ready`;
- alerte critique : trois réponses `503` consécutives sur la readiness;
- alerte sécurité : échec de `php artisan core:journal:verifier`;
- alerte sauvegarde : aucun lot complet depuis 24 heures;
- exercice de restauration : mensuel tant que le socle n'a pas atteint sa
  qualification globale.

Les unités proposées sous `ops/core-foundation/systemd/` écrivent les échecs
critiques dans le journal système avec la priorité `auth.alert`. Le routage de
ces alertes vers un opérateur externe reste à installer avant de prétendre à
une surveillance 24/7.

Sur le serveur actuel, les unités attendent le fichier non secret
`/etc/gamad-core/backup.env` :

```text
GAMAD_BACKUP_DIR=/var/backups/gamad-core/daily
GAMAD_INDEX_PGDATABASE=registre_normes
GAMAD_ACCESS_PGDATABASE=registre_acces
GAMAD_IDENTITY_PGDATABASE=registre_identites
GAMAD_JOURNAL_PGDATABASE=journal_operationnel
```

La sonde `gamad-core-readiness.timer` interroge la readiness publique toutes
les cinq minutes. Trois échecs consécutifs dans la même exécution déclenchent
`gamad-core-operations-alert@readiness.service`. La vérification du journal est
horaire et la sauvegarde quotidienne.

Les unités et l’environnement non secret sont installés depuis
`ops/core-foundation/systemd/`, puis activés avec `systemctl enable --now` pour
les trois timers. Toute modification d’une unité doit être suivie de
`systemctl daemon-reload` et d’une exécution manuelle de son service.

La spécification HTTP est dans
`apps/console-laravel/openapi/core-v1.yaml`.

Le déploiement initial de ce socle est constaté sans réécriture du registre
historique scellé dans
`genesis-ii/registres/ingenierie/ENTREE-DEPLOIEMENT-20260730-0001.md`.
