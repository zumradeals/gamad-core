#!/usr/bin/env bash
#
# Point d'entrée unique de la continuité, pilotable depuis la console.
#
# La console tourne en `www-data`, la sauvegarde en `postgres`. La console ne
# reçoit AUCUN droit d'exécuter des commandes système : elle dépose un
# fichier-signal dans un répertoire partagé, et une unité systemd surveille ce
# répertoire. C'est le seul chemin, et il ne franchit aucune frontière de
# privilège.
#
# Modes :
#   etat             recalcule l'état et l'écrit dans etat.json
#   sauvegarder      sauvegarde + copie hors machine, puis état
#   eprouver         exercice de restauration depuis la copie, puis état
#   servir-demande   exécute la demande déposée par la console, puis l'efface
#
# Variables :
#   GAMAD_CONTINUITE_DIR  répertoire partagé (défaut /var/lib/gamad-core/continuite)
#   + celles de backup.sh et offsite.sh

set -Eeuo pipefail
umask 007

racine="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
partage="${GAMAD_CONTINUITE_DIR:-/var/lib/gamad-core/continuite}"
demandes="${partage}/demandes"
etat="${partage}/etat.json"

mkdir -p "$demandes"

# Les réglages écrits par la console. Absents, la continuité reste locale.
if [[ -r "${partage}/offsite.env" ]]; then
    set -a
    # shellcheck source=/dev/null
    . "${partage}/offsite.env"
    set +a
fi

json_texte() {
    local valeur="${1-}"
    if [[ -z "$valeur" ]]; then
        printf 'null'
        return
    fi
    valeur="${valeur//\\/\\\\}"
    valeur="${valeur//\"/\\\"}"
    printf '"%s"' "$valeur"
}

# La destination est restituée sans ses identifiants : l'état est lisible par
# la console, qui n'a pas à réafficher un mot de passe.
destination_publique() {
    local destination="${GAMAD_OFFSITE_DEST:-}"
    [[ -z "$destination" ]] && return 0
    printf '%s' "${destination%%\?*}"
}

dernier_dossier() {
    find "${1%/}" -mindepth 1 -maxdepth 1 -type d \
        -regextype posix-extended -regex '.*/[0-9]{8}T[0-9]{6}Z$' 2>/dev/null \
        | sort | tail -1
}

