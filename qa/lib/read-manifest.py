#!/usr/bin/env python3
"""Reads a qa capture manifest into widths and shots.

The manifest is a flat document: a `widths` list and a `shots` list of
name/url pairs. That is deliberately not general YAML — the alternative is a
YAML dependency in a directory whose whole purpose is to avoid dependencies, and
the shape is fixed by the two files in this directory.

Usage: read-manifest.py <manifest.yml> <base_url>
Prints JSON: {"widths": [...], "shots": [{"name", "url"}, ...]}
"""

from __future__ import annotations

import json
import sys


def parse(path: str) -> dict:
    widths: list[int] = []
    shots: list[dict] = []
    section: str | None = None

    with open(path) as handle:
        for raw in handle:
            line = raw.rstrip("\n")

            stripped = line.strip()
            if not stripped or stripped.startswith("#"):
                continue

            is_item = stripped.startswith("- ")
            body = stripped.lstrip("- ").strip()

            key, sep, value = body.partition(":")
            key, value = key.strip(), value.strip()

            if not is_item and key in ("widths", "shots"):
                section = key
                continue

            # A width is a bare scalar (`- 1440`), while a shot opens with a
            # `- name:` item and continues with plain `url:` lines, so a value
            # is split on a colon only when there is one.
            if section == "widths":
                widths.append(int(value if sep else body))
            elif section == "shots":
                if key == "name":
                    shots.append({"name": value})
                elif key == "url" and shots:
                    shots[-1]["url"] = value

    return {"widths": widths, "shots": shots}


def main() -> int:
    if len(sys.argv) != 3:
        print("usage: read-manifest.py <manifest.yml> <base_url>", file=sys.stderr)
        return 2

    manifest, base_url = sys.argv[1], sys.argv[2]
    parsed = parse(manifest)

    if not parsed["widths"]:
        print(f"{manifest}: no widths", file=sys.stderr)
        return 1
    for shot in parsed["shots"]:
        if "url" not in shot:
            print(f"{manifest}: shot {shot['name']} has no url", file=sys.stderr)
            return 1

    parsed["base_url"] = base_url
    print(json.dumps(parsed))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())