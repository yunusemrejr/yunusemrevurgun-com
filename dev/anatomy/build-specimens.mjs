#!/usr/bin/env node
/* Builds every specimen figure: dev/anatomy/specimens/<name>.mjs -> assets/anatomy/spec-<name>.svg
 *
 * Usage: node dev/anatomy/build-specimens.mjs [name ...]     (no names = all)
 *
 * A specimen module exports:
 *   meta   { label: string,            the accessible description (what the object is, what moves)
 *            extents: [x, y, z][] }    world points the camera must fit besides the bench
 *   parts(lib) -> string[]             SVG strings in paint order, drawn on top of lib.base()
 * See lib.mjs for the helpers and conventions, and rack.mjs for a full-size example.
 */
import { mkdirSync, readdirSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";
import * as k from "./kit/iso-kit.mjs";
import { CAMERA, CANVAS, FOOT_H, PLATE, PLATE_H, makeLib } from "./lib.mjs";
import { SITE_TOKENS } from "./site-theme.mjs";

const HERE = dirname(fileURLToPath(import.meta.url));
const OUT = join(HERE, "..", "..", "assets", "anatomy");
const wanted = process.argv.slice(2);
mkdirSync(OUT, { recursive: true });

const names = readdirSync(join(HERE, "specimens"))
  .filter((f) => f.endsWith(".mjs"))
  .map((f) => f.replace(/\.mjs$/, ""))
  .filter((n) => !wanted.length || wanted.includes(n));

for (const name of names) {
  const mod = await import(pathToFileURL(join(HERE, "specimens", `${name}.mjs`)).href);
  const P = k.fitProjection([...k.boxCorners(PLATE, -PLATE_H - FOOT_H, 0), ...(mod.meta.extents ?? [])], CANVAS.width, CANVAS.height, { pad: 14, ...CAMERA });
  const lib = makeLib(P);
  const body = [lib.base(), ...mod.parts(lib)];
  const svg = k.figureSvg({ width: CANVAS.width, height: CANVAS.height, label: mod.meta.label, body, standalone: "light" }).replace("</style>", `${SITE_TOKENS}</style>`);
  writeFileSync(join(OUT, `spec-${name}.svg`), svg + "\n");
  console.log("wrote", `assets/anatomy/spec-${name}.svg`, svg.length, "bytes");
}
