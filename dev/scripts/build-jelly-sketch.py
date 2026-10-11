#!/usr/bin/env python3
"""Trace the mascot into the vector "machine sketch" that draws itself on the homepage.

Source:  assets/images/mascot.png            800 px transparent cutout of the mascot render
Output:  views/includes/jelly-sketch.html     inline markup (a wrapper with four stacked SVGs, viewBox 0 0 800 800),
                                              read by views/includes/hero-stage.php

The hero shows the mascot in three beats: ink linework drawn on construction lines (the sketch), flat
colour filled into the same regions, then the real render fading in over both. The three beats must
register with the render to the pixel, so the geometry is traced from the render itself:

  * regions (silhouette, cap, crown, jelly body, face, glints) come from colour classification of the
    cutout; each region's contour is smoothed, fitted with cubic Beziers (_bezier_fit.py) and written
    as a closed path;
  * the jelly's flute lines and latitude rings are generated from the traced silhouette (a surface of
    revolution seen from slightly above), so every line ends on the outline it belongs to;
  * the cap's panel seams start on the crown's top edge and end on its brim edge; the brim stitching
    is the cap region shrunk by a few pixels;
  * the drafting furniture (axis, bounding corners, fitted construction circle, dimension lines) is
    measured from the same masks.

Class names and the --len / --d custom properties are the contract with assets/css/blueprint.css.
Needs Pillow, numpy, scipy and scikit-image. Dev-only; re-run it after the mascot render changes
(dev/scripts/build-brand-assets.py first).
"""
import pathlib
import sys

import numpy as np
from PIL import Image
from scipy import ndimage as ndi
from skimage import measure

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent))
from _bezier_fit import fit_closed, fit_open, seg_length, to_path  # noqa: E402

ROOT = pathlib.Path(__file__).resolve().parents[2]
SRC = ROOT / "assets" / "images" / "mascot.png"
OUT = ROOT / "views" / "includes" / "jelly-sketch.html"


def load():
    im = Image.open(SRC).convert("RGBA")
    a = np.asarray(im).astype(np.float32)
    return a[..., 0], a[..., 1], a[..., 2], a[..., 3] / 255.0


R, G, B, A = load()
H, W = R.shape
yy, xx = np.mgrid[0:H, 0:W]

# ---- region masks ---------------------------------------------------------------------------------
sil = A > 0.5
red_dominant = (R > G + 45) & (R > B + 45)
navy = (B - R > 8) & (R < 125) & sil
cream_raw = (R > 190) & (G > 170) & (B > 140) & (np.abs(R - G) < 60) & sil & (yy < 335)
cap = ((sil & (yy < 335) & ~red_dominant) | navy)
cap = ndi.binary_opening(ndi.binary_closing(cap, iterations=3), iterations=3) & sil
# The colour classification is noisy along the brim (the render is dithered there), so the cap is
# re-derived from a heavily blurred copy; the jelly is "silhouette minus that cap", so the two share
# one smooth edge instead of two slightly different ones.
cap = ndi.gaussian_filter(cap.astype(np.float32), 3.6) > 0.5
cap &= sil
cap = ndi.binary_opening(cap, structure=np.ones((3, 3)), iterations=6)
lab, n_lab = ndi.label(cap)
sizes = ndi.sum(cap, lab, range(1, n_lab + 1))
button_lab = None
if n_lab > 1:
    order = np.argsort(sizes)[::-1]
    main_lab = order[0] + 1
    cap_main = lab == main_lab
else:
    cap_main = cap
# The brim's lower edge is where the dither noise is worst. The cap is one solid piece in every column
# (top edge down to brim edge), so smooth the brim edge as a function of x and rebuild the mask from it.
colt = np.full(W, -1)
colb = np.full(W, -1)
for x in range(W):
    ys = np.nonzero(cap_main[:, x])[0]
    if len(ys):
        colt[x], colb[x] = ys.min(), ys.max()
lo_x, hi_x = 175, 665
xs_cols = np.arange(W)
bottom_s = colb.astype(np.float32).copy()
seg = (xs_cols >= lo_x) & (xs_cols <= hi_x) & (colb >= 0)
bottom_s[seg] = ndi.gaussian_filter1d(colb[seg].astype(np.float32), 9, mode="nearest")
# blend back to the measured edge over the outer 25 px so the corners stay put
for x in range(lo_x - 25, lo_x):
    if colb[x] >= 0:
        k = (x - (lo_x - 25)) / 25
        bottom_s[x] = colb[x] * (1 - k) + bottom_s[lo_x] * k
