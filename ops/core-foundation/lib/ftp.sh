#!/usr/bin/env bash
#
# Transport FTP de la copie hors machine, partagé par offsite.sh et
# offsite-drill.sh.
#
# LE FTP EST LE TRANSPORT LE PLUS FAIBLE SUPPORTÉ ICI. Il est fourni parce que
# certains espaces de sauvegarde n'offrent rien d'autre. Deux dangers demeurent,
# et aucun script ne peut les supprimer :
#
#   · en FTP nu, le mot de passe traverse le réseau en clair. Qui l'intercepte
#     ne peut pas LIRE les copies — elles sont chiffrées avant de partir — mais
#     il peut les EFFACER ;
#   · le serveur n'est pas authentifié : la copie peut être détournée.
#
# D'où le défaut `opportuniste` : le transport tente TLS à chaque connexion et
# ne retombe en clair que si le serveur le refuse. `exige` ferme le transport
# quand TLS est absent ; c'est le réglage à viser dès que l'hébergeur le permet.
#
# Le mot de passe n'est JAMAIS passé en argument : `ps` expose la ligne de
# commande de tous les processus à tous les utilisateurs de la machine. Il
# transite par un fichier de configuration curl, créé en 0600 dans un
# répertoire temporaire privé, et effacé à la sortie.

set -Eeuo pipefail

FTP_CONFIG=""

ftp_preparer() {
    : "${GAMAD_OFFSITE_FTP_USER:?GAMAD_OFFSITE_FTP_USER est requis pour une destination FTP}"
    : "${GAMAD_OFFSITE_FTP_SECRET_FILE:?GAMAD_OFFSITE_FTP_SECRET_FILE est requis pour une destination FTP}"

    if [[ ! -r "$GAMAD_OFFSITE_FTP_SECRET_FILE" ]]; then
        echo "Refus : mot de passe FTP illisible (${GAMAD_OFFSITE_FTP_SECRET_FILE})." >&2
        exit 2
    fi

    local secret
    secret="$(< "$GAMAD_OFFSITE_FTP_SECRET_FILE")"
    secret="${secret%$'\n'}"
    if [[ -z "$secret" ]]; then
        echo "Refus : mot de passe FTP vide." >&2
        exit 2
    fi

    local abri
    abri="$(mktemp -d)"
    chmod 700 "$abri"
    FTP_CONFIG="${abri}/curl.conf"
    umask 077
    {
        printf 'user = "%s:%s"\n' "$GAMAD_OFFSITE_FTP_USER" "$secret"
        printf 'silent\n'
        printf 'show-error\n'
        printf 'fail\n'
        printf 'connect-timeout = 20\n'
        printf 'max-time = 900\n'
        case "${GAMAD_OFFSITE_FTP_TLS:-opportuniste}" in
            exige) printf 'ssl-reqd\n' ;;
            aucun) ;;
            opportuniste|'') printf 'ssl\n' ;;
            *)
                echo "GAMAD_OFFSITE_FTP_TLS doit valoir exige, opportuniste ou aucun." >&2
                exit 2
                ;;
        esac
    } > "$FTP_CONFIG"

    # Le fichier disparaît quoi qu'il arrive, y compris sur interruption.
    gamad_a_nettoyer "$abri"
}

ftp_curl() {
    curl --config "$FTP_CONFIG" "$@"
}

ftp_base() {
    local destination="$1"
    printf '%s/' "${destination%/}"
}

# Liste les archives présentes à destination, une par ligne, triées.
ftp_lister() {
    local base
    base="$(ftp_base "$1")"
    ftp_curl --list-only "$base" 2>/dev/null \
        | tr -d '\r' \
        | grep -E '\.tar\.gz\.gpg$' \
        | sort || true
}

ftp_deposer() {
    local fichier="$1"
    local base
    base="$(ftp_base "$2")"
    ftp_curl --ftp-create-dirs --upload-file "$fichier" "${base}$(basename "$fichier")"
}

ftp_recuperer() {
    local nom="$1"
    local base
    base="$(ftp_base "$2")"
    local vers="$3"
    ftp_curl --output "$vers" "${base}${nom}"
}

# Le chemin distant, extrait de l'URL : `ftp://hote:port/copies` donne `/copies`.
ftp_chemin() {
    local sans_schema="${1#*://}"
    local chemin="/${sans_schema#*/}"
    [[ "$chemin" == "/${sans_schema}" ]] && chemin=""
    printf '%s' "${chemin%/}"
}

# curl envoie les commandes `--quote` AVANT le changement de répertoire : un
# `DELE nom` relatif s'exécuterait donc à la racine du compte FTP et le serveur
# répondrait 550. Le préfixe `+`, censé placer la commande après le CWD, est
# ignoré par curl 8.5. Le chemin absolu est la seule forme qui ne dépende ni de
# l'ordre interne de curl ni du répertoire courant du compte.
ftp_supprimer() {
    local nom="$1"
    local base
    base="$(ftp_base "$2")"
    local chemin
    chemin="$(ftp_chemin "$2")"
    ftp_curl --quote "DELE ${chemin}/${nom}" "$base" --output /dev/null
}

# Aligne la destination sur le miroir local : dépose ce qui manque, retire ce
# qui a quitté le miroir. La rétention a déjà été appliquée localement, où elle
# est inspectable ; ici on ne fait que la propager.
ftp_deposer_miroir() {
    local stage="$1"
    local destination="$2"
    local retention="$3"

    local -a locales=()
    local chemin
    while IFS= read -r chemin; do
        locales+=("$(basename "$chemin")")
    done < <(find "$stage" -mindepth 1 -maxdepth 1 -type f -name '*.tar.gz.gpg' | sort)

    local nom
    for nom in "${locales[@]}"; do
        ftp_deposer "${stage%/}/${nom}" "$destination"
        ftp_deposer "${stage%/}/${nom}.sha256" "$destination"
        echo "Déposé : ${nom}"
    done

    local distante
    while IFS= read -r distante; do
        [[ -z "$distante" ]] && continue
        local presente=0
        for nom in "${locales[@]}"; do
            [[ "$nom" == "$distante" ]] && presente=1 && break
        done
        if (( presente == 0 )); then
            ftp_supprimer "$distante" "$destination"
            ftp_supprimer "${distante}.sha256" "$destination" || true
            echo "Retiré de la destination : ${distante}"
        fi
    done < <(ftp_lister "$destination")
}
