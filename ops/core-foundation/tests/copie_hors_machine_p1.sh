#!/usr/bin/env bash
#
# Épreuve de la copie hors machine (CAP-CORE-019).
#
# Elle s'exécute sans aucun identifiant : un répertoire temporaire tient lieu
# de destination distante. La même variable accepte `utilisateur@hote:/chemin`
# le jour où la destination réelle est choisie — le code ne change pas.
#
# Exécution : ops/core-foundation/tests/copie_hors_machine_p1.sh

set -Eeuo pipefail
umask 077

racine="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
temp="$(mktemp -d /tmp/gamad-copie-hors-machine.XXXXXX)"
trap 'rm -rf -- "$temp"' EXIT

echo "ÉPREUVE — COPIE HORS MACHINE (CAP-CORE-019)"
echo

echecs=0
verifier() {
    if [[ "$1" == "0" ]]; then
        printf '  [OK]    %s\n' "$2"
    else
        printf '  [ÉCHEC] %s\n' "$2"
        echecs=$((echecs + 1))
    fi
}

lots="${temp}/lots"
destination="${temp}/distant"
stage="${temp}/miroir"
mkdir -p "$lots" "$destination"

phrase="${temp}/phrase"
printf 'phrase-secrete-epreuve-%s' "$RANDOM$RANDOM" > "$phrase"

fabriquer_lot() {
    local horodatage="$1"
    local dossier="${lots}/${horodatage}"
    mkdir -p "$dossier"
    for cible in index acces identites journal; do
        printf 'contenu factice %s du lot %s\n' "$cible" "$horodatage" > "${dossier}/${cible}.dump"
    done
    ( cd "$dossier" && sha256sum index.dump acces.dump identites.dump journal.dump > SHA256SUMS )
}

export GAMAD_BACKUP_DIR="$lots"
export GAMAD_OFFSITE_STAGE="$stage"
export GAMAD_OFFSITE_PASSPHRASE_FILE="$phrase"

# 1 — sans destination, rien ne part et le script n'échoue pas.
fabriquer_lot 20260801T010000Z
sortie="$(GAMAD_OFFSITE_DEST='' "${racine}/offsite.sh" 2>&1)" && code=0 || code=$?
[[ "$code" == "0" ]] \
    && [[ "$sortie" == *"désactivée"* ]] \
    && [[ -z "$(ls -A "$destination")" ]]
verifier $? "sans destination configurée, rien ne quitte la machine et le script réussit"

# 2 — sans chiffrement, le transport est refusé.
set +e
GAMAD_OFFSITE_DEST="$destination" \
GAMAD_OFFSITE_PASSPHRASE_FILE='' \
    "${racine}/offsite.sh" >/dev/null 2>&1
code=$?
set -e
[[ "$code" != "0" ]] && [[ -z "$(ls -A "$destination")" ]]
verifier $? "un lot ne part jamais en clair, faute de chiffrement configuré"

# 3 — transport nominal : l'archive arrive, chiffrée, avec son empreinte.
GAMAD_OFFSITE_DEST="$destination" "${racine}/offsite.sh" >/dev/null
archive="${destination}/20260801T010000Z.tar.gz.gpg"
[[ -f "$archive" ]] \
    && [[ -f "${archive}.sha256" ]] \
    && ! tar --list --file "$archive" >/dev/null 2>&1 \
    && ! grep -aq 'contenu factice' "$archive"
verifier $? "le lot arrive à destination, chiffré et accompagné de son empreinte"

# 4 — aller-retour : ce qui revient est exactement ce qui est parti.
( cd "$destination" && sha256sum --quiet --check "$(basename "$archive").sha256" ) \
    && gpg --batch --yes --pinentry-mode loopback --passphrase-file "$phrase" \
        --decrypt --output "${temp}/retour.tar.gz" "$archive" 2>/dev/null \
    && tar --extract --gzip --file "${temp}/retour.tar.gz" --directory "${temp}" \
    && diff -r "${lots}/20260801T010000Z" "${temp}/20260801T010000Z" >/dev/null
verifier $? "l’archive se déchiffre et restitue le lot à l’identique"

# 5 — un lot dont les empreintes ne concordent plus ne part pas.
fabriquer_lot 20260801T020000Z
printf 'altération' >> "${lots}/20260801T020000Z/journal.dump"
set +e
GAMAD_OFFSITE_DEST="$destination" "${racine}/offsite.sh" >/dev/null 2>&1
code=$?
set -e
[[ "$code" != "0" ]] && [[ ! -f "${destination}/20260801T020000Z.tar.gz.gpg" ]]
verifier $? "un lot corrompu est refusé au transport, jamais transporté quand même"

