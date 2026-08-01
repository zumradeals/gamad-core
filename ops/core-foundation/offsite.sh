#!/usr/bin/env bash
#
# Copie hors machine d'un lot de sauvegarde GAMAD Core.
#
# Une sauvegarde qui vit sur le disque qu'elle protège ne protège de rien : la
# panne qui emporte les bases emporte les copies. Ce script transporte un lot
# vers une destination distincte, après l'avoir vérifié et chiffré.
#
# Il est DÉSACTIVÉ tant que GAMAD_OFFSITE_DEST est vide : il peut donc être
# raccordé à la sauvegarde quotidienne sans que rien ne quitte la machine.
#
# Variables :
#   GAMAD_BACKUP_DIR             répertoire des lots locaux (obligatoire)
#   GAMAD_OFFSITE_DEST           destination ; VIDE = désactivé
#                                rsync : /mnt/copie  ou  sauvegarde@hote:/chemin
#                                FTP   : ftp://hote/chemin  ou  ftps://hote/chemin
#   GAMAD_OFFSITE_FTP_USER       utilisateur FTP
#   GAMAD_OFFSITE_FTP_SECRET_FILE fichier contenant le mot de passe FTP
#   GAMAD_OFFSITE_FTP_TLS        exige | opportuniste (défaut) | aucun
#   GAMAD_OFFSITE_RECIPIENT      destinataire GPG (chiffrement asymétrique)
#   GAMAD_OFFSITE_PASSPHRASE_FILE fichier de phrase secrète (symétrique)
#   GAMAD_OFFSITE_RETENTION      lots conservés à distance (défaut 14)
#   GAMAD_OFFSITE_SSH_KEY        clé SSH dédiée, si destination distante
#   GAMAD_OFFSITE_STAGE          miroir local, défaut <backup>/../hors-machine
#
# Le chiffrement n'est pas optionnel : un dump non chiffré qui quitte la
# machine emporte avec lui les empreintes de sessions et le registre des
# identités. Sans destinataire ni phrase secrète, le script refuse.

set -Eeuo pipefail
umask 077

: "${GAMAD_BACKUP_DIR:?GAMAD_BACKUP_DIR doit désigner le répertoire des lots}"

destination="${GAMAD_OFFSITE_DEST:-}"
if [[ -z "$destination" ]]; then
    echo "Copie hors machine désactivée : GAMAD_OFFSITE_DEST n'est pas défini."
    echo "Aucune donnée n'a quitté la machine."
    exit 0
fi

case "$GAMAD_BACKUP_DIR" in
    /*) ;;
    *) echo "GAMAD_BACKUP_DIR doit être absolu." >&2; exit 2 ;;
esac

recipient="${GAMAD_OFFSITE_RECIPIENT:-}"
passphrase="${GAMAD_OFFSITE_PASSPHRASE_FILE:-}"
if [[ -z "$recipient" && -z "$passphrase" ]]; then
    echo "Refus : aucun chiffrement configuré." >&2
    echo "Définir GAMAD_OFFSITE_RECIPIENT ou GAMAD_OFFSITE_PASSPHRASE_FILE." >&2
    exit 2
fi
if [[ -n "$passphrase" && ! -r "$passphrase" ]]; then
    echo "Refus : phrase secrète illisible ($passphrase)." >&2
    exit 2
fi

retention="${GAMAD_OFFSITE_RETENTION:-14}"
if ! [[ "$retention" =~ ^[0-9]+$ ]] || (( retention < 1 )); then
    echo "GAMAD_OFFSITE_RETENTION doit être un entier positif." >&2
    exit 2
fi

# Un lot explicite, sinon le plus récent.
lot="${1:-}"
if [[ -z "$lot" ]]; then
    lot="$(find "${GAMAD_BACKUP_DIR%/}" -mindepth 1 -maxdepth 1 -type d \
        -regextype posix-extended -regex '.*/[0-9]{8}T[0-9]{6}Z$' \
        | sort | tail -1)"
fi
if [[ -z "$lot" || ! -d "$lot" ]]; then
    echo "Aucun lot de sauvegarde à transporter dans $GAMAD_BACKUP_DIR." >&2
    exit 1
fi
horodatage="$(basename "$lot")"

# Un lot corrompu ne part pas : le transporter donnerait une copie inutile en
# donnant l'impression d'une continuité assurée.
if [[ ! -r "$lot/SHA256SUMS" ]]; then
    echo "Refus : le lot $horodatage ne porte pas de SHA256SUMS." >&2
    exit 1
fi
if ! ( cd "$lot" && sha256sum --quiet --check SHA256SUMS ); then
    echo "Refus : le lot $horodatage ne correspond pas à ses empreintes." >&2
    exit 1
fi

stage="${GAMAD_OFFSITE_STAGE:-${GAMAD_BACKUP_DIR%/}/../hors-machine}"
mkdir -p "$stage"
temporaire="$(mktemp -d)"
trap 'rm -rf -- "$temporaire"' EXIT

archive="${temporaire}/${horodatage}.tar.gz"
tar --create --gzip --file "$archive" --directory "$(dirname "$lot")" "$horodatage"

chiffre="${stage%/}/${horodatage}.tar.gz.gpg"
if [[ -n "$recipient" ]]; then
    gpg --batch --yes --trust-model always \
        --encrypt --recipient "$recipient" \
        --output "$chiffre" "$archive"
else
    gpg --batch --yes --pinentry-mode loopback \
        --symmetric --cipher-algo AES256 \
        --passphrase-file "$passphrase" \
        --output "$chiffre" "$archive"
fi

( cd "$stage" && sha256sum "$(basename "$chiffre")" > "$(basename "$chiffre").sha256" )

# La rétention s'applique d'abord au miroir local, où elle est inspectable,
# puis se propage par --delete. Aucune suppression n'est commandée à distance.
mapfile -t archives < <(find "$stage" -mindepth 1 -maxdepth 1 -type f -name '*.tar.gz.gpg' | sort)
if (( ${#archives[@]} > retention )); then
    for ancienne in "${archives[@]:0:${#archives[@]}-retention}"; do
        rm -f -- "$ancienne" "${ancienne}.sha256"
        echo "Retiré du miroir : $(basename "$ancienne")"
    done
fi

case "$destination" in
    ftp://*|ftps://*)
        # shellcheck source=/dev/null
        . "$(dirname "${BASH_SOURCE[0]}")/lib/ftp.sh"
        ftp_preparer
        ftp_deposer_miroir "$stage" "$destination" "$retention"
        ;;
    *)
        rsync_options=(--archive --delete --checksum)
        if [[ -n "${GAMAD_OFFSITE_SSH_KEY:-}" ]]; then
            rsync_options+=(--rsh "ssh -i ${GAMAD_OFFSITE_SSH_KEY} -o StrictHostKeyChecking=yes")
        fi
        rsync "${rsync_options[@]}" "${stage%/}/" "$destination"
        ;;
esac

echo "Lot transporté : ${horodatage}"
echo "Chiffrement : $([[ -n "$recipient" ]] && echo "destinataire ${recipient}" || echo 'phrase secrète AES256')"
echo "Destination : ${destination}"
echo "Rétention : ${retention} lot(s)"
echo "Aucune phrase secrète n’a été passée en argument."
