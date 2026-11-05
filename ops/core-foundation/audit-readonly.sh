#!/usr/bin/env bash
# GAMAD Core — diagnostic non destructif. Aucun secret ni contenu de base n'est lu.
set -uo pipefail
export LC_ALL=C
umask 077
if [[ "${1:-}" != "--read-only" || $# -ne 1 ]]; then
  echo "Usage: bash ops/core-foundation/audit-readonly.sh --read-only" >&2
  exit 2
fi
if [[ "${EUID:-$(id -u)}" -eq 0 ]]; then
  echo "Refus : exécuter avec un compte non privilégié." >&2
  exit 2
fi
if [[ -n "${GAMAD_AUDIT_OUTPUT:-}" ]]; then
  echo "Refus : sortie personnalisée non autorisée (évite l'écrasement)." >&2
  exit 2
fi
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd -P)"
if [[ ! -f "$ROOT/apps/console-laravel/artisan" ]]; then
  echo "Refus : arborescence Core introuvable." >&2
  exit 2
fi
REPORT="$(mktemp -d "${TMPDIR:-/tmp}/gamad-audit.XXXXXXXX")" || exit 2
chmod 700 "$REPORT"
OUT="$REPORT/report.txt"
: > "$OUT"
exec 3>&1
exec > "$OUT" 2>&1
section() { printf '\n## %s\n' "$1"; }
check() {
  local label="$1"; shift
  printf '%s: ' "$label"
  if "$@" >/dev/null 2>&1; then echo "PASS"; else echo "FAIL_OR_UNAVAILABLE"; fi
}
section "Identification"
date -u '+UTC=%Y-%m-%dT%H:%M:%SZ'
printf 'hostname='; hostname -s 2>/dev/null || echo unavailable
printf 'user='; id -un
printf 'git_commit='; git -C "$ROOT" rev-parse --verify HEAD 2>/dev/null || echo unavailable
printf 'git_branch='; git -C "$ROOT" symbolic-ref --quiet --short HEAD 2>/dev/null || echo detached_or_unavailable
printf 'git_dirty='
if git -C "$ROOT" diff --quiet -- && git -C "$ROOT" diff --cached --quiet --; then echo no_tracked_changes; else echo tracked_changes; fi
section "Environment"
for command in php psql pg_isready systemctl curl git; do
  if command -v "$command" >/dev/null 2>&1; then
    printf '%s=available\n' "$command"
  else
    printf '%s=missing\n' "$command"
  fi
done
php -r 'echo "php_version=",PHP_VERSION,PHP_EOL;' 2>/dev/null || true
section "Repository checks"
check "composer_lock" test -f "$ROOT/apps/console-laravel/composer.lock"
check "vendor_autoload" test -f "$ROOT/apps/console-laravel/vendor/autoload.php"
check "api_routes" test -f "$ROOT/apps/console-laravel/routes/api.php"
check "backup_script" test -f "$ROOT/ops/core-foundation/backup.sh"
check "restore_drill_script" test -f "$ROOT/ops/core-foundation/restore-drill.sh"
check "matching_tests" test -f "$ROOT/core/moteur-matching/tests/matching_p6.php"
section "HTTP health (loopback only)"
# A loopback endpoint may be behind HTTPS/proxy: unavailable is not proof of outage.
if command -v curl >/dev/null 2>&1; then
  for endpoint in /api/v1/health/live /api/v1/health/ready; do
    code="$(curl --noproxy '*' --silent --output /dev/null --write-out '%{http_code}' --connect-timeout 2 --max-time 5 "http://127.0.0.1$endpoint" 2>/dev/null)" || code=unreachable
    printf '%s_http_loopback=%s\n' "$endpoint" "$code"
  done
fi
section "PostgreSQL reachability (no credentials or database contents)"
if command -v pg_isready >/dev/null 2>&1; then
  if pg_isready -q -h 127.0.0.1 -p 5432; then echo "postgres_loopback=accepting"; else echo "postgres_loopback=unavailable_or_remote"; fi
fi
section "Declared database targets"
for name in INDEX ACCESS IDENTITY PRODUCTS SOURCES POLICIES CONTRACTS VOCABULARY ORGANIZATIONS REALMS JOURNAL EVENEMENTS SECRETS PREUVES MATCHING; do
  service_var="GAMAD_${name}_PGSERVICE"
  db_var="GAMAD_${name}_PGDATABASE"
  if [[ -n "${!service_var:-}" || -n "${!db_var:-}" ]]; then
    echo "${name}=configured_in_process_environment"
  else
    echo "${name}=not_visible_in_process_environment"
  fi
done
section "Service activity (non-privileged, selected units only)"
if command -v systemctl >/dev/null 2>&1; then
  for unit in nginx php8.3-fpm postgresql gamad-matching-expiration.timer; do
    printf '%s=' "$unit"
    systemctl is-active "$unit" 2>/dev/null || true
  done
fi
section "Scope and limitations"
echo "READ_ONLY: no migrations, no tests that write data, no backups or restores executed."
echo "No privileged access; no database credentials, secrets, process environment dumps, or logs captured."
echo "A PASS here is diagnostic only; it is not a production-readiness certificate."
exec 1>&3 3>&-
printf 'Audit report: %s\n' "$OUT"
