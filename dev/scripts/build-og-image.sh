#!/usr/bin/env bash
# build-og-image.sh — regenerate the social-share card (assets/images/og-image.png).
#
# Social platforms (X/Twitter, LinkedIn, Facebook, etc.) do not render SVG and
# prefer a 1.91:1 raster. This composes the landing photograph with the site
# wordmark on the v4 palette at 1200x630, so a shared link previews like the
# site it points to. Re-run this whenever the hero photograph or the palette
# tokens in assets/css/variables.css change.
#
# Requires: google-chrome (headless) + ImageMagick `identify` (for the size check).
# Dev-only; never executed in production.
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PHOTO="$ROOT/assets/images/landing-hero-desk.png"   # full-resolution original
FONTS="$ROOT/assets/fonts"
OUT="$ROOT/assets/images/og-image.png"
TEMPLATE="$ROOT/dev/scripts/og-card-template.html"

for bin in google-chrome identify; do
  command -v "$bin" >/dev/null 2>&1 || { echo "ERROR: required tool '$bin' not found" >&2; exit 1; }
done
[ -f "$PHOTO" ]    || { echo "ERROR: hero photograph not found: $PHOTO" >&2; exit 1; }
[ -f "$TEMPLATE" ] || { echo "ERROR: template not found: $TEMPLATE" >&2; exit 1; }

HTML="$(mktemp /tmp/og-card-XXXXXX.html)"
PROF="$(mktemp -d /tmp/og-chrome-XXXXXX)"
sed -e "s#PHOTO_PATH_PLACEHOLDER#file://$PHOTO#" \
    -e "s#FONT_DIR_PLACEHOLDER#file://$FONTS#" "$TEMPLATE" > "$HTML"

google-chrome --headless=new --no-sandbox --disable-gpu --hide-scrollbars \
  --window-size=1200,630 --user-data-dir="$PROF" --virtual-time-budget=8000 \
  --screenshot="$OUT" "file://$HTML" >/dev/null 2>&1

[ -f "$OUT" ] || { echo "ERROR: chrome produced no output" >&2; exit 1; }

DIM="$(identify -format '%wx%h' "$OUT" 2>/dev/null)"
if [ "$DIM" != "1200x630" ]; then
  echo "ERROR: expected 1200x630, got $DIM" >&2; exit 1
fi

echo "OK: wrote $OUT ($DIM, $(du -h "$OUT" | cut -f1))"
