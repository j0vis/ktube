#!/usr/bin/env node
/**
 * ktube release validator — Phase 0-A (2026-06-21).
 *
 * Confirms the post-reversal hand-managed asset layout ships complete.
 * Phase 0-A+round-7 extends this with a vendored-file SHA-256 byte-
 * identity contract — closes the round-2 reviewer nit about the byte-
 * identity guarantee that the prior theme-manifest.json provided.
 *
 *   1. assets/css/main.css exists
 *   2. assets/js/{main,player,dark-mode,age-gate,video-grid,
 *                 lightbox-controller,customize-controls}.js each exist
 *   3. assets/vendor/videojs/{video.min.js,video-js.min.css} each exist
 *      AND match VENDORED.json's recorded SHA-256 + size
 *   4. NO assets/dist/ directory exists (Phase 0-A reverses out of it)
 *   5. NO vite.config.js / assets/src/ (Phase 0-A reverses out of it)
 *   6. Every .php file under the theme root lints via `php -l`.
 *
 * Pre-reversal, this script validated `theme-manifest.json` SHA-256 sums.
 * Phase 0-A drops the manifest: assets are hand-managed and the
 * maintainer commits intentional changes under git anyway. The vendored
 * subdirectory keeps its own sidecar (VENDORED.json) so we still get
 * byte-identity on the third-party files where it matters most.
 *
 * Exits 0 on a clean run, 1 if any check fails.
 */

