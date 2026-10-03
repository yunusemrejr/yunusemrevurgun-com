#!/usr/bin/env python3
"""Rebuild every raster brand asset from the mascot source render.

Source:  assets/images/brand/mascot-source.png   (jelly + cap on flat #69B0F9)
Outputs: assets/images/mascot.png|.webp          transparent cutout, 800px
         assets/images/mascot-160.png|.webp      navbar / avatar size
         assets/images/mascot-320|480|640.webp   responsive steps for the homepage srcset
         assets/images/favicon.svg               transparent, embeds a 96px cutout
         assets/images/favicon-32.png, /favicon.ico (16/32/48, transparent)
         assets/images/apple-touch-icon.png      180px on the brand blue (iOS fills alpha black)
         assets/images/pwa-icon-192|512.png      transparent, purpose "any"
         assets/images/pwa-maskable-512.png      brand blue, mascot inside the 80% safe zone
The Open Graph card is built separately by build-og-image.sh.
Needs Pillow, numpy and scipy. Dev-only.
"""
import base64, io, pathlib
import numpy as np
from PIL import Image
from scipy import ndimage as ndi

ROOT = pathlib.Path(__file__).resolve().parents[2]
IMG = ROOT / "assets" / "images"
SRC = IMG / "brand" / "mascot-source.png"
SKY = (105, 176, 249)  # sampled from the source background: #69B0F9


def matte(im: Image.Image) -> Image.Image:
    """Key the flat blue field out. The mascot has no blue in it, so the
    background is the border-connected region near SKY; a 3px band along the
    edge gets a partial alpha and has the blue spill removed from its colour."""
    p = np.asarray(im.convert("RGB")).astype(float)
    bg = np.array(SKY, float)
    d = np.sqrt(((p - bg) ** 2).sum(2))
    lab, _ = ndi.label(d < 26)
    edge = set(np.unique(np.concatenate([lab[0], lab[-1], lab[:, 0], lab[:, -1]]))) - {0}
    bgmask = np.isin(lab, list(edge))
    dist = ndi.distance_transform_edt(~bgmask)
    alpha = np.where(bgmask, 0.0, 1.0)
    band = (~bgmask) & (dist <= 3)
    inner = (~bgmask) & (dist > 3)
    idx = ndi.distance_transform_edt(~inner, return_distances=False, return_indices=True)
    fg = p[idx[0], idx[1]]
    a = np.clip(((p - bg) * (fg - bg)).sum(2) / (((fg - bg) ** 2).sum(2) + 1e-6), 0, 1)
    alpha[band] = a[band]
    out = p.copy()
    m = band & (alpha > 0.02)
    out[m] = np.clip((p[m] - (1 - alpha[m, None]) * bg) / alpha[m, None], 0, 255)
    return Image.fromarray(np.dstack([out, alpha * 255]).astype(np.uint8), "RGBA")


def square_crop(im: Image.Image, pad: float) -> Image.Image:
    box = im.getchannel("A").point(lambda v: 255 if v > 8 else 0).getbbox()
    w, h = box[2] - box[0], box[3] - box[1]
    side = int(max(w, h) * (1 + pad))
    cx, cy = (box[0] + box[2]) // 2, (box[1] + box[3]) // 2
    canvas = Image.new("RGBA", (side, side), (0, 0, 0, 0))
    canvas.paste(im.crop(box), (side // 2 - w // 2, side // 2 - h // 2))
    return canvas


def sized(im, n):
    return im.resize((n, n), Image.LANCZOS)


def on_sky(mascot, size, scale):
    canvas = Image.new("RGBA", (size, size), SKY + (255,))
    m = sized(mascot, int(size * scale))
    canvas.alpha_composite(m, ((size - m.width) // 2, (size - m.height) // 2 + int(size * 0.02)))
    return canvas


def main():
    cut = matte(Image.open(SRC))
    tight = square_crop(cut, 0.0)   # touching the bbox: favicons want every pixel
    roomy = square_crop(cut, 0.06)  # the general-purpose cutout

    for name, n in (("mascot", 800), ("mascot-160", 160)):
        m = sized(roomy, n)
        m.save(IMG / f"{name}.png", optimize=True)
        m.save(IMG / f"{name}.webp", quality=90, method=6)

    # Responsive steps for the homepage's masthead image (srcset): it is shown at
    # most ~320 CSS px wide on phones, so the 800px file is only needed on dense
    # or wide screens. WebP only; the page falls back to mascot.webp.
    for n in (320, 480, 640):
        sized(roomy, n).save(IMG / f"mascot-{n}.webp", quality=90, method=6)

    f96 = sized(tight, 96)
    buf = io.BytesIO(); f96.save(buf, "PNG", optimize=True)
    b64 = base64.b64encode(buf.getvalue()).decode()
    (IMG / "favicon.svg").write_text(
        '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" '
        'viewBox="0 0 96 96" width="96" height="96"><title>Yemre</title>'
        f'<image width="96" height="96" href="data:image/png;base64,{b64}" '
        f'xlink:href="data:image/png;base64,{b64}"/></svg>\n')
    sized(tight, 32).save(IMG / "favicon-32.png", optimize=True)
    tight_big = sized(tight, 256)
    tight_big.save(IMG / "favicon.ico", sizes=[(16, 16), (32, 32), (48, 48)])

    on_sky(roomy, 180, 0.82).convert("RGB").save(IMG / "apple-touch-icon.png", optimize=True)
    for n in (192, 512):
        sized(roomy, n).save(IMG / f"pwa-icon-{n}.png", optimize=True)
    on_sky(roomy, 512, 0.62).convert("RGB").save(IMG / "pwa-maskable-512.png", optimize=True)
    print("brand assets written to", IMG)


if __name__ == "__main__":
    main()