for x in range(hi_x + 1, hi_x + 26):
    if colb[x] >= 0:
        k = (x - hi_x) / 25
        bottom_s[x] = bottom_s[hi_x] * (1 - k) + colb[x] * k
cap_rebuilt = np.zeros_like(cap_main)
for x in range(W):
    if colt[x] >= 0:
        cap_rebuilt[colt[x] : int(round(bottom_s[x])) + 1, x] = True
cap = ndi.gaussian_filter(cap_rebuilt.astype(np.float32), 2.0) > 0.5
cap |= ndi.gaussian_filter(((lab > 0) & ~cap_main).astype(np.float32), 1.0) > 0.5
cap &= sil
cream = ndi.binary_opening(ndi.binary_closing(cream_raw, iterations=3), iterations=3) & cap
jelly = ndi.binary_opening(sil & ~cap, iterations=4)
corner = (xx < 215) & (yy < 360)
jelly = np.where(corner, ndi.gaussian_filter(jelly.astype(np.float32), 9) > 0.5, jelly)
jelly &= sil & ~cap
face = (R < 90) & (G < 80) & (B < 90) & (yy > 380) & (yy < 580) & (xx > 220) & (xx < 600) & sil
face = ndi.binary_opening(face, iterations=1)
glint = (R > 215) & (G > 130) & (B > 120) & (yy > 300) & sil & ~cream & ~cap
glint = ndi.binary_opening(glint, iterations=1)


def median_colour(mask):
    px = np.stack([R[mask], G[mask], B[mask]], axis=1)
    return "#%02x%02x%02x" % tuple(int(v) for v in np.median(px, axis=0))


COLOUR = {
    # Slightly flattened versions of the render's own medians: the flat beat should read as the same
    # object before the lighting arrives.
    "jelly": median_colour(jelly & red_dominant & (G < 60)),
    "navy": median_colour(cap & ~cream & (B - R > 8)),
    "cream": median_colour(cream),
    "face": median_colour(face),
}


# ---- contours --------------------------------------------------------------------------------------
def polygon_area(xy):
    x, y = xy[:, 0], xy[:, 1]
    return 0.5 * abs(np.dot(x, np.roll(y, 1)) - np.dot(y, np.roll(x, 1)))


def loops(mask, sigma, min_area):
    field = ndi.gaussian_filter(mask.astype(np.float32), sigma)
    out = []
    for c in measure.find_contours(field, 0.5):
        xy = c[:, ::-1].copy()
        if len(xy) > 12 and polygon_area(xy) >= min_area:
            out.append(xy)
    out.sort(key=polygon_area, reverse=True)
    return out


def resample(xy, step=1.5):
    d = np.r_[0, np.cumsum(np.linalg.norm(np.diff(xy, axis=0), axis=1))]
    n = max(8, int(d[-1] / step))
    t = np.linspace(0, d[-1], n, endpoint=False)
    return np.c_[np.interp(t, d, xy[:, 0]), np.interp(t, d, xy[:, 1])]


def closed_path(xy, tol=1.1, corner_deg=52):
    segs = fit_closed(resample(xy), tol=tol, corner_deg=corner_deg)
    return to_path(segs, True), seg_length(segs)


def open_path(xy, tol=0.9):
    segs = fit_open(resample(xy), tol=tol)
    return to_path(segs, False), seg_length(segs)


def fmt(v):
    return ("%.1f" % v).rstrip("0").rstrip(".")


# ---- geometry helpers ------------------------------------------------------------------------------
def row_extent(mask, y):
    xs = np.nonzero(mask[int(round(y))])[0]
    return (xs.min(), xs.max()) if len(xs) else (None, None)


def col_extent(mask, x):
    ys = np.nonzero(mask[:, int(round(x))])[0]
    return (ys.min(), ys.max()) if len(ys) else (None, None)


def fit_circle(pts):
    x, y = pts[:, 0], pts[:, 1]
    Am = np.c_[2 * x, 2 * y, np.ones(len(x))]
    b = x**2 + y**2
    cx, cy, c = np.linalg.lstsq(Am, b, rcond=None)[0]
    return cx, cy, float(np.sqrt(c + cx**2 + cy**2))


