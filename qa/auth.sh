#!/usr/bin/env bash
#
# Signs in as the demo user and writes one Playwright storage-state file per
# theme, so screenshots capture a real dashboard rather than the login page.
#
# Usage: qa/auth.sh [email] [password]

set -euo pipefail

cd "$(dirname "$0")/.."

BASE_URL="${QA_BASE_URL:-http://127.0.0.1:8000}"
EMAIL="${1:-${QA_EMAIL:-hasanaftab007@gmail.com}}"
PASSWORD="${2:-${QA_PASSWORD:-$EMAIL}}"
JAR="$(mktemp)"
trap 'rm -f "$JAR"' EXIT

./qa/lib/login.sh "$EMAIL" "$PASSWORD" "$JAR"

for theme in light dark; do
    python3 qa/lib/write-storage.py "$JAR" "$BASE_URL" "$theme" "qa/auth-${theme}.json"
done

echo "wrote qa/auth-light.json and qa/auth-dark.json"