import { createHash } from 'node:crypto';
import { statSync, readdirSync, existsSync, readFileSync } from 'node:fs';
import { resolve, dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';

const __dirname = dirname( fileURLToPath( import.meta.url ) );
const ROOT = resolve( __dirname, '..' );

/**
 * ktube-validate-contract — single-source-of-truth for the structural
 * assertions below. When VENDORED.json's `schema` bumps from /3 → /4
 * (or a future maintainer adds a new required field) update ONLY the
 * constants here; the verify loops reference this object. Co-locating
 * prevents the schema string + required-field list from drifting
 * separately across the file.
 */
const KTUBE_VALIDATE_CONTRACT = {
	vendoredSchema: 'ktube.vendor.videojs/3',
	vendoredRequiredFields: [ 'path', 'size_bytes', 'sha256' ],
};

const REQUIRED = [
	'style.css',
	'functions.php',
	'theme.json',
	'includes/setup.php',
	'includes/post-types.php',
	'includes/taxonomies.php',
	'includes/meta.php',
	'includes/customizer.php',
	'includes/age-gate.php',
	'includes/privacy.php',
	'includes/seo/schema.php',
	'includes/wps-compat/mass-importer.php',
	'includes/wps-compat/wps-player.php',
	'includes/wps-compat/importer-adapter.php',
	'includes/template-functions.php',
	'assets/css/main.css',
	'assets/js/main.js',
	'assets/js/player.js',
	'assets/js/dark-mode.js',
	'assets/js/age-gate.js',
	'assets/js/video-grid.js',
	'assets/js/lightbox-controller.js',
	'assets/js/customize-controls.js',
	'assets/vendor/videojs/video.min.js',
	'assets/vendor/videojs/video-js.min.css',
	'assets/vendor/videojs/README.md',
	'assets/vendor/videojs/VENDORED.json',
	'assets/vendor/videojs/LICENSE',
];

const FORBIDDEN_PATHS = [
	'vite.config.js',
	'assets/dist',
	'assets/src',
	'theme-manifest.json',
	'assets/css/editor.css',
];

// Vendored File: SHA-256 byte-identity contract. Reads VENDORED.json and
// verifies every entry on disk. Drift fails the run.
const VENDORED_JSON_PATH = resolve( ROOT, 'assets/vendor/videojs/VENDORED.json' );

let failed = 0;
function fail( msg ) {
	console.error( '  FAIL  ' + msg );
	failed++;
}

function sha256Hex( path ) {
	const buf = readFileSync( path );
	return {
		size_bytes: buf.length,
		sha256: createHash( 'sha256' ).update( buf ).digest( 'hex' ),
	};
}

console.log( `[ktube-validate] Phase 0-A: hand-managed asset layout (${ new Date().toISOString() })` );

// 1-3. Required paths present.
for ( const rel of REQUIRED ) {
	const full = join( ROOT, rel );
	if ( ! existsSync( full ) ) {
		fail( `missing required path: ${ rel }` );
	}
}

// 4-5. Forbidden paths absent.
for ( const rel of FORBIDDEN_PATHS ) {
	const full = join( ROOT, rel );
	if ( existsSync( full ) ) {
		fail( `forbidden path still present (Phase 0-A reversal incomplete): ${ rel }` );
	}
}

// Vendored-file byte-identity contract (round-7+1).
if ( existsSync( VENDORED_JSON_PATH ) ) {
	let sidecar;
	try {
		sidecar = JSON.parse( readFileSync( VENDORED_JSON_PATH, 'utf8' ) );
	} catch ( _e ) {
		fail( `VENDORED.json is not valid JSON` );
	}
	if ( sidecar ) {
		const expectedSchema = KTUBE_VALIDATE_CONTRACT.vendoredSchema;
		if ( sidecar.schema !== expectedSchema ) {
			fail( `VENDORED.json schema mismatch (got '${ sidecar.schema }', expected '${ expectedSchema }') — bump validator + sidecar together` );
		}
		const entries = Array.isArray( sidecar.files ) ? sidecar.files : [];
		const REQUIRED_ENTRY_FIELDS = KTUBE_VALIDATE_CONTRACT.vendoredRequiredFields;
		for ( let entryIdx = 0; entryIdx < entries.length; entryIdx++ ) {
			const entry = entries[ entryIdx ];
			// Required-fields gate: a maintainer who deletes `size_bytes` or
			// `sha256` would otherwise silently weaken the contract because
			// the per-field drift checks below short-circuit on missing
			// keys. Surface the structural gap before any disk comparison
			// runs so operators see why their edit is unsafe to ship.
			// Including the files[] index removes ambiguity in sidecars with
			// 8+ vendored entries.
			const missingFields = REQUIRED_ENTRY_FIELDS.filter( ( k ) => ! Object.prototype.hasOwnProperty.call( entry, k ) );
			if ( missingFields.length > 0 ) {
				fail( `VENDORED.json files[${ entryIdx }] missing required field(s): ${ missingFields.join( ', ' ) } — full entry: ${ JSON.stringify( entry ) }` );
				continue;
			}
			const full = join( ROOT, 'assets/vendor/videojs', entry.path );
			if ( ! existsSync( full ) ) {
				fail( `VENDORED.json declares ${ entry.path } but file is missing` );
				continue;
			}
			const actual = sha256Hex( full );
			if ( actual.size_bytes !== entry.size_bytes ) {
				fail( `vendored size drift on ${ entry.path } (sidecar ${ entry.size_bytes }, disk ${ actual.size_bytes }) — full SHA on disk: ${ actual.sha256 }` );
			}
			if ( actual.sha256 !== entry.sha256 ) {
				fail( `vendored hash drift on ${ entry.path } (sidecar full ${ entry.sha256 }, disk full ${ actual.sha256 })` );
			}
		}
	}
}

// 6. PHP syntax-lint every .php under theme root.
function walkPhp( dir, out = [] ) {
	let entries;
	try { entries = readdirSync( dir, { withFileTypes: true } ); } catch ( _e ) { return out; }
	for ( const entry of entries ) {
		const full = join( dir, entry.name );
		if ( entry.isDirectory() ) {
			if ( entry.name === 'node_modules' || entry.name === '.git' ) continue;
			walkPhp( full, out );
		} else if ( entry.isFile() && entry.name.endsWith( '.php' ) ) {
			out.push( full );
		}
	}
	return out;
}
const phpFiles = walkPhp( ROOT );
for ( const f of phpFiles ) {
	const r = spawnSync( 'php', [ '-l', f ], { encoding: 'utf8' } );
	if ( r.status !== 0 ) {
		fail( `PHP syntax error in ${ f.replace( ROOT + '\\', '' ).replace( ROOT + '/', '' ) }: ${ r.stderr || r.stdout }` );
	}
}

if ( failed ) {
	console.error( `[ktube-validate] FAIL — ${ failed } drift(s) detected.` );
	process.exit( 1 );
}
console.log( `[ktube-validate] OK — ${ REQUIRED.length } required paths present, ${ phpFiles.length } PHP files syntax-clean, vendored SHA-256 contracts honored, no Forbidden paths.` );
