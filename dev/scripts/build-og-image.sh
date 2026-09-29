#!/usr/bin/env bash
# build-og-image.sh — regenerate the social-share cards.
#
# Social platforms (X/Twitter, LinkedIn, Facebook, etc.) do not render SVG and
# prefer a 1.91:1 raster. This composes the mascot with the site name on the
# brand palette at 1200x630, so a shared link previews like the site it points
# to. Cards built here:
#   assets/images/og-image.png     default card for every page
#   assets/images/og-chessko.png   /chessko and its articles
# Re-run after the mascot (dev/scripts/build-brand-assets.py) or the palette
# tokens in assets/css/variables.css change.
#
# Requires: google-chrome (headless) + ImageMagick `identify` (for the size check).
# Dev-only; never executed in production.
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
MASCOT="$ROOT/assets/images/mascot.png"
FONTS="$ROOT/assets/fonts"
TEMPLATE="$ROOT/dev/scripts/og-card-template.html"

for bin in google-chrome identify; do
  command -v "$bin" >/dev/null 2>&1 || { echo "ERROR: required tool '$bin' not found" >&2; exit 1; }
done
[ -f "$MASCOT" ]   || { echo "ERROR: mascot not found: $MASCOT (run build-brand-assets.py)" >&2; exit 1; }
[ -f "$TEMPLATE" ] || { echo "ERROR: template not found: $TEMPLATE" >&2; exit 1; }

# out-file | label | title | title px | subtitle
CARDS=(
  "assets/images/og-image.png|Yemre|Yunus Emre Vurgun|92|AI/ML systems and industrial automation, built and documented in Istanbul."
  "assets/images/og-chessko.png|Chessko|Jelly chess in your browser|74|Seven levels, up to Stockfish 19 in WebAssembly. No sign-up."
)

build_card() {
  local out="$ROOT/$1" label="$2" title="$3" size="$4" sub="$5"
  local html prof
  html="$(mktemp /tmp/og-card-XXXXXX.html)"
  prof="$(mktemp -d /tmp/og-chrome-XXXXXX)"
  sed -e "s#MASCOT_PATH_PLACEHOLDER#file://$MASCOT#" \
      -e "s#FONT_DIR_PLACEHOLDER#file://$FONTS#g" \
      -e "s#TITLE_SIZE_PLACEHOLDER#$size#" \
      -e "s#LABEL_PLACEHOLDER#$label#" \
      -e "s#TITLE_PLACEHOLDER#$title#" \
      -e "s#SUB_PLACEHOLDER#$sub#" "$TEMPLATE" > "$html"
  google-chrome --headless=new --no-sandbox --disable-gpu --hide-scrollbars \
    --window-size=1200,630 --user-data-dir="$prof" --virtual-time-budget=8000 \
    --screenshot="$out" "file://$html" >/dev/null 2>&1
  rm -rf "$html" "$prof"
  [ -f "$out" ] || { echo "ERROR: chrome produced no output for $1" >&2; return 1; }
  local dim
  dim="$(identify -format '%wx%h' "$out" 2>/dev/null)"
  [ "$dim" = "1200x630" ] || { echo "ERROR: $1 expected 1200x630, got $dim" >&2; return 1; }
  echo "OK: wrote $out ($dim, $(du -h "$out" | cut -f1))"
}

for spec in "${CARDS[@]}"; do
  IFS='|' read -r f l t s p <<<"$spec"
  build_card "$f" "$l" "$t" "$s" "$p" || exit 1
done
