#!/usr/bin/env sh
set -eu

STATE_DIR="${GAMAD_TEST_IDP_STATE_DIR:-$(pwd)/var/test-idp}"
IDP_PORT="${GAMAD_TEST_IDP_PORT:-9090}"
API_PORT="${GAMAD_TEST_API_PORT:-8080}"
ISSUER="http://127.0.0.1:${IDP_PORT}"

cleanup() {
  [ -n "${IDP_PID:-}" ] && kill "$IDP_PID" 2>/dev/null || true
  [ -n "${API_PID:-}" ] && kill "$API_PID" 2>/dev/null || true
}
trap cleanup EXIT INT TERM

GAMAD_TEST_IDP_STATE_DIR="$STATE_DIR" php tools/test-idp/init.php 1 >/dev/null
GAMAD_TEST_IDP_STATE_DIR="$STATE_DIR" GAMAD_TEST_IDP_ISSUER="$ISSUER" \
  php -S "127.0.0.1:${IDP_PORT}" tools/test-idp/router.php >/tmp/gamad-test-idp.log 2>&1 &
IDP_PID=$!

GAMAD_PG_DSN="${GAMAD_TEST_PG_DSN}" \
GAMAD_PG_USER="${GAMAD_TEST_PG_USER:-}" \
GAMAD_PG_PASSWORD="${GAMAD_TEST_PG_PASSWORD:-}" \
GAMAD_OIDC_ISSUER="$ISSUER" \
GAMAD_OIDC_AUDIENCE="gamad-admin" \
GAMAD_OIDC_JWKS_URI="$ISSUER/jwks.json" \
GAMAD_ADMIN_PERMISSIONS_JSON='{"GAM-PER-000001":["core.runtime.health.read"]}' \
php -S "127.0.0.1:${API_PORT}" -t public public/index.php >/tmp/gamad-admin-api.log 2>&1 &
API_PID=$!

sleep 2
TOKEN=$(curl -fsS -X POST -d 'sub=GAM-PER-000001&scope=core.runtime.health.read' "$ISSUER/token" | php -r '$v=json_decode(stream_get_contents(STDIN), true); echo $v["access_token"] ?? "";')
[ -n "$TOKEN" ]

STATUS=$(curl -sS -o /tmp/gamad-health.json -w '%{http_code}' -H "Authorization: Bearer $TOKEN" "http://127.0.0.1:${API_PORT}/admin/runtime/health")
[ "$STATUS" = "200" ]
php -r '$v=json_decode(file_get_contents("/tmp/gamad-health.json"), true); if (!is_array($v) || !array_key_exists("healthy", $v)) exit(1);'

UNAUTH=$(curl -sS -o /dev/null -w '%{http_code}' "http://127.0.0.1:${API_PORT}/admin/runtime/health")
[ "$UNAUTH" = "401" ]

printf '%s\n' '{"smoke_test":"passed"}'
