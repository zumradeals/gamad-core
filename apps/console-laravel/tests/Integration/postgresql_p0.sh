#!/usr/bin/env bash

set -Eeuo pipefail

racine="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../.." && pwd)"
application="$racine/apps/console-laravel"
temp="$(mktemp -d /tmp/gamad-postgresql-p0.XXXXXX)"
port="$((55000 + RANDOM % 5000))"
postgres_bin="/usr/lib/postgresql/16/bin"

nettoyer() {
    "$postgres_bin/pg_ctl" -D "$temp/data" -m fast -w stop >/dev/null 2>&1 || true
    rm -rf -- "$temp"
}
trap nettoyer EXIT

"$postgres_bin/initdb" \
    --pgdata="$temp/data" \
    --username=postgres \
    --auth-local=trust \
    --auth-host=trust \
    --no-locale \
    --encoding=UTF8 >/dev/null

"$postgres_bin/pg_ctl" \
    --pgdata="$temp/data" \
    --options="-F -p $port -h 127.0.0.1 -k $temp" \
    --wait \
    start >/dev/null

for base in gamad_index gamad_access gamad_identity gamad_products gamad_sources gamad_policies gamad_contracts gamad_vocabulary gamad_organizations gamad_realms gamad_journal gamad_events gamad_secrets gamad_preuves; do
    "$postgres_bin/createdb" --host=127.0.0.1 --port="$port" --username=postgres "$base"
done

cache_prefix="$temp/laravel"
environnement=(
    "APP_ENV=production"
    "APP_DEBUG=false"
    "APP_KEY=base64:eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHg="
    "APP_URL=https://core.test"
    "APP_CONFIG_CACHE=${cache_prefix}-config.php"
    "APP_EVENTS_CACHE=${cache_prefix}-events.php"
    "APP_PACKAGES_CACHE=${cache_prefix}-packages.php"
    "APP_ROUTES_CACHE=${cache_prefix}-routes.php"
    "APP_SERVICES_CACHE=${cache_prefix}-services.php"
    "CACHE_STORE=array"
    "LOG_CHANNEL=errorlog"
    "DB_CONNECTION=gamad_access"
    "SESSION_DRIVER=database"
    "SESSION_CONNECTION=gamad_access"
    "SESSION_ENCRYPT=true"
    "SESSION_SECURE_COOKIE=true"
    "DATABASE_URL=postgresql://postgres@127.0.0.1:${port}/gamad_index"
    "MAGASIN_URL=postgresql://postgres@127.0.0.1:${port}/gamad_access"
    "IDENTITY_REGISTRY_URL=postgresql://postgres@127.0.0.1:${port}/gamad_identity"
    "PRODUCT_REGISTRY_URL=postgresql://postgres@127.0.0.1:${port}/gamad_products"
    "SOURCE_REGISTRY_URL=postgresql://postgres@127.0.0.1:${port}/gamad_sources"
    "POLICY_REGISTRY_URL=postgresql://postgres@127.0.0.1:${port}/gamad_policies"
    "CONTRACT_REGISTRY_URL=postgresql://postgres@127.0.0.1:${port}/gamad_contracts"
    "VOCABULARY_REGISTRY_URL=postgresql://postgres@127.0.0.1:${port}/gamad_vocabulary"
    "ORGANIZATION_REGISTRY_URL=postgresql://postgres@127.0.0.1:${port}/gamad_organizations"
    "REALM_REGISTRY_URL=postgresql://postgres@127.0.0.1:${port}/gamad_realms"
    "JOURNAL_OPERATIONNEL_URL=postgresql://postgres@127.0.0.1:${port}/gamad_journal"
    "EVENT_JOURNAL_URL=postgresql://postgres@127.0.0.1:${port}/gamad_events"
    "SECRET_REGISTRY_URL=postgresql://postgres@127.0.0.1:${port}/gamad_secrets"
    "PROOF_REGISTRY_URL=postgresql://postgres@127.0.0.1:${port}/gamad_preuves"
)

(
    cd "$application"
    env "${environnement[@]}" php artisan migrate --force --no-interaction
    env "${environnement[@]}" php artisan core:fondation:migrer --force
)

env "${environnement[@]}" php "$application/tests/Integration/postgresql_p0.php"

for base in drill_index drill_access drill_identity drill_products drill_sources drill_policies drill_contracts drill_vocabulary drill_organizations drill_realms drill_journal drill_events drill_secrets drill_preuves; do
    "$postgres_bin/createdb" --host=127.0.0.1 --port="$port" --username=postgres "$base"
done

export PGHOST=127.0.0.1
export PGPORT="$port"
export PGUSER=postgres
export GAMAD_BACKUP_DIR="$temp/backups"
export GAMAD_INDEX_PGDATABASE=gamad_index
export GAMAD_ACCESS_PGDATABASE=gamad_access
export GAMAD_IDENTITY_PGDATABASE=gamad_identity
export GAMAD_PRODUCTS_PGDATABASE=gamad_products
export GAMAD_SOURCES_PGDATABASE=gamad_sources
export GAMAD_POLICIES_PGDATABASE=gamad_policies
export GAMAD_CONTRACTS_PGDATABASE=gamad_contracts
export GAMAD_VOCABULARY_PGDATABASE=gamad_vocabulary
export GAMAD_ORGANIZATIONS_PGDATABASE=gamad_organizations
export GAMAD_REALMS_PGDATABASE=gamad_realms
export GAMAD_JOURNAL_PGDATABASE=gamad_journal
export GAMAD_EVENEMENTS_PGDATABASE=gamad_events
export GAMAD_SECRETS_PGDATABASE=gamad_secrets
export GAMAD_PREUVES_PGDATABASE=gamad_preuves

"$racine/ops/core-foundation/backup.sh"
lot="$(find "$GAMAD_BACKUP_DIR" -mindepth 1 -maxdepth 1 -type d -print -quit)"

export GAMAD_RESTORE_SOURCE="$lot"
export GAMAD_RESTORE_INDEX_PGDATABASE=drill_index
export GAMAD_RESTORE_ACCESS_PGDATABASE=drill_access
export GAMAD_RESTORE_IDENTITY_PGDATABASE=drill_identity
export GAMAD_RESTORE_PRODUCTS_PGDATABASE=drill_products
export GAMAD_RESTORE_SOURCES_PGDATABASE=drill_sources
export GAMAD_RESTORE_POLICIES_PGDATABASE=drill_policies
export GAMAD_RESTORE_CONTRACTS_PGDATABASE=drill_contracts
export GAMAD_RESTORE_VOCABULARY_PGDATABASE=drill_vocabulary
export GAMAD_RESTORE_ORGANIZATIONS_PGDATABASE=drill_organizations
export GAMAD_RESTORE_REALMS_PGDATABASE=drill_realms
export GAMAD_RESTORE_JOURNAL_PGDATABASE=drill_journal
export GAMAD_RESTORE_EVENEMENTS_PGDATABASE=drill_events
export GAMAD_RESTORE_SECRETS_PGDATABASE=drill_secrets
export GAMAD_RESTORE_PREUVES_PGDATABASE=drill_preuves
export GAMAD_RESTORE_DRILL_CONFIRM=isolated-empty-databases

"$racine/ops/core-foundation/restore-drill.sh"
echo "Sauvegarde et restauration PostgreSQL P0 : ÉTABLIES."
