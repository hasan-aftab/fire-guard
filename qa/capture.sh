#!/usr/bin/env bash
#
# Captures the dashboard matrix in qa/dashboard-*.yml.
#
# The YAML is the spec of what to capture and why; this runs it. shot-scraper
# multi cannot set a viewport per shot, and every state has to be seen at every
# width, so each entry becomes its own `shot-scraper shot` call.
#
# Usage: qa/capture.sh [light|dark|all]

set -euo pipefail

cd "$(dirname "$0")/.."

BASE_URL="${QA_BASE_URL:-http://127.0.0.1:8000}"
WHICH="${1:-all}"

./qa/auth.sh >/dev/null

capture_theme() {
    local theme="$1"
    local manifest="qa/dashboard-${theme}.yml"

    python3 - "$manifest" "$BASE_URL" "$theme" <<'PY'
import json
import subprocess
import sys

manifest, base_url, theme = sys.argv[1], sys.argv[2], sys.argv[3]

plan = json.loads(
    subprocess.run(
        ["python3", "qa/lib/read-manifest.py", manifest, base_url],
        capture_output=True, text=True, check=True,
    ).stdout
)

failures = []
for shot in plan["shots"]:
    for width in plan["widths"]:
        target = f"qa/{shot['name']}-{width}.png"
        result = subprocess.run(
            [
                "shot-scraper", "shot", base_url + shot["url"],
                "--auth", f"qa/auth-{theme}.json",
                "--width", str(width),
                "--height", "900",
                # Wait for the agenda to have rendered, not just for `load`:
                # the rows arrive with the Inertia payload, and a screenshot
                # taken at `load` catches an empty card.
                "--wait-for", "document.querySelectorAll('h3').length > 0",
                "-o", target,
            ],
            capture_output=True,
            text=True,
        )
        if result.returncode != 0:
            tail = result.stderr.strip().splitlines()[-1:] or ["unknown error"]
            failures.append((target, tail[0]))
            print(f"FAIL {target}")
        else:
            print(f"ok   {target}")

if failures:
    print(f"\n{len(failures)} capture(s) failed", file=sys.stderr)
    for target, err in failures:
        print(f"  {target}: {err}", file=sys.stderr)
    raise SystemExit(1)
PY
}

case "$WHICH" in
    light | dark) capture_theme "$WHICH" ;;
    all) capture_theme light; capture_theme dark ;;
    *) echo "usage: qa/capture.sh [light|dark|all]" >&2; exit 2 ;;
esac

echo
echo "screenshots written to qa/ (not committed)"