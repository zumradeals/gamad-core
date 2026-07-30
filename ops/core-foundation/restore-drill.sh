#!/usr/bin/env bash

set -Eeuo pipefail

: "${GAMAD_RESTORE_SOURCE:?GAMAD_RESTORE_SOURCE doit désigner un lot de sauvegarde}"
: "${GAMAD_RESTORE_DRILL_CONFIRM:?Confirmation absente}"

if [[ "$GAMAD_RESTORE_DRILL_CONFIRM" != "isolated-empty-databases" ]]; then
    echo "Refus : GAMAD_RESTORE_DRILL_CONFIRM doit valoir isolated-empty-databases." >&2
    exit 2
fi

case "$GAMAD_RESTORE_SOURCE" in
    /*) ;;
    *) echo "GAMAD_RESTORE_SOURCE doit être absolu." >&2; exit 2 ;;
esac

connexion() {
    local service="$1"
    local base="$2"
    if [[ -n "$service" ]]; then
        printf 'service=%s' "$service"
        return
    fi
    if [[ -z "$base" ]]; then
        echo "Un nom de service ou de base de restauration est requis." >&2
        return 2
    fi
    printf 'dbname=%s' "$base"
}

declare -A connexions=(
    [index]="$(connexion "${GAMAD_RESTORE_INDEX_PGSERVICE:-}" "${GAMAD_RESTORE_INDEX_PGDATABASE:-}")"
    [acces]="$(connexion "${GAMAD_RESTORE_ACCESS_PGSERVICE:-}" "${GAMAD_RESTORE_ACCESS_PGDATABASE:-}")"
    [identites]="$(connexion "${GAMAD_RESTORE_IDENTITY_PGSERVICE:-}" "${GAMAD_RESTORE_IDENTITY_PGDATABASE:-}")"
    [journal]="$(connexion "${GAMAD_RESTORE_JOURNAL_PGSERVICE:-}" "${GAMAD_RESTORE_JOURNAL_PGDATABASE:-}")"
)

(
    cd "$GAMAD_RESTORE_SOURCE"
    sha256sum --check SHA256SUMS
)

for cible in index acces identites journal; do
    pg_restore \
        --dbname="${connexions[$cible]}" \
        --clean \
        --if-exists \
        --exit-on-error \
        --single-transaction \
        --no-owner \
        --no-privileges \
        "${GAMAD_RESTORE_SOURCE%/}/${cible}.dump"
done

psql "${connexions[index]}" --no-psqlrc --set=ON_ERROR_STOP=1 \
    --command='SELECT count(*) AS identites_derivees FROM entite'
psql "${connexions[acces]}" --no-psqlrc --set=ON_ERROR_STOP=1 \
    --command='SELECT count(*) AS authentificateurs FROM authentificateur'
psql "${connexions[identites]}" --no-psqlrc --set=ON_ERROR_STOP=1 \
    --command='SELECT count(*) AS identites_persistantes FROM identite_inscrite'
psql "${connexions[journal]}" --no-psqlrc --set=ON_ERROR_STOP=1 \
    --command='SELECT count(*) AS evenements FROM evenement_operationnel'

echo "Exercice de restauration terminé sur les quatre cibles isolées."