ys_s, xs_s = np.nonzero(sil)
BBOX = (int(xs_s.min()), int(ys_s.min()), int(xs_s.max()), int(ys_s.max()))
jy, jx = np.nonzero(jelly)
CX = float((jx.min() + jx.max()) / 2)

# ---- the traced regions ----------------------------------------------------------------------------
sil_loop = loops(sil, 1.6, 50000)[0]
cap_loops = loops(cap, 2.4, 900)
cream_loop = loops(cream, 2.2, 5000)[0]
jelly_loop = loops(jelly, 2.4, 50000)[0]
face_loops = loops(face, 1.7, 120)
glint_loops = [l for l in loops(glint, 2.2, 180) if l[:, 1].min() > 300 and polygon_area(l) < 4000]

paths = {}  # name -> (d, length)
paths["jelly"] = closed_path(jelly_loop, tol=1.3)
paths["cap"] = closed_path(cap_loops[0], tol=1.2)
paths["cream"] = closed_path(cream_loop, tol=1.2)
paths["button"] = closed_path(cap_loops[1], tol=0.7) if len(cap_loops) > 1 else None
paths["faces"] = [closed_path(l, tol=0.7, corner_deg=80) for l in face_loops]
paths["glints"] = [closed_path(l, tol=0.9, corner_deg=80) for l in glint_loops]

# ---- jelly flute lines and latitude rings, from the traced silhouette ---------------------------------
jelly_top = {}
jelly_bottom = {}
for x in range(int(jx.min()) + 2, int(jx.max()) - 1):
    t, b = col_extent(jelly, x)
    if t is not None:
        jelly_top[x], jelly_bottom[x] = t, b

LAT = [330, 410, 490, 570, 640, 700]
ring_ry_ratio = 0.15
rings = []
for y in LAT:
    xl, xr = row_extent(jelly, y)
    if xl is None:
        continue
    cx, rx = (xl + xr) / 2, (xr - xl) / 2
    ry = rx * ring_ry_ratio
    rings.append("M%s %sA%s %s 0 0 0 %s %s" % (fmt(xl), fmt(y), fmt(rx), fmt(ry), fmt(xr), fmt(y)))

flutes = []
FIRST_RING = LAT[0]
ring_ry_ratio = 0.15
for f in (-0.87, -0.55, 0.55, 0.87):
    pts = []
    for y in range(FIRST_RING, int(max(jelly_bottom.values()))):
        xl, xr = row_extent(jelly, y)
        if xl is None:
            continue
        cx, rx = (xl + xr) / 2, (xr - xl) / 2
        x = cx + f * rx
        xi = int(round(x))
        if xi in jelly_bottom and y <= jelly_bottom[xi] - 2:
            pts.append((x, y))
    if len(pts) > 40:
        xy = np.array(pts)
        xy[:, 0] = ndi.gaussian_filter1d(xy[:, 0], 6, mode="nearest")
        # start exactly on the first latitude ring (its front arc sags by ry at the middle), end exactly
        # on the outline; the part above the ring is hidden under the cap's shadow, so it is dropped
        xl0, xr0 = row_extent(jelly, FIRST_RING)
        rx0 = (xr0 - xl0) / 2
        ring_y = FIRST_RING + rx0 * ring_ry_ratio * np.sqrt(max(0.0, 1 - f * f))
        xy = xy[xy[:, 1] > ring_y + 1]
        if len(xy) < 40:
            continue
        xy = np.vstack([[xy[0, 0], ring_y], xy])
        xy[-1, 1] = jelly_bottom[int(round(xy[-1, 0]))]
        flutes.append(open_path(xy[::3], tol=1.6))

# ---- the cap: seams that run from the crown's top edge to its brim edge ---------------------------------
cream_top, cream_bottom = {}, {}
cys, cxs = np.nonzero(cream)
for x in range(int(cxs.min()) + 1, int(cxs.max())):
    t, b = col_extent(cream, x)
    if t is not None:
        cream_top[x], cream_bottom[x] = t, b

