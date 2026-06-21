#!/usr/bin/env node
/**
 * ktube build orchestrator — Phase 0-A (2026-06-21) reduced to NO-OP.
 *
 * Before reversal (Phase 5–Phase 7), `node tools/build.mjs` ran Vite to
 * compile `assets/src/{scss,js}` into `assets/dist/{css,js}` and emitted
 * `theme-manifest.json`. Phase 0-A dropped Vite/SCSS/bundler from the
 * repo entirely:
 *   - `assets/css/main.css` is hand-authored, committed verbatim.
 *   - `assets/js/*.js` are hand-authored IIFE bundles, committed verbatim.
 *   - `assets/vendor/videojs/{video.min.js,video-js.min.css}` is vendored
 *     from unpkg, committed verbatim.
 *
 * The script is preserved as a documented no-op so muscle-memory
 * `npm run build` invocations from older CI shells still exit cleanly
 * (status 0) without producing any output. A maintainer who DOES want
 * a build step (e.g. to bundle the JSX-free JS through Rollup for
 * Phase 8+) can resurrect this script; otherwise delete it.
 *
 * Usage:
 *   node tools/build.mjs           No-op (exit 0, no output).
 *   node tools/build.mjs --verbose Print a confirmation line.
 */
const verbose = process.argv.includes( '--verbose' );
if ( verbose ) {
	console.log( '[ktube-build] Phase 0-A: assets are hand-authored. Nothing to build. Use node tools/validate.mjs instead.' );
}
process.exit( 0 );
