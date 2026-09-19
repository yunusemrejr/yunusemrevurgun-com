import { build } from 'esbuild';
import { writeFile } from 'node:fs/promises';
const result = await build({
  entryPoints: ['hero-entry.js'], bundle: true, minify: true,
  format: 'iife', globalName: 'VgpuHero', target: 'es2020', write: false,
});
// Three.js embeds GLSL as template literals. Trim redundant line-end whitespace
// in those shader strings as well as the surrounding bundle.
await writeFile('../../../assets/js/vgpu-hero.min.js', result.outputFiles[0].text.replace(/[ \t]+$/gm, ''));