SEAMS = [(368, 332), (396, 452), (424, 556)]  # (x on the crown's top edge, x on its brim edge)
seams = []
for xt, xb in SEAMS:
    xt = min(max(xt, min(cream_top)), max(cream_top))
    xb = min(max(xb, min(cream_bottom)), max(cream_bottom))
    yt, yb = cream_top[xt], cream_bottom[xb]
    p0 = np.array([xt, yt + 1.0])
    p3 = np.array([xb, yb - 1.0])
    bow = 10 if xb > xt else -10
    c1 = p0 + np.array([bow * 0.4, (p3[1] - p0[1]) * 0.32])
    c2 = p3 + np.array([-bow * 1.2, -(p3[1] - p0[1]) * 0.38])
    seg = np.array([p0, c1, c2, p3])
    d = "M%s %sC%s %s %s %s %s %s" % tuple(fmt(v) for v in (*p0, *c1, *c2, *p3))
    seams.append((d, seg_length([seg])))

# brim stitching: the cap shrunk by a few pixels, kept where it runs under the crown
dist = ndi.distance_transform_edt(cap)
stitch_field = ndi.gaussian_filter((dist > 9).astype(np.float32), 1.6)
stitch = None
for c in measure.find_contours(stitch_field, 0.5):
    xy = c[:, ::-1]
    keep = (xy[:, 1] > 205) & (xy[:, 0] > 270) & (xy[:, 0] < 706)
    keep &= np.array([cream[min(int(y) - 7, H - 1), int(x)] == 0 for x, y in xy])
    # longest contiguous run of kept points
    best, run = [], []
    for k, ok in zip(range(len(xy)), keep):
        if ok:
            run.append(k)
        else:
            if len(run) > len(best):
                best = run
            run = []
    if len(run) > len(best):
        best = run
    if len(best) > 60 and (stitch is None or len(best) > len(stitch)):
        stitch = xy[best]
stitch_path = open_path(stitch[::2], tol=0.8) if stitch is not None else None

# ---- construction geometry --------------------------------------------------------------------------
crown = np.array([p for p in cap_loops[0] if p[1] < 190 and 275 < p[0] < 600])
ccx, ccy, cr = fit_circle(crown)
x0, y0, x1, y1 = BBOX

construct = {
    "axis": "M%s 6V794M%s 6h-9M%s 794h-9" % (fmt(CX), fmt(CX - 0.0), fmt(CX - 0.0)),
    "circle": "M%s %sa%s %s 0 1 0 %s 0a%s %s 0 1 0 %s 0Z" % (fmt(ccx - cr), fmt(ccy), fmt(cr), fmt(cr), fmt(2 * cr), fmt(cr), fmt(cr), fmt(-2 * cr)),
    "corners": "M{a} {b}h24M{a} {b}v24M{c} {b}h-24M{c} {b}v24M{a} {d}h24M{a} {d}v-24M{c} {d}h-24M{c} {d}v-24".format(
        a=fmt(x0 - 6), b=fmt(y0 - 6), c=fmt(x1 + 6), d=fmt(y1 + 6)),
    # horizontal dimension under the figure and vertical dimension at its right, with extension lines
    "dim_w": "M{a} {e1}V{e2}M{c} {e1}V{e2}M{a} {m}H{c}M{a} {m}l9 -3.4v6.8ZM{c} {m}l-9 -3.4v6.8Z".format(
        a=fmt(x0), c=fmt(x1), e1=fmt(y1 + 5), e2=fmt(y1 + 20), m=fmt(y1 + 14)),
    "dim_h": "M{e1} {b}H{e2}M{e1} {d}H{e2}M{m} {b}V{d}M{m} {b}l-3.4 9h6.8ZM{m} {d}l-3.4 -9h6.8Z".format(
        b=fmt(y0), d=fmt(y1), e1=fmt(x1 + 5), e2=fmt(x1 + 20), m=fmt(x1 + 14)),
    "base": "M%s %sH%s" % (fmt(x0 - 24), fmt(y1), fmt(x1 + 24)),
}

# ---- assemble the markup ------------------------------------------------------------------------------
# Four stacked SVGs inside one wrapper, so each beat of the intro owns its own compositor layer:
#   .jx-construct  drafting furniture + the shared defs (fades in once, then stays, very faint)
#   .jx-fills      flat colour (fades in, later dissolves)                      opacity only
#   .jx-mesh       hatching, latitude rings, flutes, seams, stitching (static)  opacity only
#   .jx-ink        the outlines, drawn line by line (the only layer that repaints while animating)
# Fades are composited, so the draw on the few ink paths is the only per-frame paint cost of the intro.
out = []
w = out.append
w('<div class="jx" aria-hidden="true">')

