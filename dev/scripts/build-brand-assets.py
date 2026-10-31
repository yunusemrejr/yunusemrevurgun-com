#!/usr/bin/env python3
"""Rebuild every brand mark and icon from one drawing: the forked Y.

The mark is the letter Y drawn as a tree forking: a trunk that splits into two branches, the way a
birch forks, and the way a binary tree or a decision tree does. One branch ends in a birch-gold node,
the other in a stone one. It sits on a pine plate with a ground line under the trunk. Colours are the
Field tokens in assets/css/variables.css (pine #1c2b20, stone #eef0ec, birch #e3a227, fog #a9c3dc).

Outputs (assets/images/):
  brand/logo-mark.svg       the plate mark (navbar, admin, error pages, YunoBot)
  favicon.svg               same drawing, tuned for 16-32 px
  favicon-32.png, favicon.ico (16/32/48)
  apple-touch-icon.png      180 px, full-bleed plate (iOS fills alpha black)
  pwa-icon-192|512.png      transparent corners, purpose "any"
  pwa-maskable-512.png      pine field, mark inside the 80 % safe zone
The Open Graph cards are built separately by build-og-image.sh.
Needs google-chrome (headless) and Pillow. Dev-only. Run: python3 dev/scripts/build-brand-assets.py
"""
import io
import pathlib
import subprocess
import tempfile

from PIL import Image

ROOT = pathlib.Path(__file__).resolve().parents[2]
IMG = ROOT / "assets" / "images"
(IMG / "brand").mkdir(parents=True, exist_ok=True)

PINE = "#1c2b20"
PINE_EDGE = "#2c4232"
STONE = "#eef0ec"
BIRCH = "#e3a227"
FOG = "#a9c3dc"


def mark(size=64, plate=True, rx=13, ground=True, inset=0.0):
    """The drawing in a 64-unit box. `inset` shrinks the drawing toward the centre (maskable icon)."""
    k = 1 - inset
    t = f'translate({32 - 32 * k:.2f} {32 - 32 * k:.2f}) scale({k:.4f})'
    body = []
    if plate:
        body.append(f'<rect x="1" y="1" width="62" height="62" rx="{rx}" fill="{PINE}"/>')
        body.append(f'<rect x="2.5" y="2.5" width="59" height="59" rx="{rx - 1.5}" fill="none" stroke="{PINE_EDGE}" stroke-width="1"/>')
    g = []
    if ground:
        g.append(f'<path d="M15 52.5H49" stroke="{FOG}" stroke-width="2.4" stroke-linecap="round" opacity=".9"/>')
        g.append(f'<path d="M21 52.5v3.2M43 52.5v3.2" stroke="{FOG}" stroke-width="1.6" stroke-linecap="round" opacity=".75"/>')
    # trunk and the two branches, slightly curved like a real fork
    g.append(
        f'<path d="M32 52V35.5C32 29.5 27.5 24.5 20.5 17.5M32 35.5C32 29.5 36.5 24.5 43.5 17.5" '
        f'fill="none" stroke="{STONE}" stroke-width="6.4" stroke-linecap="round" stroke-linejoin="round"/>'
    )
    # nodes
    g.append(f'<circle cx="44.6" cy="16.4" r="5.6" fill="{BIRCH}"/>')
    g.append(f'<circle cx="19.4" cy="16.4" r="4" fill="{STONE}"/>')
    body.append(f'<g transform="{t}">' + "".join(g) + "</g>")
    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" width="{size}" height="{size}" viewBox="0 0 64 64">'
        + "".join(body)
        + "</svg>\n"
    )


def full_bleed(size, inset):
    """Square pine field (no rounded corners): apple-touch and maskable icons."""
    svg = mark(64, plate=False, inset=inset)
    return svg.replace('viewBox="0 0 64 64">', f'viewBox="0 0 64 64"><rect width="64" height="64" fill="{PINE}"/>', 1).replace(
        'width="64" height="64" viewBox', f'width="{size}" height="{size}" viewBox', 1
    )


def render(svg: str, size: int) -> Image.Image:
    with tempfile.TemporaryDirectory() as tmp:
        tmp = pathlib.Path(tmp)
        page = tmp / "m.html"
        page.write_text(
            "<!doctype html><html><body style='margin:0;background:transparent'>"
            f"<img src='data:image/svg+xml;utf8,{svg.replace('#', '%23').strip()}' width='{size}' height='{size}' style='display:block'>"
            "</body></html>"
        )
        out = tmp / "o.png"
        subprocess.run(
            [
                "google-chrome", "--headless=new", "--disable-gpu", "--hide-scrollbars",
                "--default-background-color=00000000", f"--window-size={size},{size}",
                f"--user-data-dir={tmp / 'prof'}", f"--screenshot={out}", f"file://{page}",
            ],
            check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
        )
        return Image.open(out).convert("RGBA").crop((0, 0, size, size))


def main():
    logo = mark(64)
    (IMG / "brand" / "logo-mark.svg").write_text(logo)
    (IMG / "favicon.svg").write_text(mark(64, rx=14))

    big = render(mark(64), 512)
    big.save(IMG / "pwa-icon-512.png", optimize=True)
    big.resize((192, 192), Image.LANCZOS).save(IMG / "pwa-icon-192.png", optimize=True)
    fav = render(mark(64), 256)
    fav.resize((32, 32), Image.LANCZOS).save(IMG / "favicon-32.png", optimize=True)
    fav.save(IMG / "favicon.ico", sizes=[(16, 16), (32, 32), (48, 48)])
    render(full_bleed(64, 0.16), 512).convert("RGB").save(IMG / "pwa-maskable-512.png", optimize=True)
    render(full_bleed(64, 0.06), 180).convert("RGB").save(IMG / "apple-touch-icon.png", optimize=True)
    print("brand mark and icons written to", IMG)


if __name__ == "__main__":
    main()
