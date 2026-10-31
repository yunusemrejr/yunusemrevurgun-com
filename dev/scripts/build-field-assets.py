#!/usr/bin/env python3
"""Field assets: everything the "Field" skin draws from the owner's two photographs.

Sources (dev/field-src/, not served):
  portrait-pines.jpg   the owner in front of a pine stand with one birch gone gold
  fog-hill.jpg         a hillside lost in fog above a stone-walled lawn

Outputs (assets/images/field/):
  stand.svg            the pine stand's real skyline, traced column by column from the portrait,
                       with the birch crown traced from its gold pixels: the silhouette that
                       stands at the right of every page header
  ridge.svg            the fog photo's hill line, traced the same way, as two receding layers:
                       the strip along the foot of every header sheet
  contours.svg         a seamless survey-sheet tile of contour lines (periodic field, so it repeats
                       without a seam), drawn over the drafting grid
  portrait-738.webp/jpg  the homepage portrait, cropped to 2:3 around the subject and the birch

Colours are literals because SVG used as a CSS background cannot read custom properties; they
match the tokens in assets/css/variables.css (pine #1c2b20, birch #e3a227, fog scale).

Run: python3 dev/scripts/build-field-assets.py   (needs Pillow, numpy, matplotlib; no network)
"""
from pathlib import Path

import numpy as np
from PIL import Image, ImageFilter

ROOT = Path(__file__).resolve().parents[2]
SRC = ROOT / "dev" / "field-src"
OUT = ROOT / "assets" / "images" / "field"
OUT.mkdir(parents=True, exist_ok=True)

PINE = "#1c2b20"
PINE_MID = "#263a2b"
BIRCH = "#e3a227"
BIRCH_DEEP = "#c3830f"


def smooth(values, k):
    """Running median then running mean: kills single-column spikes, keeps the needle tips."""
    v = np.asarray(values, dtype=float)
    pad = k // 2
    padded = np.pad(v, pad, mode="edge")
    med = np.array([np.median(padded[i:i + k]) for i in range(len(v))])
    kern = np.ones(3) / 3
    return np.convolve(np.pad(med, 1, mode="edge"), kern, mode="valid")


def path_from_profile(xs, ys, bottom, close=True):
    pts = " ".join(f"{x:.1f} {y:.1f}" for x, y in zip(xs, ys))
    d = f"M{xs[0]:.1f} {bottom:.1f}L{pts}L{xs[-1]:.1f} {bottom:.1f}"
    return d + ("Z" if close else "")


def simplify(xs, ys, step):
    idx = list(range(0, len(xs), step))
    if idx[-1] != len(xs) - 1:
        idx.append(len(xs) - 1)
    return [xs[i] for i in idx], [ys[i] for i in idx]


