#!/usr/bin/env bash
#
# Épreuve du transport FTP de la copie hors machine (CAP-CORE-019).
#
# Elle dialogue avec un véritable serveur FTP — le double d'épreuve
# `serveur_ftp_double.py` — plutôt qu'avec une simulation de commande. Elle
# prouve donc la logique du transport et l'usage de curl.
#
# LIMITE ASSUMÉE : elle ne prouve pas la compatibilité avec le serveur d'un
# hébergeur donné. Seule une première exécution réelle le prouvera.
#
# Exécution : ops/core-foundation/tests/copie_hors_machine_ftp_p1.sh

set -Eeuo pipefail
umask 077

racine="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
temp="$(mktemp -d /tmp/gamad-copie-ftp.XXXXXX)"
serveur_pid=""
nettoyer() {
    [[ -n "$serveur_pid" ]] && kill "$serveur_pid" 2>/dev/null || true
    rm -rf -- "$temp"
}
trap nettoyer EXIT

echo "ÉPREUVE — COPIE HORS MACHINE PAR FTP (CAP-CORE-019)"
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
distant="${temp}/distant"
stage="${temp}/miroir"
mkdir -p "$lots" "$distant"

phrase="${temp}/phrase"
printf 'phrase-secrete-ftp-%s' "$RANDOM$RANDOM" > "$phrase"
secret_ftp="${temp}/secret-ftp"
motdepasse="motdepasse-ftp-$RANDOM"
printf '%s\n' "$motdepasse" > "$secret_ftp"

port=$(( 21000 + RANDOM % 4000 ))
python3 "${racine}/tests/serveur_ftp_double.py" "$distant" "$port" gamad "$motdepasse" \
    > "${temp}/serveur.log" 2>&1 &
serveur_pid=$!

for _ in $(seq 1 50); do
    if grep -q "double FTP prêt" "${temp}/serveur.log" 2>/dev/null; then break; fi
    sleep 0.1
done
grep -q "double FTP prêt" "${temp}/serveur.log"
verifier $? "le double FTP d’épreuve répond sur 127.0.0.1:${port}"

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
export GAMAD_OFFSITE_DEST="ftp://127.0.0.1:${port}/copies"
export GAMAD_OFFSITE_FTP_USER=gamad
export GAMAD_OFFSITE_FTP_SECRET_FILE="$secret_ftp"
# Le double ne parle pas TLS : le mode opportuniste doit savoir continuer.
export GAMAD_OFFSITE_FTP_TLS=opportuniste

# 1 — dépôt nominal sur un serveur FTP réel.
fabriquer_lot 20260801T010000Z
"${racine}/offsite.sh" >/dev/null
archive="${distant}/copies/20260801T010000Z.tar.gz.gpg"
[[ -f "$archive" ]] \
    && [[ -f "${archive}.sha256" ]] \
    && ! grep -aq 'contenu factice' "$archive"
verifier $? "le lot est déposé par FTP, chiffré, avec son empreinte"

# 2 — le mot de passe ne passe jamais par la ligne de commande.
grep -q -- '--config' "${racine}/lib/ftp.sh" \
    && ! grep -qE -- '--user[ =]' "${racine}/lib/ftp.sh" \
    && grep -q 'chmod 700' "${racine}/lib/ftp.sh" \
    && grep -qE "^\s*trap 'rm -rf" "${racine}/lib/ftp.sh"
verifier $? "le mot de passe transite par un fichier de configuration éphémère, jamais par argv"

# 3 — un mot de passe faux ferme le transport.
faux="${temp}/faux-secret"
printf 'mauvais-mot-de-passe\n' > "$faux"
fabriquer_lot 20260801T020000Z
set +e
GAMAD_OFFSITE_FTP_SECRET_FILE="$faux" "${racine}/offsite.sh" >/dev/null 2>&1
code=$?
set -e
[[ "$code" != "0" ]] && [[ ! -f "${distant}/copies/20260801T020000Z.tar.gz.gpg" ]]
verifier $? "une authentification refusée interrompt le transport"

# 4 — TLS exigé sur un serveur sans TLS : refus plutôt que repli silencieux.
set +e
GAMAD_OFFSITE_FTP_TLS=exige "${racine}/offsite.sh" >/dev/null 2>&1
code=$?
set -e
[[ "$code" != "0" ]]
verifier $? "TLS exigé ferme le transport quand le serveur ne le propose pas"

# 5 — rétention : la destination suit le miroir local.
"${racine}/offsite.sh" >/dev/null
fabriquer_lot 20260801T030000Z
GAMAD_OFFSITE_RETENTION=2 "${racine}/offsite.sh" >/dev/null
conserves="$(find "${distant}/copies" -maxdepth 1 -type f -name '*.tar.gz.gpg' | wc -l)"
[[ "$conserves" == "2" ]] \
    && [[ ! -f "${distant}/copies/20260801T010000Z.tar.gz.gpg" ]] \
    && [[ -f "${distant}/copies/20260801T030000Z.tar.gz.gpg" ]]
verifier $? "la rétention retire les archives les plus anciennes du serveur FTP"

# 6 — l'exercice de restauration relit la copie FTP.
sortie="$(GAMAD_OFFSITE_DRILL_DUMPS_ONLY=1 "${racine}/offsite-drill.sh" 2>&1)"
[[ "$sortie" == *"20260801T030000Z"* ]] \
    && [[ "$sortie" == *"Empreinte de l’archive : concordante."* ]] \
    && [[ "$sortie" == *"Empreintes des dumps : concordantes."* ]]
verifier $? "l’exercice récupère et vérifie la copie la plus récente par FTP"

# 7 — une archive altérée sur le serveur est refusée avant déchiffrement.
printf 'octet parasite' >> "${distant}/copies/20260801T030000Z.tar.gz.gpg"
set +e
GAMAD_OFFSITE_DRILL_DUMPS_ONLY=1 "${racine}/offsite-drill.sh" >/dev/null 2>&1
code=$?
set -e
[[ "$code" != "0" ]]
verifier $? "une archive altérée sur le serveur distant est refusée au retour"

echo
if [[ "$echecs" == "0" ]]; then
    echo "Copie hors machine par FTP P1 : ÉTABLIE."
    exit 0
fi
echo "Copie hors machine par FTP P1 : NON ÉTABLIE (${echecs} écart(s))."
exit 1
