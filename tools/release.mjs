#!/usr/bin/env node
/**
 * ktube release orchestrator — Phase 0-A (2026-06-21).
 *
 * The reversal removed the Vite-based build pipeline, so this orchestrator
 * no longer depends on a build step or a manifest. It now performs:
 *
 *   1. Bump the `Version:` header in `style.css` (or fail if already at target).
 *   2. Run `node tools/validate.mjs` so every committed asset is in place.
 *   3. Print a channel-agnostic release checklist covering zip layout.
 *
 * No Node dependency for shipping — if `node` is not available, the
 * maintainer can `sed -i 's/^Version: .*/Version: 0.1.1/' style.css`
 * by hand and skip straight to the zip step. The manifests that used
 * to gate releases (`theme-manifest.json`) are gone — the hand-managed
 * `assets/{css,js,vendor}/` are the source of truth.
 *
 * Usage:
 *   node tools/release.mjs <version>      Bump to e.g. 0.1.1 and validate.
 *   node tools/release.mjs --check        Print planned actions without changes.
 */

import { readFileSync, writeFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';

const __dirname = dirname( fileURLToPath( import.meta.url ) );
const ROOT = resolve( __dirname, '..' );
const STYLE_CSS_PATH = resolve( ROOT, 'style.css' );

function readStyle() {
	return readFileSync( STYLE_CSS_PATH, 'utf8' );
}
function writeStyle( body ) {
	writeFileSync( STYLE_CSS_PATH, body );
}
function currentVersion( body ) {
	const m = body.match( /^\s*\*\s*Version:\s*(\S+)/m );
	return m ? m[ 1 ] : null;
}
function bump( body, next ) {
	if ( ! /^\s*\*\s*Version:\s*\S+/m.test( body ) ) {
		throw new Error( 'Version: header missing in style.css — cannot bump.' );
	}
	return body.replace( /^\s*(\*\s*Version:\s*)\S+/m, `$1${ next }` );
}
function isSemVer( v ) {
	return /^\d+\.\d+\.\d+$/.test( v );
}
function runValidate() {
	const r = spawnSync( 'node', [ 'tools/validate.mjs' ], {
		cwd: ROOT,
		stdio: 'inherit',
		shell: process.platform === 'win32',
	} );
	if ( r.status !== 0 ) {
		process.exit( r.status ?? 1 );
	}
}
function checkOnly( next ) {
	const body = readStyle();
	const cur = currentVersion( body );
	console.log( `[ktube-release] dry run — current Version: ${ cur ?? '(missing!)' }` );
	console.log( `[ktube-release] would bump to: ${ next }` );
	console.log( '[ktube-release] would run tools/validate.mjs (asset presence + PHP syntax)' );
}
function realBump( next ) {
	const body = readStyle();
	const cur = currentVersion( body );
	if ( ! cur ) {
		throw new Error( 'Cannot read Version: field from style.css.' );
	}
	if ( cur === next ) {
		throw new Error( `Version is already ${ next }. Pick a different target or skip bump.` );
	}
	console.log( `[ktube-release] ${ cur } → ${ next }` );
	writeStyle( bump( body, next ) );
	runValidate();
	console.log( '[ktube-release] validate OK. Next steps (any channel). Pick ONE that matches your host:' );
	console.log( '   • Linux/macOS (tarball):  tar -C <theme-root> -czf ktube-' + next + '.tar.gz --exclude=.git --exclude=node_modules --exclude=tools --exclude=tests --exclude=vitest.config.mjs --exclude=phpunit.xml.dist --exclude=composer.json --exclude=RELEASING.md --exclude=to-do.md --exclude=package.json --exclude=package-lock.json --exclude=README.md --exclude=theme-manifest.json .' );
	console.log( '   • Linux/macOS (zip via zip):  ( cd <theme-root> && zip -qr ../ktube-' + next + '.zip . -x ".git/*" "node_modules/*" "tools/*" "tests/*" "vitest.config.mjs" "phpunit.xml.dist" "composer.json" "RELEASING.md" "to-do.md" "package.json" "package-lock.json" "README.md" "theme-manifest.json" )' );
	console.log( '   • macOS (ditto zip):  rm -rf /tmp/ktube-stage && mkdir /tmp/ktube-stage && rsync -a --exclude=.git --exclude=node_modules --exclude=assets/src --exclude=tools --exclude=tests --exclude=vitest.config.mjs --exclude=phpunit.xml.dist --exclude=composer.json --exclude=RELEASING.md --exclude=to-do.md --exclude=package.json --exclude=package-lock.json --exclude=README.md --exclude=theme-manifest.json ./ /tmp/ktube-stage/ && ditto -c -k --sequesterRsrcs /tmp/ktube-stage ktube-' + next + '.zip' );
	console.log( '   • Windows (PowerShell Compress-Archive):  $stage="C:\\ktube-stage"; if (Test-Path $stage) { Remove-Item -Recurse -Force $stage }; New-Item -ItemType Directory -Force -Path $stage | Out-Null; Copy-Item -Path "<theme-root>\\*" -Destination $stage\\ -Recurse -Force; Get-ChildItem -Path $stage\\assets -ErrorAction SilentlyContinue | Where-Object { $_.Name -eq "src" } | Remove-Item -Recurse -Force -ErrorAction SilentlyContinue; $exclude=@("tools","tests","vitest.config.mjs","phpunit.xml.dist","composer.json","package.json","package-lock.json","RELEASING.md","to-do.md","README.md","theme-manifest.json"); Get-ChildItem -Path $stage -Force | Where-Object { $exclude -contains $_.Name } | Remove-Item -Recurse -Force -ErrorAction SilentlyContinue; Compress-Archive -Path $stage\\* -DestinationPath ktube-' + next + '.zip -Force' );
	console.log( '   Phase 0-A 2026-06-21: assets/{css,js,vendor} are now source-of-truth — DO NOT exclude assets/dist (it does not exist in this layout).' );
	console.log( '   • Validate (channel-agnostic): node tools/validate.mjs' );
}
function main() {
	const arg = process.argv[ 2 ];
	if ( arg === '--check' ) {
		console.log( '[ktube-release] --check' );
		checkOnly( '0.1.1' );
		return;
	}
	if ( ! arg || ! isSemVer( arg ) ) {
		console.error( '[ktube-release] usage: node tools/release.mjs <x.y.z> | --check' );
		process.exit( 2 );
	}
	realBump( arg );
}
try {
	main();
} catch ( e ) {
	console.error( `[ktube-release] ${ e.message }` );
	process.exit( 1 );
}
