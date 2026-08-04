#!/usr/bin/env bash
#
# Analyse de sécurité du dépôt (CAP-CORE-016, fiche partie 4 §15).
#
# Recherche les motifs évidents de secrets committés : clés privées, JWT
# complets, mots de passe en URI, secrets AWS, tokens GitHub, fichiers .env
# suivis par Git, fichiers de clé privée. Ne remplace pas un outil
# spécialisé externe ; protège seulement les erreurs évidentes.
#
# Prouve sa propre capacité à échouer avec des canaris synthétiques avant de
# scanner le dépôt réel — une garde qui ne sait pas échouer ne prouve rien.
#
# Exécution : ops/core-foundation/tests/secrets_analyse_depot_p1.sh

set -Eeuo pipefail

racine="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
echecs=0

verifier() {
    if [[ "$1" == "0" ]]; then
        printf '  [OK]    %s\n' "$2"
    else
        printf '  [ÉCHEC] %s\n' "$2"
        echecs=$((echecs + 1))
    fi
}

echo "ÉPREUVE — ANALYSE DE SÉCURITÉ DU DÉPÔT (CAP-CORE-016)"
echo

# --- Contre-épreuve : la garde sait échouer -----------------------------
canari="$(mktemp -d)"
trap 'rm -rf -- "$canari"' EXIT

cat > "$canari/prive.pem" <<'EOF'
-----BEGIN PRIVATE KEY-----
MIIBVgIBADANBgkqhkiG9w0BAQEFAASCAT8wggE7AgEAAkEAxxxxxxxxxxxxxxxx
-----END PRIVATE KEY-----
EOF
echo 'AWS_SECRET_ACCESS_KEY=AKIAABCDEFGHIJKLMNOP/canari0000000000000000' > "$canari/aws.env"
echo 'postgresql://utilisateur:motdepasse@hote/base' > "$canari/uri.txt"

motif_cle_privee='BEGIN (RSA |EC |OPENSSH |DSA |PGP )?PRIVATE KEY'
motif_secret_aws='AKIA[0-9A-Z]{16}'
motif_uri_mdp='(postgres|postgresql|mysql|redis|ftp|https?)://[^:/[:space:]]+:[^@/[:space:]]+@'
motif_jwt='eyJ[A-Za-z0-9_-]{10,}\.eyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}'
motif_token_github='gh[pousr]_[A-Za-z0-9]{20,}'

resultat=0
grep -Eq "$motif_cle_privee" "$canari/prive.pem" || resultat=1
grep -Eq "$motif_secret_aws" "$canari/aws.env" || resultat=1
grep -Eq "$motif_uri_mdp" "$canari/uri.txt" || resultat=1
verifier "$resultat" "les canaris synthétiques (clé privée, secret AWS, URI avec mot de passe) sont bien détectés"

echo 'valeur inoffensive sans motif de secret' > "$canari/propre.txt"
resultat=0
grep -Eq "$motif_cle_privee|$motif_secret_aws|$motif_uri_mdp|$motif_jwt|$motif_token_github" "$canari/propre.txt" && resultat=1
verifier "$resultat" "un fichier sans motif de secret n'est pas signalé à tort"

# --- Analyse réelle du dépôt ---------------------------------------------
cd "$racine"

fichiers_a_scanner() {
    git ls-files -- \
        ':!:*.png' ':!:*.jpg' ':!:*.jpeg' ':!:*.gif' ':!:*.svg' ':!:*.ico' \
        ':!:*.lock' ':!:vendor/**' ':!:node_modules/**' \
        ':!:*/tests/Integration/*' ':!:*/tests/*_p3.php' \
        ':!:ops/core-foundation/tests/secrets_analyse_depot_p1.sh'
}

trouve=0
while IFS= read -r motif_nom; do
    motif="${motif_nom%%|*}"
    nom="${motif_nom#*|}"
    resultat="$(fichiers_a_scanner | xargs -r grep -EIln "$motif" 2>/dev/null || true)"
    if [[ -n "$resultat" ]]; then
        echo "  [ÉCHEC] motif « ${nom} » détecté dans :" >&2
        echo "$resultat" | sed 's/^/            /' >&2
        trouve=1
    fi
done <<'MOTIFS'
BEGIN (RSA |EC |OPENSSH |DSA |PGP )?PRIVATE KEY|clé privée
AKIA[0-9A-Z]{16}|secret AWS
eyJ[A-Za-z0-9_-]{10,}\.eyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}|JWT complet
gh[pousr]_[A-Za-z0-9]{20,}|token GitHub
(postgres|postgresql|mysql|redis|ftp)://[^:/[:space:]]+:[^@/[:space:]]+@|mot de passe en URI
MOTIFS
verifier "$trouve" "aucun motif de secret réel dans les fichiers suivis par Git"

# Fichiers .env réels suivis par Git (les .env.example sont attendus).
fichiers_env="$(git ls-files -- '*.env' ':!:*.env.example' 2>/dev/null || true)"
verifier "$([[ -z "$fichiers_env" ]] && echo 0 || echo 1)" "aucun fichier .env réel n'est suivi par Git"

# Fichiers de clé privée suivis par Git.
fichiers_cle="$(git ls-files -- '*.pem' '*_rsa' '*_ed25519' '*_ecdsa' '*_dsa' ':!:*.pem.example' 2>/dev/null || true)"
verifier "$([[ -z "$fichiers_cle" ]] && echo 0 || echo 1)" "aucun fichier de clé privée n'est suivi par Git"

echo
if [[ "$echecs" -eq 0 ]]; then
    echo "Analyse de sécurité du dépôt : ÉTABLIE."
    exit 0
fi

echo "Analyse de sécurité du dépôt : NON ÉTABLIE (${echecs} écart(s))."
exit 1
