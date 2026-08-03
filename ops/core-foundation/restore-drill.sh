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
    [produits]="$(connexion "${GAMAD_RESTORE_PRODUCTS_PGSERVICE:-}" "${GAMAD_RESTORE_PRODUCTS_PGDATABASE:-}")"
    [sources]="$(connexion "${GAMAD_RESTORE_SOURCES_PGSERVICE:-}" "${GAMAD_RESTORE_SOURCES_PGDATABASE:-}")"
    [politiques]="$(connexion "${GAMAD_RESTORE_POLICIES_PGSERVICE:-}" "${GAMAD_RESTORE_POLICIES_PGDATABASE:-}")"
    [contrats]="$(connexion "${GAMAD_RESTORE_CONTRACTS_PGSERVICE:-}" "${GAMAD_RESTORE_CONTRACTS_PGDATABASE:-}")"
    [vocabulaire]="$(connexion "${GAMAD_RESTORE_VOCABULARY_PGSERVICE:-}" "${GAMAD_RESTORE_VOCABULARY_PGDATABASE:-}")"
    [organisations]="$(connexion "${GAMAD_RESTORE_ORGANIZATIONS_PGSERVICE:-}" "${GAMAD_RESTORE_ORGANIZATIONS_PGDATABASE:-}")"
    [journal]="$(connexion "${GAMAD_RESTORE_JOURNAL_PGSERVICE:-}" "${GAMAD_RESTORE_JOURNAL_PGDATABASE:-}")"
)

# `pg_restore --clean` détruit ce qu'il trouve. La confirmation annonce des
# bases isolées et vides, mais rien ne le vérifiait : une variable mal recopiée
# suffisait à viser la production, et le script l'aurait fait sans broncher.
#
# Ce contrôle refuse toute cible qui porte le nom d'une base d'exploitation.
# Il ne remplace pas la vigilance ; il rend l'accident impossible par simple
# faute de frappe.
declare -a production=(
    "${GAMAD_INDEX_PGDATABASE:-}"
    "${GAMAD_ACCESS_PGDATABASE:-}"
    "${GAMAD_IDENTITY_PGDATABASE:-}"
    "${GAMAD_PRODUCTS_PGDATABASE:-}"
    "${GAMAD_SOURCES_PGDATABASE:-}"
    "${GAMAD_POLICIES_PGDATABASE:-}"
    "${GAMAD_CONTRACTS_PGDATABASE:-}"
    "${GAMAD_VOCABULARY_PGDATABASE:-}"
    "${GAMAD_ORGANIZATIONS_PGDATABASE:-}"
    "${GAMAD_JOURNAL_PGDATABASE:-}"
    "${GAMAD_INDEX_PGSERVICE:-}"
    "${GAMAD_ACCESS_PGSERVICE:-}"
    "${GAMAD_IDENTITY_PGSERVICE:-}"
    "${GAMAD_PRODUCTS_PGSERVICE:-}"
    "${GAMAD_SOURCES_PGSERVICE:-}"
    "${GAMAD_POLICIES_PGSERVICE:-}"
    "${GAMAD_CONTRACTS_PGSERVICE:-}"
    "${GAMAD_VOCABULARY_PGSERVICE:-}"
    "${GAMAD_ORGANIZATIONS_PGSERVICE:-}"
    "${GAMAD_JOURNAL_PGSERVICE:-}"
)
for cible in index acces identites produits sources politiques contrats vocabulaire organisations journal; do
    for interdite in "${production[@]}"; do
        [[ -z "$interdite" ]] && continue
        if [[ "${connexions[$cible]}" == *"=${interdite}" ]]; then
            echo "Refus : la cible ${cible} désigne « ${interdite} », qui est une base" >&2
            echo "d'exploitation. Un exercice ne se restaure jamais sur la production." >&2
            exit 2
        fi
    done
done

(
    cd "$GAMAD_RESTORE_SOURCE"
    sha256sum --check SHA256SUMS
)

for cible in index acces identites produits sources politiques contrats vocabulaire organisations journal; do
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
psql "${connexions[produits]}" --no-psqlrc --set=ON_ERROR_STOP=1 \
    --command='SELECT count(*) AS produits_persistants FROM produit'
psql "${connexions[sources]}" --no-psqlrc --set=ON_ERROR_STOP=1 \
    --command='SELECT count(*) AS sources_persistantes FROM source'
psql "${connexions[politiques]}" --no-psqlrc --set=ON_ERROR_STOP=1 \
    --command='SELECT count(*) AS politiques_persistantes FROM politique'
psql "${connexions[contrats]}" --no-psqlrc --set=ON_ERROR_STOP=1 \
    --command='SELECT count(*) AS contrats_persistants FROM contrat'
psql "${connexions[vocabulaire]}" --no-psqlrc --set=ON_ERROR_STOP=1 \
    --command='SELECT count(*) AS termes_persistants FROM terme'
psql "${connexions[organisations]}" --no-psqlrc --set=ON_ERROR_STOP=1 \
    --command='SELECT count(*) AS organisations_persistantes FROM organisation'
psql "${connexions[journal]}" --no-psqlrc --set=ON_ERROR_STOP=1 \
    --command='SELECT count(*) AS evenements FROM evenement_operationnel'

echo "Exercice de restauration terminé sur les dix cibles isolées."
