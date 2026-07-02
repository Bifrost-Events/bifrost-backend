#!/usr/bin/env bash
# Read-only HTTP smoke etter deploy. Speilet fra bifrost-public-ui/release/smoke/http-smoke.sh
# Usage: http-smoke.sh <mode> <app_url> <release_id> <ref>

set -euo pipefail

MODE="${1:?mode required (public-ui|backend)}"
APP_URL="${2:?app_url required}"
RELEASE_ID="${3:?release_id required}"
REF="${4:?ref required}"

APP_URL="${APP_URL%/}"

echo "========== Release smoke ($MODE) =========="
echo "APP_URL:    $APP_URL"
echo "ReleaseId:  $RELEASE_ID"
echo "Ref:        $REF"
echo "=========================================="

curl_check() {
  local url="$1"
  local label="$2"
  echo ""
  echo "GET $url"
  local body
  body=$(curl -fsS --connect-timeout 15 --max-time 30 "$url")
  echo "$body" | head -c 800
  echo ""
  echo "✓ $label OK"
}

version_check() {
  local url="$1"
  echo ""
  echo "GET $url"
  local body
  body=$(curl -fsS --connect-timeout 15 --max-time 30 "$url")
  echo "$body" | head -c 800
  echo ""

  command -v jq >/dev/null || (sudo apt-get update -qq && sudo apt-get install -y jq)

  local rid commit
  rid=$(echo "$body" | jq -r '.releaseId // empty')
  commit=$(echo "$body" | jq -r '.commit // empty')

  if [ -z "$rid" ] || [ "$rid" != "$RELEASE_ID" ]; then
    echo "::error::releaseId mismatch: forventet $RELEASE_ID, fikk $rid"
    exit 1
  fi

  if [ -z "$commit" ]; then
    echo "::error::commit mangler i version response"
    exit 1
  fi

  if [[ "$commit" != "$REF"* ]] && [[ "$REF" != "$commit"* ]]; then
    echo "::error::commit mismatch: forventet $REF, fikk $commit"
    exit 1
  fi

  echo "✓ version metadata OK (releaseId + commit)"
}

case "$MODE" in
  public-ui)
    curl_check "$APP_URL/health" "health"
    version_check "$APP_URL/version.json"
    ;;
  backend)
    curl_check "$APP_URL/api/health" "api/health"
    version_check "$APP_URL/api/version"
    ;;
  *)
    echo "::error::Ukjent mode: $MODE"
    exit 1
    ;;
esac

echo ""
echo "Smoke fullført."