ecrire_etat() {
    local action="${1:-}" resultat="${2:-}" detail="${3:-}"

    local lots_dir="${GAMAD_BACKUP_DIR:-}"
    local dernier_lot="" nombre_lots=0
    if [[ -n "$lots_dir" && -d "$lots_dir" ]]; then
        dernier_lot="$(dernier_dossier "$lots_dir")"
        nombre_lots="$(find "${lots_dir%/}" -mindepth 1 -maxdepth 1 -type d 2>/dev/null | wc -l)"
    fi

    local stage="${GAMAD_OFFSITE_STAGE:-}"
    if [[ -z "$stage" && -n "$lots_dir" ]]; then
        stage="$(dirname "${lots_dir%/}")/hors-machine"
    fi
    local derniere_archive="" nombre_copies=0
    if [[ -d "$stage" ]]; then
        derniere_archive="$(find "$stage" -maxdepth 1 -type f -name '*.tar.gz.gpg' 2>/dev/null | sort | tail -1)"
        nombre_copies="$(find "$stage" -maxdepth 1 -type f -name '*.tar.gz.gpg' 2>/dev/null | wc -l)"
    fi

    # Les opérations précédentes sont conservées telles quelles si la présente
    # exécution ne les concerne pas : un état ne doit jamais effacer une trace.
    local precedent_operation='null' precedent_exercice='null'
    if [[ -r "$etat" ]]; then
        precedent_operation="$(sed -n 's/.*"derniere_operation":[[:space:]]*\({[^}]*}\|null\).*/\1/p' "$etat" | head -1)"
        precedent_exercice="$(sed -n 's/.*"dernier_exercice":[[:space:]]*\({[^}]*}\|null\).*/\1/p' "$etat" | head -1)"
        [[ -z "$precedent_operation" ]] && precedent_operation='null'
        [[ -z "$precedent_exercice" ]] && precedent_exercice='null'
    fi

    local operation="$precedent_operation" exercice="$precedent_exercice"
    if [[ -n "$action" ]]; then
        local bloc
        bloc="{\"action\": $(json_texte "$action"), \"resultat\": $(json_texte "$resultat"), \"le\": $(json_texte "$(date -u +%Y-%m-%dT%H:%M:%SZ)"), \"detail\": $(json_texte "$detail")}"
        operation="$bloc"
        [[ "$action" == "exercice" ]] && exercice="$bloc"
    fi

    local chiffrement='aucun'
    [[ -n "${GAMAD_OFFSITE_RECIPIENT:-}" ]] && chiffrement='clé publique GPG'
    [[ -n "${GAMAD_OFFSITE_PASSPHRASE_FILE:-}" ]] && chiffrement='phrase secrète AES256'

    local temporaire="${etat}.nouveau"
    cat > "$temporaire" <<JSON
{
  "genere_le": $(json_texte "$(date -u +%Y-%m-%dT%H:%M:%SZ)"),
  "destination_configuree": $([[ -n "${GAMAD_OFFSITE_DEST:-}" ]] && echo true || echo false),
  "destination": $(json_texte "$(destination_publique)"),
  "transport": $(json_texte "$(case "${GAMAD_OFFSITE_DEST:-}" in ftp://*) echo FTP ;; ftps://*) echo FTPS ;; *:*) echo "rsync sur SSH" ;; ?*) echo "volume local" ;; *) echo "" ;; esac)"),
  "chiffrement": $(json_texte "$chiffrement"),
  "retention": ${GAMAD_OFFSITE_RETENTION:-14},
  "lots_locaux": ${nombre_lots:-0},
  "dernier_lot_local": $(json_texte "$([[ -n "$dernier_lot" ]] && basename "$dernier_lot")"),
  "copies_hors_machine": ${nombre_copies:-0},
  "derniere_copie": $(json_texte "$([[ -n "$derniere_archive" ]] && basename "$derniere_archive")"),
  "derniere_operation": ${operation},
  "dernier_exercice": ${exercice}
}
JSON
    mv -f "$temporaire" "$etat"
    chmod 0660 "$etat" 2>/dev/null || true
}

executer() {
    local action="$1"
    local journal="${partage}/derniere-sortie.txt"
    local code=0

    case "$action" in
        sauvegarde)
            { "${racine}/backup.sh" && "${racine}/offsite.sh"; } > "$journal" 2>&1 || code=$?
            ;;
        exercice)
            "${racine}/offsite-drill.sh" > "$journal" 2>&1 || code=$?
            ;;
        *)
            echo "Action inconnue : ${action}" >&2
            return 2
            ;;
    esac

    chmod 0660 "$journal" 2>/dev/null || true
    local detail
    detail="$(tail -3 "$journal" | tr '\n' ' ' | cut -c1-400)"

    if (( code == 0 )); then
        ecrire_etat "$action" "succes" "$detail"
        echo "Opération ${action} : réussie."
    else
        ecrire_etat "$action" "echec" "$detail"
        echo "Opération ${action} : ÉCHEC (code ${code})." >&2
    fi

    return "$code"
}

case "${1:-etat}" in
    etat)
        ecrire_etat
        echo "État de la continuité écrit dans ${etat}."
        ;;
    sauvegarder)
        executer sauvegarde
        ;;
    eprouver)
        executer exercice
        ;;
    servir-demande)
        servi=0
        dernier=0
        for demande in sauvegarde exercice; do
            fichier="${demandes}/${demande}.demande"
            if [[ -e "$fichier" ]]; then
                # Le signal est retiré AVANT l'exécution : une opération qui
                # échoue ne doit pas se rejouer en boucle.
                rm -f -- "$fichier"
                servi=1
                executer "$demande" || dernier=$?
            fi
        done
        if (( servi == 0 )); then
            ecrire_etat
            echo "Aucune demande en attente."
        fi
        # L'échec est propagé pour que l'alerte systemd se déclenche. L'état a
        # déjà été écrit : la console saura dire ce qui s'est passé.
        exit "$dernier"
        ;;
    *)
        echo "Usage : continuite.sh [etat|sauvegarder|eprouver|servir-demande]" >&2
        exit 2
        ;;
esac
