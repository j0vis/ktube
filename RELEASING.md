# Releasing ktube

This theme is **distribution-channel-agnostic**. Every release artifact
is the same directory tree, regardless of where you host it:

* GitHub Release ZIP
* Self-hosted `downloads/ktube-0.1.1.zip` behind a CDN
* WordPress.org SVN commit (wp-admin's auto-update feeds off this)
* A commit hash attached to a private mirror for enterprise clients
* A tarball handed to a sysadmin over SCP

Phase 0-A (2026-06-21) reversed the Vite/SCSS build pipeline. **The
repo is clonable and immediately runnable with no `npm install`.**
The release flow is now `bump Version: + validate + zip` — three
file paths, no Node-compile.

The `tools/*.mjs` Node scripts are conveniences, not requirements.
A maintainer who can't install Node bumps the version with `sed`,
runs `php -l` over the changed files, and skips straight to zip.

---

## Release steps

### 1. Bump the version

Single source of truth is the `Version:` header in `style.css`.

```sh
# Manual edit:
sed -i 's/^Version: .*/Version: 0.1.1/' style.css

# Or via the helper (if Node available):
node tools/release.mjs 0.1.1
```

### 2. Validate

```sh
# Channel-agnostic smoke-check (Node):
node tools/validate.mjs

# Channel-agnostic PHP lint (no Node, no Composer):
find . -type f -name '*.php' ! -path './tests/*' -print0 | while IFS= read -r -d '' f; do
  php -l "$f" || { echo "FAIL: $f"; exit 1; }
done
```

Exits 0 if `assets/{css,js,vendor}/` are all present, `vite.config.js`
+ `assets/dist/` + `assets/src/` are all gone, and every PHP file
syntax-lints clean.

### 3. Package the theme ZIP

```sh
# Linux/macOS (clean staging dir):
stage=$(mktemp -d); rsync -a --exclude='.git' --exclude='node_modules' \
  --exclude='tools' --exclude='tests' --exclude='phpunit.xml.dist' \
  --exclude='composer.json' --exclude='vitest.config.mjs' \
  --exclude='package.json' --exclude='package-lock.json' \
  --exclude='RELEASING.md' --exclude='to-do.md' --exclude='README.md' \
  --exclude='theme-manifest.json' \
  ./ "$stage/ktube/"
( cd "$stage" && zip -r ktube-0.1.1.zip ktube )

# Windows PowerShell:
$stage = Join-Path $env:TEMP 'ktube-stage'
if (Test-Path $stage) { Remove-Item -Recurse -Force $stage }
New-Item -ItemType Directory -Force -Path $stage | Out-Null
Copy-Item -Path .\*.css, .\*.\json, .\*.php, .\*.md `
  -Destination "$stage\" -Recurse -Force
Copy-Item -Path .\assets, .\includes, .\template-parts, .\screenshot, .\languages `
  -Destination "$stage\" -Recurse -Force
$exclude = @('tools','tests','phpunit.xml.dist','composer.json',
  'vitest.config.mjs','package.json','package-lock.json',
  'RELEASING.md','to-do.md','README.md','theme-manifest.json')
$exclude | ForEach-Object {
  if (Test-Path "$stage\$_") { Remove-Item -Recurse -Force "$stage\$_" }
}
$stageExclude = @('src','dist')
foreach ($folder in $stageExclude) {
  if (Test-Path "$stage\assets\$folder") {
    Remove-Item -Recurse -Force "$stage\assets\$folder"
  }
}
Compress-Archive -Path "$stage\*" -DestinationPath ktube-0.1.1.zip -Force

# macOS native:
ditto -c -k --sequesterRsrcs --keepParent ./ ktube-0.1.1.zip
```

The release ZIP's top level must be the theme root, so the WP admin
uploader sees `style.css` at the root of the archive. **`assets/{css,js,vendor}/`
KEEP** — they're the source of truth after Phase 0-A. **`assets/src/`,
`assets/dist/` MUST NOT ship** — they are now ignored by the validator.

### 4. Publish

Push the ZIP wherever you ship. The end-user upload flow never needs
to know about GitHub:

```
WordPress admin → Appearance → Themes → Add New → Upload Theme → Choose ZIP
```

---

## What ships vs what's build-host-only

| Shipped in ZIP                | Build-host-only                          |
| ----------------------------- | ---------------------------------------- |
| `style.css`, `theme.json`     | `.github/` (CI conventions only)         |
| `*/*` PHP files                | `tools/`                                 |
| `screenshot/`, `languages/`   | `tests/`, `phpunit.xml.dist`             |
| `assets/css/main.css`         | `composer.json`, `package.json`, `package-lock.json` |
| `assets/js/*.js`              | `vitest.config.mjs`, `phpunit.xml.dist`  |
| `assets/vendor/videojs/*`     | `RELEASING.md`, `to-do.md`               |
| `index.php`, `404.php`, page templates | `README.md`                       |

`theme-manifest.json` is retired — Phase 0-A (2026-06-21) hands off
provenance to git history. If a downstream tool ever needs a checksum
file again, generate it at release time from `node tools/validate.mjs`'s
list of required paths.

---

## Performance caveats

The current release ships **without** a captured PSI baseline, and
several known performance-margin trade-offs apply. Read these before
claiming a Lighthouse or PageSpeed score in public.

1. **HLS / MPEG-DASH is not vendored.** `assets/vendor/videojs/video.min.js`
   is the Video.js 8 **core** build only — `videojs-http-streaming` (VHS)
   is intentionally not vendored. `.m3u8` and `.mpd` streams will fail.
   H.264 progressive `.mp4` plays in all current single-bitrate paths.
   *Operator impact:* any imported video using HLS packaging surfaces as
   `playback error` in the player. *Code locations:*
   `includes/setup.php::ktube_enqueue_player()` (the `ktube_has_wps_player()`
   short-circuit) and the absence of `videojs-http-streaming` in `package.json`.
   *Mitigation:* ship the §5-B ❌ Player depth work (lazy-import behind a
   Customizer flag plus a `Hls.isSupported()` probe) before serving HLS;
   or activate WPS Clean Tube Player on those videos.

2. **Video.js core raw size is ~75% above the brief's 150 KB target.**
   Phase 3b import-path swap trimmed Video.js from 695 KB raw / 206 KB
   gzip to **263 KB raw / 76 KB gzip** (~two-thirds lighter), but the
   brief's 150 KB raw target still isn't met. *Mitigation:* the §5-B ❌
   Player depth work is the next reduction, NOT another `video.js/*`
   import path.

3. **No AVIF / WebP image-format negotiation.** WordPress's default
   pixel-format pipeline is used. Authors uploading JPEGs / PNGs get
   those; no AVIF / WebP variants are produced at upload time.
   *Operator impact:* image bytes on the critical path are larger than
   on modern sites. *Mitigation:* until §5-B ❌ Phase 14 perf ships,
   run a server-side image-optimisation MU-plugin (ShortPixel / Imagify /
   WP-Optimize equivalent) outside the theme.

4. **Lightbox CSS bundles on every template, not just photo singles.**
   `assets/css/main.css` ships the lightbox modal stylesheet
   unconditionally. *Mitigation:* deferral-to-photo-singles lives in
   §5-B ❌ Phase 14 perf.

5. **No above-the-fold critical-CSS inlining.** `assets/css/main.css`
   is served whole in `<head>`. *Operator impact:* the stylesheet
   blocks paint while it downloads; on slow 3G the visitor sees a flash
   before the page content. *Mitigation:* until §5-B ❌ Phase 14 perf
   ships, use Cloudflare's "Auto Minify" + "Rocket Loader" toggles (or
   equivalent) as an MU-plugin or origin strategy.

