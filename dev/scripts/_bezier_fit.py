"""Schneider cubic Bezier fitting (numpy). Points are Nx2 float arrays.

Helper for build-jelly-sketch.py: turns the dense contour of a traced region into a
handful of smooth cubic segments, so the sketch overlay ships as a few KB of path data.
"""
import numpy as np

def _bez(ctrl, t):
    t = np.asarray(t)[:, None]
    mt = 1 - t
    return (mt**3) * ctrl[0] + 3 * (mt**2) * t * ctrl[1] + 3 * mt * (t**2) * ctrl[2] + (t**3) * ctrl[3]

def _bez1(ctrl, t):
    t = np.asarray(t)[:, None]
    mt = 1 - t
    return 3 * (mt**2) * (ctrl[1] - ctrl[0]) + 6 * mt * t * (ctrl[2] - ctrl[1]) + 3 * (t**2) * (ctrl[3] - ctrl[2])

def _bez2(ctrl, t):
    t = np.asarray(t)[:, None]
    mt = 1 - t
    return 6 * mt * (ctrl[2] - 2 * ctrl[1] + ctrl[0]) + 6 * t * (ctrl[3] - 2 * ctrl[2] + ctrl[1])

def _norm(v):
    n = np.linalg.norm(v)
    return v / n if n > 1e-12 else v

def _chord_param(pts):
    d = np.r_[0, np.cumsum(np.linalg.norm(np.diff(pts, axis=0), axis=1))]
    return d / d[-1] if d[-1] > 0 else d

def _gen_bezier(pts, u, t1, t2):
    p0, p3 = pts[0], pts[-1]
    t = u[:, None]
    b0 = (1 - t) ** 3; b1 = 3 * t * (1 - t) ** 2; b2 = 3 * t**2 * (1 - t); b3 = t**3
    A1 = t1[None, :] * b1
    A2 = t2[None, :] * b2
    C = np.array([[np.sum(A1 * A1), np.sum(A1 * A2)], [np.sum(A1 * A2), np.sum(A2 * A2)]])
    tmp = pts - (p0 * (b0 + b1) + p3 * (b2 + b3))
    X = np.array([np.sum(A1 * tmp), np.sum(A2 * tmp)])
    det = C[0, 0] * C[1, 1] - C[0, 1] * C[1, 0]
    seg = np.linalg.norm(p3 - p0)
    if abs(det) > 1e-12:
        a1 = (X[0] * C[1, 1] - X[1] * C[0, 1]) / det
        a2 = (C[0, 0] * X[1] - C[1, 0] * X[0]) / det
    else:
        a1 = a2 = 0
    eps = 1e-6 * seg
    if a1 < eps or a2 < eps:
        a1 = a2 = seg / 3
    return np.array([p0, p0 + t1 * a1, p3 + t2 * a2, p3])

def _reparam(ctrl, pts, u):
    q = _bez(ctrl, u) - pts
    q1 = _bez1(ctrl, u)
    q2 = _bez2(ctrl, u)
    num = np.sum(q * q1, axis=1)
    den = np.sum(q1 * q1, axis=1) + np.sum(q * q2, axis=1)
    out = u.copy()
    ok = np.abs(den) > 1e-12
    out[ok] = u[ok] - num[ok] / den[ok]
    return np.clip(out, 0, 1)

def _max_err(ctrl, pts, u):
    d = np.linalg.norm(_bez(ctrl, u) - pts, axis=1)
    i = int(np.argmax(d))
    return d[i], i

