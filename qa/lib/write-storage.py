#!/usr/bin/env python3
"""Converts a curl cookie jar into a Playwright storage-state file.

curl's Netscape format is not the format Playwright loads, and the differences
are both easy to miss:

  * curl marks an httpOnly cookie by prefixing the whole line with
    "#HttpOnly_". A parser that skips comment lines silently drops the session
    cookie, which looks exactly like a failed login.

  * The theme lives in localStorage rather than a cookie, so it cannot be
    forced with a header. Storage state carries an `origins` array for this, and
    use-appearance.tsx reads it on mount, which `--js` (running after mount)
    could not do.
"""

from __future__ import annotations

import json
import sys


def read_jar(path: str) -> list[dict]:
    cookies: list[dict] = []

    with open(path) as handle:
        for raw in handle:
            line = raw.rstrip("\n")
            if not line.strip():
                continue

            http_only = line.startswith("#HttpOnly_")
            if line.startswith("#") and not http_only:
                continue
            if http_only:
                line = line[len("#HttpOnly_") :]

            fields = line.split("\t")
            if len(fields) != 7:
                continue

            domain, _subdomains, path, secure, expiry, name, value = fields
            cookies.append(
                {
                    "name": name,
                    "value": value,
                    "domain": domain,
                    "path": path,
                    "httpOnly": http_only,
                    "secure": secure.upper() == "TRUE",
                    "sameSite": "Lax",
                    "expires": int(float(expiry)) if float(expiry) > 0 else -1,
                }
            )

    return cookies


def main() -> int:
    if len(sys.argv) != 5:
        print(
            "usage: write-storage.py <jar> <origin> <appearance> <output>",
            file=sys.stderr,
        )
        return 2

    jar, origin, appearance, output = sys.argv[1:]
    cookies = read_jar(jar)

    if not any(cookie["name"].endswith("session") for cookie in cookies):
        print("no session cookie in the jar: login did not persist", file=sys.stderr)
        return 1

    with open(output, "w") as handle:
        json.dump(
            {
                "cookies": cookies,
                "origins": [
                    {
                        "origin": origin,
                        "localStorage": [{"name": "appearance", "value": appearance}],
                    }
                ],
            },
            handle,
            indent=4,
        )
        handle.write("\n")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())