6. **PSI workflow ships but no captured baseline yet.**
   `.github/workflows/psi.yml` directly invokes PageSpeed Insights v5
   REST API via `curl` — no Lighthouse-npm dependency, no headless
   Chrome. Trigger manually with `workflow_dispatch` against a deployed
   URL (inputs: `url`, `strategy`, `threshold`). Raw JSON auto-uploads
   as run-id artifact. Once a baseline is captured, the workflow gate-
   fails any future asset-touching change below the configured threshold
   (default 90).

For a runtime reality check before public launch, run the PSI workflow
against a deployed URL and capture the JSON:

1. `gh workflow run psi.yml -f url=https://your-ktheme.example.com`
2. Wait for the run; download the run-id artifact; archive the JSON.

No baseline has been captured against any deployment. **Do not quote
Lighthouse or PageSpeed numbers in public channels** until you have
run the PSI workflow against your own deployment and recorded the
resulting JSON in your release notes. Numbers listed elsewhere in this
document (the per-caveat size/byte trade-offs) are measured; the per-
category Performance/Accessibility/Best-Practices score is **not** —
the brief's 100/100/100 target has not been verified for any release
of ktube yet, and the open items in §5-B's Player depth + Phase 14
perf make it prudent to assume headroom rather than advertise a
specific number.
