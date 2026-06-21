# ktube

Modern hybrid-block WordPress 6.5+ tube theme — clean-room rebuild of
WP-Script KingTube. Phase 0-A (2026-06-21) ships hand-authored CSS,
plain JS, and vendored Video.js — **zero `npm install` required at
clone time.**

* theme.json v2 + classic PHP for data-heavy loops.
* Vanilla JS, no jQuery.
* WCAG 2.1 AA target.
* Compatible with WPS Mass Importer + WPS Player contracts.

## Requirements

| Runtime | Minimum | Notes                                                          |
| ------- | ------- | -------------------------------------------------------------- |
| PHP     | 8.3     | Hard-required (see `KTUBE_MIN_PHP` in `functions.php`).         |
| WordPress | 6.5   | Tested up to 6.8.                                              |

That's it. No Node, no Composer, no npm.

## Install / clone

```sh
# Clone into a fresh theme folder:
cd wp-content/themes && git clone https://github.com/j0vis/ktube.git
# Activate from wp-admin → Appearance → Themes.
```

The theme is runnable immediately: `assets/css/main.css`,
`assets/js/*.js`, and `assets/vendor/videojs/` are committed static
files. No `npm install`, no Vite, no SCSS compile — WordPress enqueues
them directly via `wp_enqueue_style`/`wp_enqueue_script` in
`includes/setup.php`.

## Directory layout

```
ktube/
├── style.css
├── theme.json
├── functions.php                Bootstrap only.
├── includes/                    All PHP modules (one hookup per file).
│   ├── setup.php                Enqueues CSS/JS; vendor Video.js on singular('video').
│   ├── customizer.php           Layout + color suite + dark-mode default.
│   ├── age-gate.php             Modal + RTA meta tag.
│   ├── privacy.php              Auto-doc data sheet + footer badge.
│   ├── post-types.php           video / blog / photo CPTs.
│   ├── taxonomies.php           category / tag / actor / channel.
│   ├── meta.php                 Custom-meta registration.
│   ├── wps-compat/              WPS Mass Importer + WPS Player contracts.
│   └── seo/schema.php           VideoObject / Article schema.
├── template-parts/              Archive + single + card components.
├── assets/
│   ├── css/main.css             Hand-authored, no preprocessor.
│   ├── js/                      Hand-authored IIFE bundles, no bundler.
│   └── vendor/videojs/          Vendored Video.js 8.17.4 (source-of-truth).
├── languages/
└── README.md / RELEASING.md / to-do.md
```

## Customizer

Settings grouped into:

* **ktube — Layout**: per-breakpoint grid columns + photo gallery columns.
* **ktube — Colors**: 8 tokens (light + dark × bg/surface/text/link/accent).
  Live WCAG contrast badge updates per picker.
* **ktube — Dark mode**: default theme for new visitors (`auto` / `light`
  / `dark`). Returning visitors keep their last toggle in localStorage.
* **ktube — Age gate**: 5 settings — enabled, min age, persistence days,
  redirect URL, RTA meta tag.
* **ktube — Privacy**: dropdown-pages selector for the privacy disclosure
  Page. Falls back to a Page with slug `privacy` if unset.

All settings render live in the Customizer preview iframe. The
customizer-emitted inline CSS includes per-key SHA-256 checksums that
the client-side rebuild (`assets/js/customize-controls.js`) verifies
on enter — drift triggers a `console.warn` with the diverged settings.

## Testing

Two test surfaces, runnable independently:

```sh
# JS: vitest + jsdom, runs assets/js/* through browsers' API surface.
npx vitest run

# PHP: stub-WP bootstrap, runs includes/* through assertion surface.
php tests/phpunit/run.php
```

If you have `vitest` installed (`npm install -g vitest` or via the
local `node_modules/`), the first command runs. If not, install via
the optional `npm install --no-save jsdom vitest`. **No production
code paths require an `npm install`** — only the test runner does.

## Releasing

See `RELEASING.md`. The release flow is now:

```sh
node tools/release.mjs 0.1.1     # Bump Version:, run validate, print zip commands.
```

`tools/build.mjs` is preserved as a documented no-op so muscle-memory
`npm run build` invocations still exit cleanly.

## Performance budget

The brief asks for 100/100/100 on PSI's three pillars (Performance,
Accessibility, Best Practices). Realistic targets:

| Layer                              | What ktube does                                                                                   |
| ---------------------------------- | ------------------------------------------------------------------------------------------------- |
| Critical CSS                       | Single linked stylesheet (≈ 16 KB) + ≈ 2 KB Customizer-derived inline CSS in `<head>`.            |
| Code-split JS                      | `dark-mode.js` and `video-grid.js` enqueued only where needed; `player.js` only on singular.       |
| Vendored Video.js                  | ≈ 666 KB minified, enqueued only on singular('video').                                             |
| Fonts                              | Subset via `font-display: swap`; no Google Fonts CDN dependency.                                  |
| Images                             | WordPress-native responsive image sizes (`ktube-card`, `ktube-card-2x`, `ktube-hero`, `ktube-og`). |
| CLS                                | Defaults via `aspect-ratio` CSS var; no layout-shift from trailer mount (node is appended but `aspect-ratio` holds the box). |

A bare-theme install with stock demo content, no ad zones, and lazy-loaded
everything can hit 100/100/100 on PSI's lab. The moment a real third-party
ad/CDN/wps-player stack is layered on top, scores become partly out of
theme code's control — the README warns future contributors not to claim
a guaranteed score.

## Where to pick up

See `to-do.md` §5. Spec divergence audit (`§0`) and prioritized
follow-ups (`§5-A`) live there.