w('<svg class="jx-construct" viewBox="0 0 800 800" focusable="false">')
w("<defs>")
w('<path id="jx-p-jelly" d="%s"/>' % paths["jelly"][0])
w('<path id="jx-p-cap" d="%s"/>' % paths["cap"][0])
w('<path id="jx-p-cream" d="%s"/>' % paths["cream"][0])
w('<clipPath id="jx-clip-jelly"><use href="#jx-p-jelly"/></clipPath>')
w('<pattern id="jx-hatch" width="7" height="7" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><path d="M0 0V7"/></pattern>')
w('<mask id="jx-mask-navy" maskUnits="userSpaceOnUse" x="0" y="0" width="800" height="800"><use href="#jx-p-cap" fill="#fff"/><use href="#jx-p-cream" fill="#000"/></mask>')
w("</defs>")
w('<path class="jx-axis" d="%s"/>' % construct["axis"])
w('<path class="jx-circle" d="%s"/>' % construct["circle"])
w('<path class="jx-corners" d="%s"/>' % construct["corners"])
w('<path class="jx-base" d="%s"/>' % construct["base"])
w('<path class="jx-dim" d="%s"/>' % construct["dim_w"])
w('<path class="jx-dim" d="%s"/>' % construct["dim_h"])
w("</svg>")

w('<svg class="jx-fills" viewBox="0 0 800 800" focusable="false">')
w('<use href="#jx-p-jelly" class="jx-fill" style="--c:%s"/>' % COLOUR["jelly"])
w('<use href="#jx-p-cap" class="jx-fill" style="--c:%s"/>' % COLOUR["navy"])
w('<use href="#jx-p-cream" class="jx-fill" style="--c:%s"/>' % COLOUR["cream"])
if paths["button"]:
    w('<path class="jx-fill" style="--c:%s" d="%s"/>' % (COLOUR["navy"], paths["button"][0]))
w('<path class="jx-fill jx-fill-glint" d="%s"/>' % "".join(g[0] for g in paths["glints"]))
w('<path class="jx-fill" style="--c:%s" d="%s"/>' % (COLOUR["face"], "".join(f[0] for f in paths["faces"])))
w("</svg>")

w('<svg class="jx-mesh" viewBox="0 0 800 800" focusable="false">')
w('<rect class="jx-hatch" width="800" height="800" fill="url(#jx-hatch)" mask="url(#jx-mask-navy)"/>')
w('<g clip-path="url(#jx-clip-jelly)">')
for d in rings:
    w('<path class="jx-line-fine" d="%s"/>' % d)
for d, ln in flutes:
    w('<path class="jx-line-fine" d="%s"/>' % d)
w("</g>")
for d, ln in seams:
    w('<path class="jx-line-fine" d="%s"/>' % d)
if stitch_path:
    w('<path class="jx-stitch" d="%s"/>' % stitch_path[0])
w("</svg>")

w('<svg class="jx-ink" viewBox="0 0 800 800" focusable="false">')
for name, d in (("jelly", paths["jelly"]), ("cap", paths["cap"]), ("cream", paths["cream"])):
    delay = {"jelly": 0.35, "cap": 0.5, "cream": 0.7}[name]
    w('<path class="jx-line jx-line-main" pathLength="1" style="--d:%ss" d="%s"/>' % (fmt(delay), d[0]))
if paths["button"]:
    w('<path class="jx-line jx-line-main" pathLength="1" style="--d:.9s" d="%s"/>' % paths["button"][0])
for i, (d, ln) in enumerate(paths["faces"]):
    w('<path class="jx-line jx-line-main" pathLength="1" style="--d:%ss" d="%s"/>' % (fmt(1.0 + i * 0.06), d))
w("</svg>")
w("</div>")

svg = "".join(out)
OUT.write_text(svg + "\n")
print("wrote", OUT.relative_to(ROOT), len(svg), "bytes")
print("colours", COLOUR)
print("bbox", BBOX, "axis x", CX, "crown circle", round(ccx), round(ccy), round(cr))
print("flutes", len(flutes), "rings", len(rings), "seams", len(seams), "stitch", bool(stitch_path), "glints", len(paths["glints"]), "faces", len(paths["faces"]))
