#!/usr/bin/env bash
# build-og-image.sh — regenerate the social-share card (assets/images/og-image.png)
# from the home hero SVG (assets/images/desktop.svg).
#
# Social platforms (X/Twitter, LinkedIn, Facebook, etc.) do NOT render SVG for
# og:image / twitter:image — they need a raster. This renders the 16:9 home hero
# into a 1200x630 (1.91:1) PNG on the same cream background, so the whole hero
# shows with seamless margins. Re-run this whenever desktop.svg changes.
#
# Requires: google-chrome (headless) + ImageMagick `identify` (for the size check).
# Dev-only; never executed in production.
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SVG="$ROOT/assets/images/desktop.svg"
OUT="$ROOT/assets/images/og-image.png"
TEMPLATE="$ROOT/dev/scripts/og-card-template.html"

for bin in google-chrome identify; do
  command -v "$bin" >/dev/null 2>&1 || { echo "ERROR: required tool '$bin' not found" >&2; exit 1; }
done
[ -f "$SVG" ]      || { echo "ERROR: hero SVG not found: $SVG" >&2; exit 1; }
[ -f "$TEMPLATE" ] || { echo "ERROR: template not found: $TEMPLATE" >&2; exit 1; }

HTML="$(mktemp /tmp/og-card-XXXXXX.html)"
PROF="$(mktemp -d /tmp/og-chrome-XXXXXX)"
sed "s#SVG_PATH_PLACEHOLDER#file://$SVG#" "$TEMPLATE" > "$HTML"

google-chrome --headless=new --no-sandbox --disable-gpu --hide-scrollbars \
  --window-size=1200,630 --user-data-dir="$PROF" --virtual-time-budget=8000 \
  --screenshot="$OUT" "file://$HTML" >/dev/null 2>&1

[ -f "$OUT" ] || { echo "ERROR: chrome produced no output" >&2; exit 1; }

DIM="$(identify -format '%wx%h' "$OUT" 2>/dev/null)"
if [ "$DIM" != "1200x630" ]; then
  echo "ERROR: expected 1200x630, got $DIM" >&2; exit 1
fi

echo "OK: wrote $OUT ($DIM, $(du -h "$OUT" | cut -f1))"
