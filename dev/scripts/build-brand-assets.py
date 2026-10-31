#!/usr/bin/env python3
"""Rebuild every brand mark and icon from one drawing: the trees in the owner's photograph.

The mark is the pine stand with its gold birch, traced from the portrait (assets/images/field/stand.svg,
built by build-field-assets.py; run that first), cropped to the birch and the pines beside it, on a fog
plate with a pine ground strip and a pine rim. Colours are the
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


STAND = ROOT / "assets" / "images" / "field" / "stand.svg"
CROP = (30, 10, 480, 480)  # x, y, w, h in stand.svg units: the gold birch and the pines either side


def _stand_paths():
    """The pine-stand silhouette traced from the owner's portrait (build-field-assets.py)."""
    import re
    svg = STAND.read_text()
    return re.findall(r"<path [^>]*/>", svg)


def mark(size=64, plate=True, rx=13, ground=True, inset=0.0):
    """The trees from the photograph on a fog plate, a pine ground strip under them.
    `inset` shrinks the drawing toward the centre (maskable icon)."""
    x, y, w, h = CROP
    k = 1 - inset
    pad = (4 if plate else 0) + 28 * inset
    clip = f'<rect x="1" y="1" width="62" height="62" rx="{rx}"/>' if plate else '<rect width="64" height="64"/>'
    body = [f'<defs><clipPath id="pl">{clip}</clipPath></defs>']
    body.append(f'<g clip-path="url(#pl)">')
    body.append(f'<rect width="64" height="64" fill="{FOG}"/>')
    body.append(
        f'<svg x="{pad:.2f}" y="{pad + 2:.2f}" width="{64 - 2 * pad:.2f}" height="{64 - 2 * pad:.2f}" '
        f'viewBox="{x} {y} {w} {h}" preserveAspectRatio="xMidYMax meet">' + "".join(_stand_paths()) + "</svg>"
    )
    if ground:
        body.append(f'<rect x="0" y="{64 - pad - 0.5:.2f}" width="64" height="{pad + 1:.2f}" fill="{PINE}"/>')
    body.append("</g>")
    if plate:
        body.append(f'<rect x="1.5" y="1.5" width="61" height="61" rx="{rx - 0.5}" fill="none" stroke="{PINE}" stroke-width="3"/>')
    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" width="{size}" height="{size}" viewBox="0 0 64 64">'
        + "".join(body)
        + "</svg>\n"
    )


def full_bleed(size, inset):
    """Square field (no rounded corners): apple-touch and maskable icons."""
    svg = mark(64, plate=False, inset=inset)
    return svg.replace(
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
    render(full_bleed(64, 0.0), 180).convert("RGB").save(IMG / "apple-touch-icon.png", optimize=True)
    print("brand mark and icons written to", IMG)


if __name__ == "__main__":
    main()
