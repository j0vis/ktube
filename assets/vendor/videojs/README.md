# assets/vendor/videojs — vendored Video.js

Phase 0-A (2026-06-21) reverses the prior Vite/npm build pipeline.
The repo now ships a vendored Video.js 8.17.4 UMD bundle as committed
static files — exactly how WordPress has always shipped jQuery.

## What's here

| File                     | Source                                                                | Purpose                                         |
| ------------------------ | --------------------------------------------------------------------- | ----------------------------------------------- |
| `video.min.js`           | <https://unpkg.com/video.js@8.17.4/dist/video.min.js>                 | Core + bundled VHS (HLS/DASH)                  |
| `video-js.min.css`       | <https://unpkg.com/video.js@8.17.4/dist/video-js.min.css>             | `.video-js` / `.vjs-*` class styles             |
| `LICENSE`                | <https://github.com/videojs/video.js/blob/main/LICENSE>               | Apache-2.0 (full text)                          |
| `VENDORED.json`          | this repo                                                             | Provenance + URLs + version + size              |
| `README.md`              | this repo                                                             | This file                                       |

## Why one bundle, not three

The original Phase 3 plan was three bundles — `video.js/core`,
`videojs-http-streaming`, and ktube's own player — code-split behind
`import('video.js/core')`. Video.js 8 collapsed that:

* **Video.js 8 ships `videojs-http-streaming` (VHS, HLS/DASH) baked into
  the standard `video.min.js`.** A separate HLS plugin file is no longer
  required for ABR playback — the same global `videojs` plays progressive
  mp4 and HLS `.m3u8` out of the box, a deliberate one-bundle design
  decision the upstream team made when merging VHS into core.
* Tree-shakeable `core` builds (used in the prior Phase 3b import path)
  required `import('video.js/core')` from a Vite-built chunk. Drop
  Vite, drop the dynamic import, ship the UMD bundle — the same
  runtime behavior with one fewer dependency.

The dragnet for "what size is Video.js" is therefore: ~666 KB minified
(vendored) covers everything we need, which is the same total the prior
Phase 3b shipped (`video-js.js` chunk ≈ 263 KB raw + the HLS chunk that
would have come from a separate dynamic import). Operators who need
smaller load numbers can switch to self-hosting a `core`-only build (NOT
shipped by ktube to keep this folder honest about what is vendored).

## How ktube wires it up

`includes/setup.php::ktube_enqueue_player()` enqueues:

1. `ktube-main` (always site-wide) — hand-authored stylesheet.
2. `ktube-videojs-css` — vendored `video-js.min.css`, depends on
   `ktube-main` so the customizer-derived per-breakpoint vars cascade
   correctly. Enqueued ONLY on `is_singular('video')`.
3. `ktube-videojs` — vendored `video.min.js`. Enqueued ONLY on
   `is_singular('video')` AND when WPS Player plugin is absent (so the
   plugin can take over without double-init).
4. `ktube-player` — hand-authored `assets/js/player.js`, depends on
   `ktube-videojs`. Calls `window.videojs(id, opts)` on DOMContentLoaded.

Both enqueues are gated on `file_exists()` so a partial ZIP without
the vendor folder (operator deleted it by mistake) falls back to the
native `<video>` markup without throwing a 500. player.js emits one
`console.warn` when the vendored bundle is missing so the issue is
diagnosable in DevTools without a fatal error.

## Updating the vendored version

```sh
# Re-fetch the exact pinned version into this folder:
curl -sL -o assets/vendor/videojs/video.min.js      https://unpkg.com/video.js@8.17.4/dist/video.min.js
curl -sL -o assets/vendor/videojs/video-js.min.css  https://unpkg.com/video.js@8.17.4/dist/video-js.min.css
# Edit VENDORED.json's "version" fields to match.
git add assets/vendor/videojs/ && git commit -m "chore(videojs): bump vendored 8.17.4 → <new-version>"
```

Don't add `npm`, `yarn`, or `pnpm` to this repo to bump the version.
Keep the dependency as a vendored static.

## License

Video.js is Apache-2.0. The full text ships in `LICENSE` per the
short-error rule that Apache-2.0 §4a requires the LICENSE + NOTICE
to travel with any redistribution.