# ---------------------------------------------------------------------------------------------
# 1. The stand: the pine skyline and the birch crown from the portrait.
# ---------------------------------------------------------------------------------------------
def build_stand():
    im = Image.open(SRC / "portrait-pines.jpg").convert("RGB")
    W, H = im.size
    TOP = 700  # the treeline lives in the upper part of the frame; the subject starts lower
    rgb = np.asarray(im.filter(ImageFilter.MedianFilter(5)), dtype=float)[:TOP] / 255.0
    r, g, b = rgb[..., 0], rgb[..., 1], rgb[..., 2]
    mx, mn = rgb.max(-1), rgb.min(-1)
    sat = np.where(mx > 0, (mx - mn) / np.maximum(mx, 1e-6), 0)
    lum = 0.2126 * r + 0.7152 * g + 0.0722 * b
    # Overcast sky: bright and nearly grey. Everything else up there is tree.
    sky = (lum > 0.78) & (sat < 0.12)
    tree = ~sky
    # Birch leaves: warm hue, saturated, reasonably bright.
    hue = np.zeros_like(lum)
    d = np.maximum(mx - mn, 1e-6)
    hue = np.where(mx == r, ((g - b) / d) % 6, np.where(mx == g, (b - r) / d + 2, (r - g) / d + 4)) * 60
    gold = (hue > 22) & (hue < 58) & (sat > 0.38) & (mx > 0.45)

    top = []
    for x in range(W):
        col = tree[:, x]
        # first run of 6 tree pixels from the top (ignores a stray dark speck in the sky)
        run = np.convolve(col.astype(int), np.ones(6, dtype=int), mode="valid")
        hits = np.nonzero(run >= 6)[0]
        top.append(hits[0] if len(hits) else TOP)
    top = smooth(top, 5)

    # The birch crown: the gold mask, softened, traced as an iso-line so its edge stays organic.
    import matplotlib

    matplotlib.use("Agg")
    import matplotlib.pyplot as plt
    from scipy.ndimage import gaussian_filter

    soft = gaussian_filter(gold.astype(float), 4)
    # Output geometry: keep the full width, take rows from just above the highest tip.
    y0 = max(0, int(min(top)) - 6)
    y1 = 560  # below this it is all foliage; the header sheet ends the shape at its foot
    VW, VH = W, y1 - y0
    xs = list(range(W))
    ys = [min(t, y1) - y0 for t in top]
    xs_s, ys_s = simplify(xs, ys, 2)
    stand = path_from_profile(xs_s, ys_s, VH)

    fig = plt.figure()
    cs = plt.contour(np.arange(W), np.arange(TOP), soft, levels=[0.42])
    shapes = []
    for seg in cs.allsegs[0]:
        if len(seg) < 40:
            continue
        xsg, ysg = seg[:, 0], seg[:, 1]
        area = 0.5 * abs(np.dot(xsg, np.roll(ysg, 1)) - np.dot(ysg, np.roll(xsg, 1)))
        if area < 900:
            continue
        seg = seg[::2]
        shapes.append("M" + "L".join(f"{x:.1f} {min(y, y1) - y0:.1f}" for x, y in seg) + "Z")
    plt.close(fig)
    birch = "".join(shapes)
    g_cols = shapes

    svg = (
        f'<svg xmlns="http://www.w3.org/2000/svg" width="{VW}" height="{VH}" viewBox="0 0 {VW} {VH}" '
        f'preserveAspectRatio="xMidYMax meet">'
        f'<path d="{stand}" fill="{PINE_MID}"/>'
        f'<path d="{birch}" fill="{BIRCH}"/>'
        f'<path d="{birch}" fill="none" stroke="{BIRCH_DEEP}" stroke-width="1.2" opacity=".55"/>'
        "</svg>\n"
    )
    (OUT / "stand.svg").write_text(svg)
    print("wrote stand.svg", VW, "x", VH, len(svg), "bytes;", "birch shapes", len(g_cols))


# ---------------------------------------------------------------------------------------------
# 2. The ridge: the fog photo's hill line, three receding layers.
# ---------------------------------------------------------------------------------------------
def build_ridge():
    im = Image.open(SRC / "fog-hill.jpg").convert("L").filter(ImageFilter.GaussianBlur(3))
    W, H = im.size
    a = np.asarray(im, dtype=float)
    # Fog brightness per column (from the clear upper band) and the first row clearly darker.
    def line_at(frac):
        out = []
        for x in range(W):
            col = a[:, x]
            fog = np.median(col[200:500])
            thr = fog * frac
            idx = np.nonzero(col[500:] < thr)[0]
            out.append(500 + idx[0] if len(idx) else H)
        return smooth(out, 9)

    near = line_at(0.62)   # the dark treeline and slope
    far = line_at(0.74)    # the hill's softer edge, where the fog thins

    VW, VH = 1440, 120
    sx = VW / W
    lo = min(far.min(), near.min())
    hi = max(np.percentile(near, 98), np.percentile(far, 98))
    span = hi - lo

    def to_view(ys, lift):
        return [8 + (y - lo) / span * (VH - 30) - lift for y in ys]

    xs = [x * sx for x in range(W)]
    layers = [
        (to_view(far, 18), "#7f9fc2", 0.34),
        (to_view(near, -6), "#3c5067", 0.5),
    ]
    paths = []
    for ys, colour, op in layers:
        xs_s, ys_s = simplify(xs, ys, 6)
        paths.append(f'<path d="{path_from_profile(xs_s, [min(y, VH) for y in ys_s], VH)}" fill="{colour}" fill-opacity="{op}"/>')
    svg = (
        f'<svg xmlns="http://www.w3.org/2000/svg" width="{VW}" height="{VH}" viewBox="0 0 {VW} {VH}" '
        f'preserveAspectRatio="none">{"".join(paths)}</svg>\n'
    )
    (OUT / "ridge.svg").write_text(svg)
    print("wrote ridge.svg", len(svg), "bytes")