# 6 — la rétention borne le nombre de lots conservés à distance.
rm -rf "${lots}/20260801T020000Z"
fabriquer_lot 20260801T030000Z
fabriquer_lot 20260801T040000Z
GAMAD_OFFSITE_DEST="$destination" GAMAD_OFFSITE_RETENTION=2 \
    "${racine}/offsite.sh" "${lots}/20260801T030000Z" >/dev/null
GAMAD_OFFSITE_DEST="$destination" GAMAD_OFFSITE_RETENTION=2 \
    "${racine}/offsite.sh" "${lots}/20260801T040000Z" >/dev/null
conserves="$(find "$destination" -maxdepth 1 -type f -name '*.tar.gz.gpg' | wc -l)"
[[ "$conserves" == "2" ]] \
    && [[ ! -f "${destination}/20260801T010000Z.tar.gz.gpg" ]] \
    && [[ -f "${destination}/20260801T040000Z.tar.gz.gpg" ]]
verifier $? "la rétention retire les lots les plus anciens de la destination"

# 6 bis — un miroir local impossible à créer est nommé, pas subi.
#
# Le blocage passe par un fichier ordinaire en guise de parent : un simple
# retrait de droits ne prouverait rien, root les ignore et l'épreuve serait
# verte sans rien démontrer.
: > "${temp}/fichier-bloquant"
set +e
sortie="$(GAMAD_OFFSITE_DEST="$destination" \
    GAMAD_OFFSITE_STAGE="${temp}/fichier-bloquant/hors-machine" \
    "${racine}/offsite.sh" 2>&1)"
code=$?
set -e
[[ "$code" != "0" ]] && [[ "$sortie" == *"miroir local"* ]] && [[ "$sortie" == *"installer-continuite.sh"* ]]
verifier $? "un miroir local impossible à créer produit un refus explicite, pas une erreur brute"

# 7 — l'exercice de restauration relit la copie distante, empreintes comprises.
sortie="$(GAMAD_OFFSITE_DEST="$destination" \
    GAMAD_OFFSITE_PASSPHRASE_FILE="$phrase" \
    GAMAD_OFFSITE_DRILL_DUMPS_ONLY=1 \
    "${racine}/offsite-drill.sh" 2>&1)"
[[ "$sortie" == *"20260801T040000Z"* ]] \
    && [[ "$sortie" == *"Empreinte de l’archive : concordante."* ]] \
    && [[ "$sortie" == *"Empreintes des dumps : concordantes."* ]]
verifier $? "l’exercice récupère, vérifie et déchiffre la copie la plus récente"

# 8 — une archive dont l'empreinte ne concorde plus est refusée au retour.
printf 'octet parasite' >> "${destination}/20260801T040000Z.tar.gz.gpg"
set +e
GAMAD_OFFSITE_DEST="$destination" \
GAMAD_OFFSITE_PASSPHRASE_FILE="$phrase" \
GAMAD_OFFSITE_DRILL_DUMPS_ONLY=1 \
    "${racine}/offsite-drill.sh" >/dev/null 2>&1
code=$?
set -e
[[ "$code" != "0" ]]
verifier $? "une archive altérée en transit est refusée avant tout déchiffrement"

# 9 — le chiffrement fonctionne quand le répertoire personnel est fermé,
# comme sous `ProtectHome=true`. Sans foyer GPG jetable, gpg s'arrête net.
fabriquer_lot 20260801T050000Z
sortie="$(env HOME=/proc GNUPGHOME= GAMAD_OFFSITE_DEST="$destination" \
    "${racine}/offsite.sh" "${lots}/20260801T050000Z" 2>&1)" && code=0 || code=$?
[[ "$code" == "0" ]] \
    && [[ -f "${destination}/20260801T050000Z.tar.gz.gpg" ]] \
    && [[ "$sortie" != *"can't create directory"* ]]
verifier $? "le chiffrement aboutit même sans répertoire personnel accessible"

# 10 — aucune archive en clair ne survit à l'exécution.
avant="$(find /tmp -maxdepth 2 -name '*.tar.gz' -newermt '-2 minutes' 2>/dev/null | wc -l)"
fabriquer_lot 20260801T060000Z
GAMAD_OFFSITE_DEST="$destination" "${racine}/offsite.sh" "${lots}/20260801T060000Z" >/dev/null
apres="$(find /tmp -maxdepth 2 -name '*.tar.gz' -newermt '-2 minutes' 2>/dev/null | wc -l)"
[[ "$apres" == "$avant" ]]
verifier $? "l’archive non chiffrée ne survit pas au transport (avant ${avant}, après ${apres})"

echo
if [[ "$echecs" == "0" ]]; then
    echo "Copie hors machine P1 : ÉTABLIE."
    exit 0
fi
echo "Copie hors machine P1 : NON ÉTABLIE (${echecs} écart(s))."
exit 1
