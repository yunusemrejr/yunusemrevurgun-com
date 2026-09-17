#!/usr/bin/env bash
# Rebuild the decorative Hampton the Hampster cameo used on a few pages.
#
# Source: the 2001 hamsterdance.com "hampton.gif" as archived by the Wayback
# Machine (117x138, 11 frames, 300 ms, looping, flat white background).
#
# What this does, in order:
#   1. fetch the archived original into dev/_work/ (ignored, never deployed)
#   2. decode every frame to a full-size PNG (ffmpeg composites the partial
#      frames and their disposal for us)
#   3. clear only the white pixels connected to the frame border, so the
#      character's own white fur stays opaque (a blanket "make white
#      transparent" punches holes through its belly and face)
#   4. halve the size with nearest-neighbour sampling to keep the pixel art
#      crisp, then assemble the GIF with -dispose background. That disposal is
#      required: with the default disposal, transparent pixels do not overwrite
#      the previous frame and the poses accumulate into smears.
#   5. write assets/images/hampton.gif plus a still frame for visitors who
#      prefer reduced motion, and print the resulting frame count and sizes.
#
# Needs: curl, ffmpeg, ImageMagick 7 (magick), python3 with Pillow. Network
# access to web.archive.org is required; nothing else is touched.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
WORK="$REPO_ROOT/dev/_work/hampton-cameo"
ORIGIN_URL="https://web.archive.org/web/20011227103945if_/http://hamsterdance.com:80/gif/hampton.gif"
OUT_GIF="$REPO_ROOT/assets/images/hampton.gif"
OUT_STILL="$REPO_ROOT/assets/images/hampton-still.png"

for bin in curl ffmpeg magick python3; do
    command -v "$bin" >/dev/null || { echo "missing dependency: $bin" >&2; exit 1; }
done
python3 -c 'import PIL' 2>/dev/null || { echo "missing dependency: python3 Pillow" >&2; exit 1; }

mkdir -p "$WORK/full" "$WORK/proc" "$WORK/half"
rm -f "$WORK"/full/*.png "$WORK"/proc/*.png "$WORK"/half/*.png

echo "1/5 fetching the archived original"
curl -fsS -L --max-time 60 -o "$WORK/original.gif" "$ORIGIN_URL"
magick identify -format '  original: %wx%h frames=%n\n' "$WORK/original.gif" | head -1

echo "2/5 decoding frames"
ffmpeg -loglevel error -y -i "$WORK/original.gif" -vsync 0 "$WORK/full/f_%02d.png"

echo "3/5 removing the background (border-connected white only)"
WORK="$WORK" python3 - <<'PY'
import collections
import glob
import os

from PIL import Image

work = os.environ["WORK"]
FUZZ = 8  # per-channel tolerance around pure white


def near_white(r: int, g: int, b: int) -> bool:
    return r >= 255 - FUZZ and g >= 255 - FUZZ and b >= 255 - FUZZ


for path in sorted(glob.glob(os.path.join(work, "full", "f_*.png"))):
    img = Image.open(path).convert("RGBA")
    w, h = img.size
    px = img.load()
    visited = bytearray(w * h)
    queue: collections.deque[tuple[int, int]] = collections.deque()

    def push(x: int, y: int) -> None:
        idx = y * w + x
        if visited[idx] or not near_white(*px[x, y][:3]):
            return
        visited[idx] = 1
        queue.append((x, y))

    for x in range(w):
        push(x, 0)
        push(x, h - 1)
    for y in range(h):
        push(0, y)
        push(w - 1, y)

    while queue:
        x, y = queue.popleft()
        # Keep the original (white) RGB and clear only alpha: any colour bleed
        # while resampling stays light instead of turning into a dark rim.
        px[x, y] = (*px[x, y][:3], 0)
        for dx, dy in ((1, 0), (-1, 0), (0, 1), (0, -1)):
            nx, ny = x + dx, y + dy
            if 0 <= nx < w and 0 <= ny < h:
                push(nx, ny)

    img.save(os.path.join(work, "proc", os.path.basename(path)))
    img.resize((w // 2, h // 2), Image.NEAREST).save(
        os.path.join(work, "half", os.path.basename(path))
    )
print(f"  background removed on {len(glob.glob(os.path.join(work, 'half', 'f_*.png')))} frames")
PY

echo "4/5 assembling the cameo"
# -dispose is an ImageMagick *setting*: it must be given before the input frames,
# otherwise the writer falls back to disposal "none", transparent pixels stop
# overwriting the previous frame, and the poses smear into each other.
magick -delay 30 -loop 0 -dispose background "$WORK"/half/f_*.png "$OUT_GIF"
magick "$WORK/half/f_01.png" -strip -colors 64 "PNG8:$OUT_STILL"

echo "5/5 verifying"
magick identify -format '  shipped: %wx%h frames=%n cols=%k\n' "$OUT_GIF" | head -1
python3 - "$OUT_GIF" "$WORK" <<'PY'
import glob
import sys

from PIL import Image

gif, work = sys.argv[1], sys.argv[2]
still = Image.open(gif)
frames = still.n_frames
problems = []
if frames < 2:
    problems.append("not animated")
if still.info.get("duration") != 300:
    problems.append(f"unexpected frame delay {still.info.get('duration')}")
if still.info.get("loop") != 0:
    problems.append("not looping")
expected = sorted(glob.glob(work + "/half/f_*.png"))
if len(expected) != frames:
    problems.append(f"{frames} frames shipped, {len(expected)} expected")

# Round-trip the shipped file through a second, stricter decoder (ffmpeg) and
# require the alpha masks to survive exactly.
import subprocess
import tempfile

with tempfile.TemporaryDirectory() as tmp:
    subprocess.run(
        ["ffmpeg", "-loglevel", "error", "-y", "-i", gif, "-vsync", "0", tmp + "/f_%02d.png"],
        check=True,
    )
    decoded = sorted(glob.glob(tmp + "/f_*.png"))
    if len(decoded) != len(expected):
        problems.append(f"decoder returned {len(decoded)} frames")
    for src, dec in zip(expected, decoded):
        a = Image.open(src).convert("RGBA").getchannel("A").point(lambda v: 255 if v > 127 else 0)
        b = Image.open(dec).convert("RGBA").getchannel("A").point(lambda v: 255 if v > 127 else 0)
        if a.tobytes() != b.tobytes():
            problems.append("transparency changed on decode: " + dec.split("/")[-1])

if problems:
    print("  FAILED: " + "; ".join(problems), file=sys.stderr)
    sys.exit(1)
print(f"  OK: {frames} frames, transparent background, looping, decodes per frame")
PY

ls -l "$OUT_GIF" "$OUT_STILL"
echo "done"
