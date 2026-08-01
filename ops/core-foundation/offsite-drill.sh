#!/usr/bin/env bash
#
# Exercice de restauration DEPUIS LA COPIE HORS MACHINE.
#
# `restore-drill.sh` prouve qu'un lot local se recharge. Celui-ci prouve la
# seule chose qui compte le jour d'un sinistre : que la copie distante se
# récupère, se déchiffre et se recharge. Une sauvegarde jamais relue n'est pas
# une sauvegarde, c'est une intention.
#
# Variables :
#   GAMAD_OFFSITE_DEST            source rsync (obligatoire)
#   GAMAD_OFFSITE_PASSPHRASE_FILE fichier de phrase secrète, ou clé GPG privée
#                                 disponible pour GAMAD_OFFSITE_RECIPIENT
#   GAMAD_OFFSITE_SSH_KEY         clé SSH dédiée, si source distante
#   + les variables de restore-drill.sh pour les bases isolées de l'exercice
#
# Le script ne touche jamais aux bases de production : il délègue à
# restore-drill.sh, qui exige des cibles isolées et une confirmation explicite.

set -Eeuo pipefail
umask 077

racine="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

: "${GAMAD_OFFSITE_DEST:?GAMAD_OFFSITE_DEST doit désigner la copie hors machine}"

# shellcheck source=/dev/null
. "${racine}/lib/nettoyage.sh"
temporaire="$(mktemp -d)"
gamad_a_nettoyer "$temporaire"

mkdir -p "${temporaire}/recuperation"
case "$GAMAD_OFFSITE_DEST" in
    ftp://*|ftps://*)
        # shellcheck source=/dev/null
        . "${racine}/lib/ftp.sh"
        ftp_preparer
        derniere="$(ftp_lister "$GAMAD_OFFSITE_DEST" | tail -1)"
        if [[ -z "$derniere" ]]; then
            echo "Aucune archive chiffrée à ${GAMAD_OFFSITE_DEST}." >&2
            exit 1
        fi
        ftp_recuperer "$derniere" "$GAMAD_OFFSITE_DEST" "${temporaire}/recuperation/${derniere}"
        ftp_recuperer "${derniere}.sha256" "$GAMAD_OFFSITE_DEST" \
            "${temporaire}/recuperation/${derniere}.sha256"
        ;;
    *)
        rsync_options=(--archive)
        if [[ -n "${GAMAD_OFFSITE_SSH_KEY:-}" ]]; then
            rsync_options+=(--rsh "ssh -i ${GAMAD_OFFSITE_SSH_KEY} -o StrictHostKeyChecking=yes")
        fi
        rsync "${rsync_options[@]}" "${GAMAD_OFFSITE_DEST%/}/" "${temporaire}/recuperation/"
        ;;
esac

archive="$(find "${temporaire}/recuperation" -maxdepth 1 -type f -name '*.tar.gz.gpg' | sort | tail -1)"
if [[ -z "$archive" ]]; then
    echo "Aucune archive chiffrée récupérée depuis ${GAMAD_OFFSITE_DEST}." >&2
    exit 1
fi
echo "Archive récupérée : $(basename "$archive")"

# L'empreinte est vérifiée avant tout déchiffrement : ce qui a voyagé doit
# être exactement ce qui est parti.
if [[ -r "${archive}.sha256" ]]; then
    ( cd "$(dirname "$archive")" && sha256sum --quiet --check "$(basename "$archive").sha256" )
    echo "Empreinte de l’archive : concordante."
else
    echo "Refus : aucune empreinte n’accompagne l’archive." >&2
    exit 1
fi

# shellcheck source=/dev/null
. "${racine}/lib/gpg.sh"
gpg_preparer_home

if [[ -n "${GAMAD_OFFSITE_PASSPHRASE_FILE:-}" ]]; then
    gpg --batch --yes --pinentry-mode loopback \
        --passphrase-file "$GAMAD_OFFSITE_PASSPHRASE_FILE" \
        --decrypt --output "${temporaire}/lot.tar.gz" "$archive"
else
    gpg --batch --yes --decrypt --output "${temporaire}/lot.tar.gz" "$archive"
fi

tar --extract --gzip --file "${temporaire}/lot.tar.gz" --directory "${temporaire}"
lot="$(find "${temporaire}" -mindepth 1 -maxdepth 1 -type d \
    -regextype posix-extended -regex '.*/[0-9]{8}T[0-9]{6}Z$' | sort | tail -1)"
if [[ -z "$lot" ]]; then
    echo "L’archive ne contient aucun lot de sauvegarde reconnaissable." >&2
    exit 1
fi

( cd "$lot" && sha256sum --quiet --check SHA256SUMS )
echo "Empreintes des dumps : concordantes."

if [[ "${GAMAD_OFFSITE_DRILL_DUMPS_ONLY:-}" == "1" ]]; then
    echo "Exercice limité aux dumps : rechargement PostgreSQL non demandé."
    exit 0
fi

export GAMAD_RESTORE_SOURCE="$lot"
"${racine}/restore-drill.sh"

echo "Exercice de restauration depuis la copie hors machine : ÉTABLI."