# ---------------------------------------------------------------------------------------------
# 3. Contours: a periodic height field, so the tile repeats without a seam.
# ---------------------------------------------------------------------------------------------
def build_contours():
    import matplotlib

    matplotlib.use("Agg")
    import matplotlib.pyplot as plt

    S = 360
    n = 240
    t = np.linspace(0, 2 * np.pi, n, endpoint=False)
    X, Y = np.meshgrid(t, t)
    rng = np.random.default_rng(7)
    Z = np.zeros_like(X)
    for _ in range(9):
        kx, ky = rng.integers(1, 4), rng.integers(-3, 4)
        Z += rng.uniform(0.4, 1.0) / np.hypot(kx, ky) * np.sin(kx * X + ky * Y + rng.uniform(0, 2 * np.pi))
    # wrap once more so the contour finder sees the seam continue
    Zw = np.pad(Z, ((0, 1), (0, 1)), mode="wrap")
    tw = np.linspace(0, S, n + 1)
    fig = plt.figure()
    cs = plt.contour(tw, tw, Zw, levels=np.linspace(Z.min(), Z.max(), 16)[1:-1])
    paths = []
    for i, segs in enumerate(cs.allsegs):
        for seg in segs:
            if len(seg) < 3:
                continue
            seg = seg[:: max(1, len(seg) // 120)] if len(seg) > 240 else seg
            d = "M" + "L".join(f"{x:.1f} {y:.1f}" for x, y in seg)
            # every fifth line is an index contour, drawn heavier, as on a survey sheet
            w, o = (1.1, 0.16) if i % 5 == 2 else (0.7, 0.09)
            paths.append(f'<path d="{d}" stroke-width="{w}" opacity="{o}"/>')
    plt.close(fig)
    svg = (
        f'<svg xmlns="http://www.w3.org/2000/svg" width="{S}" height="{S}" viewBox="0 0 {S} {S}">'
        f'<g fill="none" stroke="{PINE}" stroke-linejoin="round">{"".join(paths)}</g></svg>\n'
    )
    (OUT / "contours.svg").write_text(svg)
    print("wrote contours.svg", len(svg), "bytes")


# ---------------------------------------------------------------------------------------------
# 4. The homepage portrait: 2:3 around the subject, keeping the lower birch crown.
# ---------------------------------------------------------------------------------------------
def build_portrait():
    im = Image.open(SRC / "portrait-pines.jpg").convert("RGB")
    W, H = im.size  # 738 x 1599
    ch = round(W * 1.5)
    y = 330
    crop = im.crop((0, y, W, y + ch))
    for w in (738,):
        out = crop.resize((w, round(w * 1.5)), Image.LANCZOS) if w != W else crop
        out.save(OUT / f"portrait-{w}.webp", "WEBP", quality=82, method=6)
    crop.save(OUT / "portrait-738.jpg", "JPEG", quality=84, optimize=True, progressive=True)
    print("wrote portrait crops", crop.size)


if __name__ == "__main__":
    build_stand()
    build_ridge()
    build_contours()
    build_portrait()