def fit_cubic(pts, t1, t2, tol, depth=0):
    n = len(pts)
    if n == 2:
        dist = np.linalg.norm(pts[1] - pts[0]) / 3
        return [np.array([pts[0], pts[0] + t1 * dist, pts[1] + t2 * dist, pts[1]])]
    u = _chord_param(pts)
    ctrl = _gen_bezier(pts, u, t1, t2)
    err, split = _max_err(ctrl, pts, u)
    if err < tol:
        return [ctrl]
    if err < tol * 4:
        for _ in range(5):
            u = _reparam(ctrl, pts, u)
            ctrl = _gen_bezier(pts, u, t1, t2)
            err, split = _max_err(ctrl, pts, u)
            if err < tol:
                return [ctrl]
    split = max(1, min(n - 2, split))
    tc = _norm(pts[split - 1] - pts[split + 1])
    return fit_cubic(pts[: split + 1], t1, tc, tol, depth + 1) + fit_cubic(pts[split:], -tc, t2, tol, depth + 1)

def fit_open(pts, tol=1.0, k=4):
    pts = np.asarray(pts, float)
    t1 = _norm(pts[min(k, len(pts) - 1)] - pts[0])
    t2 = _norm(pts[max(-k - 1, -len(pts))] - pts[-1])
    return fit_cubic(pts, t1, t2, tol)

def corners(pts, win=7, thresh_deg=52):
    n = len(pts)
    idx = []
    for i in range(n):
        a = pts[(i - win) % n]; b = pts[i]; c = pts[(i + win) % n]
        v1 = b - a; v2 = c - b
        n1, n2 = np.linalg.norm(v1), np.linalg.norm(v2)
        if n1 < 1e-9 or n2 < 1e-9: continue
        ang = np.degrees(np.arccos(np.clip(np.dot(v1, v2) / (n1 * n2), -1, 1)))
        idx.append((ang, i))
    out = []
    for ang, i in sorted(idx, reverse=True):
        if ang < thresh_deg: break
        if all(min((i - j) % n, (j - i) % n) > win * 2 for j in out):
            out.append(i)
    return sorted(out)

def fit_closed(pts, tol=1.0, corner_deg=52, win=7):
    pts = np.asarray(pts, float)
    if np.allclose(pts[0], pts[-1]): pts = pts[:-1]
    n = len(pts)
    cs = corners(pts, win=win, thresh_deg=corner_deg)
    segs = []
    if not cs:
        # start anywhere; tangents continuous across the seam
        i0 = 0
        rolled = np.roll(pts, -i0, axis=0)
        rolled = np.vstack([rolled, rolled[0]])
        k = 3
        t1 = _norm(rolled[k] - pts[(i0 - k) % n])
        t2 = _norm(pts[(i0 - k) % n] - rolled[k])
        t2 = _norm(rolled[-k - 1] - rolled[1])  # approx
        t1 = _norm(rolled[k] - rolled[-k - 1])
        t2 = -t1
        segs += fit_cubic(rolled, t1, t2, tol)
    else:
        for a, b in zip(cs, cs[1:] + [cs[0] + n]):
            idxs = [i % n for i in range(a, b + 1)]
            seg = pts[idxs]
            if len(seg) < 3:
                segs.append(np.array([seg[0], seg[0] + (seg[-1] - seg[0]) / 3, seg[0] + 2 * (seg[-1] - seg[0]) / 3, seg[-1]]))
                continue
            t1 = _norm(seg[min(4, len(seg) - 1)] - seg[0])
            t2 = _norm(seg[max(-5, -len(seg))] - seg[-1])
            segs += fit_cubic(seg, t1, t2, tol)
    return segs

def to_path(segs, closed=True, nd=1):
    f = lambda v: ("%.*f" % (nd, v)).rstrip("0").rstrip(".") if nd else str(int(round(v)))
    s = "M%s %s" % (f(segs[0][0][0]), f(segs[0][0][1]))
    for c in segs:
        s += "C%s %s %s %s %s %s" % (f(c[1][0]), f(c[1][1]), f(c[2][0]), f(c[2][1]), f(c[3][0]), f(c[3][1]))
    return s + ("Z" if closed else "")

def seg_length(segs, samples=24):
    L = 0.0
    t = np.linspace(0, 1, samples)
    for c in segs:
        p = _bez(c, t)
        L += float(np.sum(np.linalg.norm(np.diff(p, axis=0), axis=1)))
    return L
