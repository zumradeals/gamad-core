#!/usr/bin/env bash
#
# Épreuve du service de continuité piloté par la console (CAP-CORE-019).
#
# Elle éprouve le chemin qui va du fichier-signal déposé par la console à
# l'état relu par la console, sans PostgreSQL : c'est la mécanique d'échange
# entre deux comptes qui est en cause ici, pas la sauvegarde elle-même, déjà
# couverte par postgresql_p0.sh.
#
# Exécution : ops/core-foundation/tests/continuite_p1.sh

set -Eeuo pipefail
umask 007

racine="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
temp="$(mktemp -d /tmp/gamad-continuite.XXXXXX)"
trap 'rm -rf -- "$temp"' EXIT

echo "ÉPREUVE — CONTINUITÉ PILOTÉE (CAP-CORE-019)"
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

partage="${temp}/partage"
lots="${temp}/lots"
mkdir -p "${partage}/demandes" "$lots"

export GAMAD_CONTINUITE_DIR="$partage"
export GAMAD_BACKUP_DIR="$lots"

# 1 — l'état s'écrit même quand rien n'est configuré.
"${racine}/continuite.sh" etat >/dev/null
grep -q '"destination_configuree": false' "${partage}/etat.json" \
    && grep -q '"lots_locaux": 0' "${partage}/etat.json" \
    && python3 -c "import json,sys; json.load(open('${partage}/etat.json'))"
verifier $? "l’état s’écrit et reste un JSON valide sans destination configurée"

# 2 — l'état reflète les réglages écrits par la console, sans le mot de passe.
printf 'motdepasse-tres-secret\n' > "${partage}/ftp.secret"
cat > "${partage}/offsite.env" <<ENV
GAMAD_OFFSITE_DEST=ftp://ftp.exemple.test/gamad
GAMAD_OFFSITE_FTP_USER=sauvegarde
GAMAD_OFFSITE_FTP_SECRET_FILE=${partage}/ftp.secret
GAMAD_OFFSITE_FTP_TLS=opportuniste
GAMAD_OFFSITE_PASSPHRASE_FILE=${partage}/chiffrement.secret
GAMAD_OFFSITE_RETENTION=7
ENV
mkdir -p "${lots}/20260801T120000Z"
: > "${lots}/20260801T120000Z/index.dump"
"${racine}/continuite.sh" etat >/dev/null
grep -q '"destination_configuree": true' "${partage}/etat.json" \
    && grep -q '"transport": "FTP"' "${partage}/etat.json" \
    && grep -q '"retention": 7' "${partage}/etat.json" \
    && grep -q '"dernier_lot_local": "20260801T120000Z"' "${partage}/etat.json" \
    && ! grep -q 'motdepasse-tres-secret' "${partage}/etat.json"
verifier $? "l’état publie la destination et la rétention, jamais le mot de passe"

# 3 — sans demande, servir-demande ne fait rien et réussit.
"${racine}/continuite.sh" servir-demande | grep -q "Aucune demande en attente"
verifier $? "sans fichier-signal, le service ne déclenche rien"

# 4 — une demande est consommée, même quand l'opération échoue, et l'échec est
#     propagé pour que l'alerte systemd se déclenche.
touch "${partage}/demandes/sauvegarde.demande"
set +e
sortie="$("${racine}/continuite.sh" servir-demande 2>&1)"
code=$?
set -e
[[ "$code" != "0" ]] \
    && [[ ! -e "${partage}/demandes/sauvegarde.demande" ]] \
    && [[ "$sortie" == *"ÉCHEC"* ]] \
    && grep -q '"resultat": "echec"' "${partage}/etat.json" \
    && grep -q '"action": "sauvegarde"' "${partage}/etat.json"
verifier $? "une demande est consommée une seule fois et son échec est visible"

# 4 bis — un échec ne se compte jamais comme un envoi confirmé.
grep -q '"dernier_envoi_confirme": null' "${partage}/etat.json"
verifier $? "une opération échouée ne fait pas croire qu’une copie est partie"

# 5 — la trace de l'échec précédent survit à un simple recalcul d'état.
"${racine}/continuite.sh" etat >/dev/null
grep -q '"resultat": "echec"' "${partage}/etat.json"
verifier $? "recalculer l’état n’efface pas la dernière opération connue"

# 6 — la sortie détaillée reste lisible par le groupe partagé, pas par le monde.
mode="$(stat -c '%a' "${partage}/derniere-sortie.txt" 2>/dev/null || echo absent)"
[[ "$mode" == "660" || "$mode" == "600" ]]
verifier $? "la sortie détaillée n’est pas lisible par tout le monde (mode ${mode})"

# 7 — une opération inconnue n'est jamais servie.
touch "${partage}/demandes/effacer-tout.demande"
"${racine}/continuite.sh" servir-demande >/dev/null 2>&1 || true
[[ -e "${partage}/demandes/effacer-tout.demande" ]]
verifier $? "un fichier-signal hors liste close est ignoré, jamais exécuté"

# 8 — un exercice ne se restaure jamais sur une base d'exploitation.
set +e
sortie="$(GAMAD_RESTORE_SOURCE=/tmp \
    GAMAD_RESTORE_DRILL_CONFIRM=isolated-empty-databases \
    GAMAD_INDEX_PGDATABASE=registre_normes \
    GAMAD_RESTORE_INDEX_PGDATABASE=registre_normes \
    GAMAD_RESTORE_ACCESS_PGDATABASE=drill_access \
    GAMAD_RESTORE_IDENTITY_PGDATABASE=drill_identity \
    GAMAD_RESTORE_JOURNAL_PGDATABASE=drill_journal \
    "${racine}/restore-drill.sh" 2>&1)"
code=$?
set -e
[[ "$code" != "0" ]] && [[ "$sortie" == *"base"* ]] && [[ "$sortie" == *"exploitation"* ]]
verifier $? "restaurer un exercice sur une base d’exploitation est refusé"

# 9 — la confirmation reste exigée : la garde ne la remplace pas.
set +e
GAMAD_RESTORE_SOURCE=/tmp GAMAD_RESTORE_INDEX_PGDATABASE=drill_index \
    "${racine}/restore-drill.sh" >/dev/null 2>&1
code=$?
set -e
[[ "$code" != "0" ]]
verifier $? "un exercice sans confirmation explicite reste refusé"

echo
if [[ "$echecs" == "0" ]]; then
    echo "Continuité pilotée P1 : ÉTABLIE."
    exit 0
fi
echo "Continuité pilotée P1 : NON ÉTABLIE (${echecs} écart(s))."
exit 1
