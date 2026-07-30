#!/usr/bin/env bash

set -Eeuo pipefail

url="${GAMAD_READINESS_URL:-https://console.dgafrique.com/api/v1/health/ready}"

for tentative in 1 2 3; do
    if curl \
        --fail \
        --silent \
        --show-error \
        --max-time 10 \
        "$url" >/dev/null; then
        echo "Readiness GAMAD Core : PRET."
        exit 0
    fi

    if [[ "$tentative" -lt 3 ]]; then
        sleep 5
    fi
done

echo "Readiness GAMAD Core : trois échecs consécutifs." >&2
exit 1
