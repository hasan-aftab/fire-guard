#!/usr/bin/env bash
#
# Logs in over HTTP and leaves the resulting cookie jar at the given path.
#
# Both auth flows (the demo user and the throwaway empty one) need this, and it
# has two traps worth naming:
#
#   * The login page is an Inertia React form, so there is no `_token` hidden
#     input to scrape. Laravel accepts the encrypted XSRF-TOKEN cookie back in
#     the X-XSRF-TOKEN header, which is what this does.
#
#   * The session cookie is encrypted, so it cannot be minted by inserting a
#     sessions row: the app decrypts the cookie to find that row, and a
#     hand-written value simply fails to decrypt into anything. Logging in for
#     real is the only way to get a cookie the app will accept.
#
# Usage: qa/lib/login.sh <email> <password> <jar>

set -euo pipefail

cd "$(dirname "$0")/../.."

BASE_URL="${QA_BASE_URL:-http://127.0.0.1:8000}"
EMAIL="$1"
PASSWORD="$2"
JAR="$3"

curl -s -c "$JAR" -o /dev/null "$BASE_URL/login"

XSRF="$(grep XSRF-TOKEN "$JAR" | awk '{print $7}')"
if [ -z "$XSRF" ]; then
    echo "no XSRF-TOKEN cookie: is the app running at $BASE_URL?" >&2
    exit 1
fi

XSRF="$(python3 -c 'import urllib.parse,sys; print(urllib.parse.unquote(sys.argv[1]))' "$XSRF")"

STATUS="$(curl -s -b "$JAR" -c "$JAR" -o /dev/null -w '%{http_code}' \
    -X POST "$BASE_URL/login" \
    -H "X-XSRF-TOKEN: $XSRF" \
    -H 'Content-Type: application/json' \
    --data "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}")"

if [ "$STATUS" != "302" ]; then
    echo "login returned $STATUS, expected 302 — check the email and password" >&2
    exit 1
fi