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
            epingle)
                # Le certificat est chiffré et authentifié — non par un nom
                # d'hôte, mais par la clé publique relevée au raccordement.
                printf 'ssl-reqd\ninsecure\npinnedpubkey = "sha256//%s"\n' "$(ftp_epreinte)"
                ;;
            opportuniste|'') printf 'ssl\n' ;;
            *)
                echo "GAMAD_OFFSITE_FTP_TLS doit valoir exige, epingle, opportuniste ou aucun." >&2
                exit 2
                ;;
        esac
    } > "$FTP_CONFIG"

    # Le fichier disparaît quoi qu'il arrive, y compris sur interruption.
    gamad_a_nettoyer "$abri"
}

# Empreinte de la clé publique du serveur.
#
# Beaucoup d'hébergements mutualisés présentent un certificat parfaitement
# valide, mais émis pour un autre nom que celui du serveur FTP. La vérification
# par nom d'hôte échoue alors, et la tentation est de la désactiver — ce qui
# rendrait le transport vulnérable à une interception.
#
# L'épinglage garde le chiffrement ET l'authentification : le serveur est
# reconnu à sa clé, relevée une fois au raccordement. Le risque résiduel est
# celui du premier contact : si cette toute première connexion était déjà
# interceptée, c'est la clé de l'intercepteur qui serait retenue. Le jour où le
# serveur change légitimement de certificat, le transport s'arrête et l'écran
# demande de relever l'empreinte à nouveau — un arrêt bruyant valant mieux
# qu'une confiance silencieuse.
ftp_epreinte() {
    local fichier="${GAMAD_OFFSITE_PIN_FILE:-}"
    if [[ -n "$fichier" && -s "$fichier" ]]; then
        tr -d '\r\n' < "$fichier"
        return
    fi

    local hote port
    hote="$(ftp_hote "$GAMAD_OFFSITE_DEST")"
    port="$(ftp_port "$GAMAD_OFFSITE_DEST")"
    local empreinte
    empreinte="$(openssl s_client -connect "${hote}:${port}" -starttls ftp -servername "$hote" \
        </dev/null 2>/dev/null \
        | openssl x509 -pubkey -noout 2>/dev/null \
        | openssl pkey -pubin -outform der 2>/dev/null \
        | openssl dgst -sha256 -binary 2>/dev/null \
        | base64)"

    if [[ -z "$empreinte" ]]; then
        echo "Refus : impossible de relever l'empreinte TLS de ${hote}:${port}." >&2
        exit 2
    fi
    if [[ -n "$fichier" ]]; then
        printf '%s\n' "$empreinte" > "$fichier"
        chmod 0660 "$fichier" 2>/dev/null || true
        echo "Empreinte TLS relevée et retenue pour ${hote} : ${empreinte}" >&2
    fi
    printf '%s' "$empreinte"
}

ftp_hote() {
    local sans_schema="${1#*://}"
    local hote="${sans_schema%%/*}"
    printf '%s' "${hote%%:*}"
}

ftp_port() {
    local sans_schema="${1#*://}"
    local hote="${sans_schema%%/*}"
    if [[ "$hote" == *:* ]]; then
        printf '%s' "${hote##*:}"
    else
        printf '21'
    fi
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
