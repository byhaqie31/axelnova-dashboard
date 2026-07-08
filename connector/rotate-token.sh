#!/usr/bin/env bash
#
# One-command rotation of the Worker's CONNECTOR_TOKEN.
#
#   ./rotate-token.sh [days]      # default 30
#   npm run rotate-token          # same, via package.json
#
# Mints a fresh scoped Sanctum token on the VPS (over the `vps` SSH alias),
# verifies it against the live connector API, then pipes it into
# `wrangler secret put` — which restarts the Worker on the new secret. No
# `wrangler deploy` (that would ship whatever code sits in this checkout).
# The token only ever lives in this process's memory and the SSH pipe.
set -euo pipefail

DAYS="${1:-30}"
API_BASE="${API_BASE:-https://axelnovaventures.com}"
VPS_APP_DIR='~/axelnova-dashboard'

cd "$(dirname "$0")"

echo "Minting a fresh ${DAYS}-day mcp-connector token on the VPS..."
TOKEN="$(ssh vps "cd ${VPS_APP_DIR} && docker compose -f docker-compose.prod.yml exec -T backend php artisan connector:token --days=${DAYS} --plain" | tr -d '[:space:]')"

if [ -z "${TOKEN}" ]; then
  echo "error: minting returned no token — run the artisan command on the VPS by hand to see why." >&2
  exit 1
fi

# Prove the new token works BEFORE overwriting the Worker secret, so a bad
# mint can never clobber a working credential.
echo "Verifying the new token against ${API_BASE}..."
if ! curl -fsS -o /dev/null \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Accept: application/json" \
  "${API_BASE}/api/v1/connector/catalog"; then
  echo "error: the freshly minted token was rejected by ${API_BASE} — Worker secret left untouched." >&2
  exit 1
fi

printf '%s' "${TOKEN}" | npx wrangler secret put CONNECTOR_TOKEN
echo "Rotated. The Worker restarted on the new secret — no deploy needed."
