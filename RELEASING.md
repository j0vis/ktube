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
