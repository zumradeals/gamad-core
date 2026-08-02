#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

: "${GAMAD_BACKUP_DIR:?GAMAD_BACKUP_DIR doit désigner un répertoire absolu dédié}"

case "$GAMAD_BACKUP_DIR" in
    /*) ;;
    *) echo "GAMAD_BACKUP_DIR doit être absolu." >&2; exit 2 ;;
esac

case "$GAMAD_BACKUP_DIR" in
    /|/root|/home|/var|/var/www|/tmp)
        echo "GAMAD_BACKUP_DIR est trop large ou non dédié." >&2
        exit 2
        ;;
esac

connexion() {
    local service="$1"
    local base="$2"
    if [[ -n "$service" ]]; then
        printf 'service=%s' "$service"
        return
    fi
    if [[ -z "$base" ]]; then
        echo "Un nom de service ou de base PostgreSQL est requis." >&2
        return 2
    fi
    printf 'dbname=%s' "$base"
}

declare -A connexions=(
    [index]="$(connexion "${GAMAD_INDEX_PGSERVICE:-}" "${GAMAD_INDEX_PGDATABASE:-}")"
    [acces]="$(connexion "${GAMAD_ACCESS_PGSERVICE:-}" "${GAMAD_ACCESS_PGDATABASE:-}")"
    [identites]="$(connexion "${GAMAD_IDENTITY_PGSERVICE:-}" "${GAMAD_IDENTITY_PGDATABASE:-}")"
    [produits]="$(connexion "${GAMAD_PRODUCTS_PGSERVICE:-}" "${GAMAD_PRODUCTS_PGDATABASE:-}")"
    [sources]="$(connexion "${GAMAD_SOURCES_PGSERVICE:-}" "${GAMAD_SOURCES_PGDATABASE:-}")"
    [politiques]="$(connexion "${GAMAD_POLICIES_PGSERVICE:-}" "${GAMAD_POLICIES_PGDATABASE:-}")"
    [journal]="$(connexion "${GAMAD_JOURNAL_PGSERVICE:-}" "${GAMAD_JOURNAL_PGDATABASE:-}")"
)

horodatage="$(date -u +%Y%m%dT%H%M%SZ)"
lot="${GAMAD_BACKUP_DIR%/}/${horodatage}"
mkdir -p "$lot"

for cible in index acces identites produits sources politiques journal; do
    destination="${lot}/${cible}.dump"
    pg_dump \
        --dbname="${connexions[$cible]}" \
        --format=custom \
        --compress=9 \
        --no-owner \
        --no-privileges \
        --file="$destination"
done

(
    cd "$lot"
    sha256sum index.dump acces.dump identites.dump produits.dump sources.dump politiques.dump journal.dump > SHA256SUMS
)

echo "Sauvegarde créée : $lot"
echo "Aucun secret de connexion n’a été passé en argument."
