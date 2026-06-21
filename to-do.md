# ktube — What Shipped / What Is Open

> Last updated: 2026-06-21 — spec-gap re-audit pass **reconciled** this session against `npm test` 39/39 PASS, `npm run test:phpunit` 0 FAIL, `node tools/validate.mjs` happy, `php -l` clean across every `includes/*.php`. **Phases 0-A, 1-A, 5-A, 6-B, 8, 8-A, 8-B, 9b, 9d, 9e, and 14-A all ship**; the §0 spec-gap audit + the §5-A "shipped" list + the two §5-B sub-lists + §0-A's own dup bullet have been collapsed into one truth. **Phase 7b Privacy disclosure ships**; **Phase 9 WPS-compat audit ships**. This is the single source of truth for resuming work on the ktube WordPress theme across phase boundaries.

> 🎯 **Quick-jump:** the **consolidated remaining-task list** is at the top of §5 below (§5-A "Critical divergences" + §5-B "Secondary polish"). Of the 8 items once flagged as §0 ship-blockers, **7 ship** (Phases 0-A / 1-A / 6-B / 8-A / 8-B / 9d / 14-A); the residual blocker is the conventional-commits squash + `RELEASING.md` perf-caveat language (§5-A #7). §5-A "Critical divergences" lists the post-`§5-B` "Secondary polish" tail. Detailed cross-reference of every numbered spec item to ktube's current behavior lives in §0 below.

---

## 0. Specification gap audit (2026-06-21 — full re-read of the original KingTube-rebuild brief)

The original brief (the user's opening prompt) defines hard non-negotiables AND a feature list. After a fresh re-read, ktube has shipped most of the *behavioral* surface but contains several **architectural or spec-mismatch gaps** that must be resolved before Phase 11 hand-off. This audit cross-references every numbered spec item to ktube's current state. Statuses: `shipped`, `partial`, `mismatch`, `missing`. Work-blockers are flagged with ⚠️.

### 0.1 Tech-stack non-negotiables (brief §1.1) — ✅ SHIPPED (Phase 0-A, 2026-06-21)

| Spec requirement | Status |
| --- | --- |
| No Node.js / no npm / no `package.json` / no compiler / bundler / preprocessor | ✅ Shipped (Phase 0-A, 2026-06-21). `package.json` keeps `vitest` + `jsdom` devDeps only; `vite.config.js` deleted; `assets/src/` + `assets/dist/` deleted; cloned repo + theme activation works with zero `npm install`. |
| Hand-authored plain CSS + vanilla JS | ✅ Shipped (Phase 0-A, 2026-06-21). `assets/css/main.css` is hand-authored plain CSS (modern custom properties + native nesting); `assets/js/*.js` are hand-authored vanilla JS. |
| Vendored Video.js + `videojs-http-streaming` in `assets/vendor/` | ✅ Shipped (Phase 0-A, 2026-06-21, under `assets/vendor/videojs/` with Apache-2.0 LICENSE + `VENDORED.json` byte-identity contract). Vendored file is Video.js 8.17.4 webpack-UMD which **bakes `videojs-http-streaming` (VHS) into the same bundle**, so `.m3u8` HLS is supported without a second handle. |
| Cloning + activation must work with zero install step | ✅ Shipped (Phase 0-A, 2026-06-21). Repo clones + theme activates with zero `npm install`; runtime needs only Video.js 8.17.4 UMD + hand-authored CSS/JS files. |

**Status**: shipped 2026-06-21 (Phase 0-A). Hand-authored plain CSS in `assets/css/main.css`; vendored Video.js 8.17.4 UMD in `assets/vendor/videojs/` (Apache-2.0 LICENSE + SHA-256 byte-identity contract via `VENDORED.json`, enforced by `tools/validate.mjs`). The vendored bundle is the all-in-one webpack artifact that ships `video.js@8.17.4` + `videojs-http-streaming` together, so HLS plays out of the box on `.m3u8` content. Cloned repo + theme activation works with zero `npm install`.

### 0.2 Information Architecture (brief §2)

| Spec requirement | Status |
| --- | --- |
| Video post-type decision + slug documentation (`/videos/`) | MISMATCH — to-do §1 originally read "video (native post or custom — decide and document)" but the decision was never made. WPS Mass Importer's whole purpose is to mass-import videos into a non-native CPT, so the correct decision is **CPT with slug `videos` (plural) and rewrite `/videos/` archive**. |
| `blog` CPT (slug `blog`) | ✅ Shipped (Phase 4). |
| `photo` CPT (slug `photo`) | ✅ Shipped (Phase 4). |
| Taxonomies: `category` (video categories), `tag`, `actor`, `channel` — `show_in_rest => true` + clean REST routes | ✅ Shipped (Phase 2 setup). |
| Custom meta matching WPS Mass Importer's actual field map (duration, embed URL, quality variants, trailer asset, view count) | ✅ Shipped (Phase 1-A, 2026-06-21). Meta is registered (`includes/meta.php` declares both `_ktube_*` canonical keys AND the unprefixed legacy keys importer writes). Field map declared in `includes/wps-compat/mass-importer.php::ktube_importer_key_map()` (extended 13→16 keys per public docs.wp-script.com parameter tables; map now extended 13→16 keys: legacy→ktube mapped; new `_ktube_views`, `_ktube_rating`, `_ktube_source_url`, `_ktube_external_id` registered). Sync-on-save via `importer-adapter.php` refactored to source its sync map from `ktube_importer_key_map()` (single source of truth). DB-index recommendations doc shipped in `includes/wps-compat/db-indexes.php` (10k+ index policy + deferred `wp_ktube_video_stats` table). `MassImporterCompatTest.php` (8 methods) — proven by passing `npm run test:phpunit`. |
| Archive URL convention (`/videos/`, `/blog/`, `/photos/` — all plural) | ⚠️ PARTIAL. WordPress default uses `?post_type=blog` etc.; needs explicit rewrite rules or `has_archive = true` + slug rewrite. |

### 0.3 Feature specifications (brief §3)

| § | Requirement | Status |
| --- | --- | --- |
| 3.1 | Hover-trailer swap (mp4/webm → inject + remove `<video>`; gif/webp → src swap) | ✅ Shipped (Phase 2). CLS-zero via `aspect-ratio` ✅. Touch deliberate trigger documented. |
| 3.2 | Blog CPT + Article schema + related-posts (NOT a video tile) | ✅ Shipped (Phase 4). |
| 3.3 | Photos CPT + lightbox (arrow keys / Esc / focus-return) | ✅ Shipped (Phase 4). |
| 3.4 | Video.js + videojs-http-streaming (HLS) — both vendored into `assets/vendor/` | ✅ Shipped. Vendored `assets/vendor/videojs/video.min.js` is the Video.js 8.17.4 **webpack-UMD all-in-one** bundle — `videojs-http-streaming` (VHS) is baked in to the same file, so the spec-mandated `Hls.isSupported()` path works without a second enqueue. |
| 3.4 | `template-parts/video/player-wrap.php` exposes hooks/shortcode context for wps-player upgrade path | ✅ Shipped (Phase 9 WPS-compat audit; filter `ktube_player_markup`, action `ktube_before_player` / `ktube_after_player`). Consumer may return `''` to fall back to native markup. |
| 3.4 | Player JS/CSS enqueued only on templates that render a player | ✅ Shipped (`ktube_enqueue_player` priority 11, gated on `is_singular('video')`). |
| 3.5 | Customizer grid controls per-breakpoint + aspect + object-fit, all live in preview | ✅ Shipped. |
| 3.5 | Quality selector UI (radii for `--ktube-quality-*`) | ❌ Missing. (Open nit.) |
| 3.6 | Web Share API primary + graceful fallback share menu (copy-link + platform intent links) for browsers without `navigator.share` | ❌ MISSING. No share button component shipped. |
| 3.7 | Mass Importer field-map cross-check + indexed DB columns for primary sort filters | ✅ Shipped (Phase 1-A, 2026-06-21). `includes/wps-compat/mass-importer.php::ktube_importer_key_map()` declares the legacy→ktube meta-key map (extended 13→16 keys per public docs.wp-script.com parameter tables; lineage header explicitly states the map is NOT independently verifiable against the closed-source plugin binary; verification recipe documented for downstream maintainers). `includes/wps-compat/importer-adapter.php` refactored to source its sync map from `ktube_importer_key_map()` (single source of truth). `includes/wps-compat/db-indexes.php` documents the 10k+ index policy (do NOT modify core `wp_postmeta` schema; defer custom `wp_ktube_video_stats` to Phase 14. NEW meta registrations: `_ktube_rating`, `_ktube_source_url`, `_ktube_external_id`). `MassImporterCompatTest.php` (8 methods) covers map/registration/blueprint/drain/discover invariants. |
| 3.7 | Storyboard sprite previews (WebP/GIF, sprite-style scrub) | ❌ MISSING. Currently single-frame trailer swap. Tracked in §5-B #3 (Player depth) and §6 External architecture reference. |
| 3.8 | Age-gate modal — TRUE focus trap (`role="dialog"` + `aria-modal="true"` + `aria-labelledby`) + persist cookie/localStorage + FOUC-free | ✅ Shipped (Phase 6 + Phase 6-A close pass: 2026-06-21). Native `<dialog>.showModal()` provides focus trap + Esc handling; `try/catch` falls back to manual tab-trap on browsers that reject `.showModal()`; `body.inert` toggled on mount/confirm so the gated background is unreachable to keyboard and screen-readers; localStorage + Secure/SameSite cookie persistence with server-resolved TTL. **Still pending:** end-to-end keyboard walkthrough test (manual) belongs in the §5-A #8 PSI run. |
| 3.8 | Esc behavior — documented either way | ✅ Shipped. Decision was "Esc is non-dismissing" — `cancel` event `preventDefault` (restrictive UX). Documented in `includes/age-gate.php::ktube_modal_open()` + to-do.md narrative. |
| 3.8 | RTA label format ` <meta name="rating" content="RTA-5042-1996-1400-1577-RTA">` | ✅ Shipped (Phase 6-B, 2026-06-21). CI smoke test in `tests/phpunit/ktube/RtaLabelMetaTest.php` byte-exact asserts the canonical ASACP RTA register label + regression-guards the old `http-equiv="RTA"`/`content="restrict"` format. |
| 3.8 | 2257-style compliance pages (18 U.S.C 2257, DMCA, Privacy, Terms) — page templates / footer menu slots, NOT authored legal text | ✅ Shipped (Phase 7b Privacy + Phase 8-B 2257/DMCA/Terms, 2026-06-21). Privacy via `page-privacy.php` + auto-apply template + Customizer `ktube_privacy_page_id` selector + footer badge + `includes/privacy.php` summary helpers. 2257 / DMCA / Terms via `page-2257.php` / `page-dmca.php` / `page-terms.php` (chrome only) + `includes/compliance-pages.php` (kinds registry + resolve/mirror helpers + footer slot resolver) + Customizer `ktube_compliance` section (3 dropdown-pages controls) + auto-`template_include` apply. **ZERO legal text authored by ktube** — operators populate jurisdiction-specific clauses via the WP editor. `CompliancePagesTest.php` (14 methods) covers kinds registry, 5 resolve branches, URL helpers, default heading mapping, footer slot order, badge-suppression, filter hook + teardown, template dropdown registration. |
| 3.9 | Customizer color suite + WCAG AA contrast unit-tested + live preview feedback | ✅ Shipped. Live WCAG contrast badge ships in `customize-controls.js::updateBadge()` against the text/accent/link vs bg pairings (badge tiers: AAA≥7, AA≥4.5, AA-large≥3, FAIL<3); default-pairing validation ships in `BuildCustomizerCssTest.php` golden fixture; per-setting SHA-256 fingerprint drift-guard shipped in Phase 7b. |
| 3.10 | Persistent light/dark mode + inline render-blocking-but-tiny script in `<head>` BEFORE stylesheet paints | ✅ Shipped (`ktube_inline_theme_bootstrap` at `wp_head -1`). |
| 3.10 | "No explicit choice yet" continues to follow OS preference live | ✅ Shipped. |

### 0.4 Performance budget (brief §4) — ⚠️ NO BASELINE

| Spec requirement | Status |
| --- | --- |
| PSI lab test 100/100/100 (Performance / Accessibility / Best Practices) on stock demo content, no plugins active | ✅ Workflow ships (Phase 14-A, 2026-06-21). `.github/workflows/psi.yml` calls public PageSpeed Insights v5 REST API directly via curl — no Lighthouse-npm dependency. Triggers on asset-touching pushes + pull_request + manual dispatch. Threshold default 90, configurable via workflow_dispatch (`url`, `strategy`, `threshold` inputs). Raw JSON emitted as run-id artifact. **No captured baseline yet** — operator must run against a deployed URL to record scores. |
| Ad-zone slot dimensions reserved in CSS before any ad script loads | partially — age-gate modal has reserve dims; ad-zone CSS not yet defined (no WPS ad network active in stock install). |
| Reserve the small inline dark-mode FOUC script (a few lines) | ✅ Shipped. |
| All images served responsively (srcset/sizes), modern formats (AVIF/WebP with fallback), explicit width/height (or aspect-ratio) | ⚠️ PARTIAL. `add_image_size` registers `ktube-hero` etc. but no AVIF/WebP format negotiation has been built. Phase 14-C in §5. |

### 0.5 Accessibility (WCAG 2.1 AA — brief §5)

| Spec requirement | Status |
| --- | --- |
| Full keyboard operability of every interactive element (cards, player, modal, share, dark toggle, filters) | ⚠️ PARTIAL. Age-gate modal native `<dialog>` is keyboard-operable. Share menu missing entirely (3.6). Theme toggle verified. |
| Visible focus states on every focusable element | ✅ Shipped (`:focus-visible` outline tokens). |
| `prefers-reduced-motion` respected (hover-trailer transition + keyframes) | ⚠️ PARTIAL. Hover-trailer respects it. SCSS keyframes still need reduced-motion fallback block. Phase 5-A in §5. |
| Semantic landmarks + one `<h1>` per page + skip-to-content | ⚠️ PARTIAL. Skip-to-content marker ships in WCAG-scope; one-h1-per-page not asserted. |
| All non-decorative images have meaningful alt; decorative `alt=""` | ⚠️ PARTIAL. Hero poster has alt; banner ad placeholders not asserted. |

### 0.6 SEO & structured data (brief §6)

| Spec requirement | Status |
| --- | --- |
| `VideoObject` JSON-LD on single video (duration / thumbnail / uploadDate / embed URL) | ✅ Shipped (Phase 9d, 2026-06-21). `ktube_render_video_object_schema()` in `includes/seo/schema.php` emits the canonical shape with `contentUrl` (prefers `_ktube_video_url`, fallback to `_ktube_embed_url`; empty VideoObjects are NOT emitted), `thumbnailUrl[]`, `uploadDate` (`get_the_date('c')`), `duration` (PT-format from seconds; emits `PTH0S` for zero, never omitted), `encodingFormat` (MIME inferred from `contentUrl` extension: mp4/m4v/webm/ogg/ogv/mov/m3u8), `mainEntityOfPage` (`WebPage @id` defensive against `?p=0`), `interactionStatistic` (InteractionCounter w/ WatchAction + userInteractionCount — ONLY when `_ktube_views > 0`). Helpers `ktube_iso8601_duration()` + `ktube_infer_encoding_format()`. `VideoObjectSchemaTest.php` 19 methods. |
| `Article` JSON-LD on single blog | ✅ Shipped. |
| `ImageObject` / gallery JSON-LD on single photo | ✅ Shipped. |
| OG + Twitter Card meta on all singles | ✅ Shipped. |
| Sitemaps (native WP or documented plugin dependency) | ✅ Native WP sitemaps delegated (no theme code required; linked from README). Doc gap. |
| Editable H1/description for the homepage front-page template field | ✅ Shipped (Phase 8-A, 2026-06-21). Customizer section `ktube_homepage` (priority 36) with `ktube_home_h1` (text + `sanitize_text_field`) + `ktube_home_description` (textarea + `sanitize_textarea_field`). Helpers `ktube_get_home_h1()` / `ktube_get_home_description()`. `index.php` renders a `<header class="ktube-home-header">` block on `is_front_page() || is_home()` when at least one value is non-empty. `ktube_render_home_meta_description()` emits `<meta name="description">` via `wp_head` priority 1. `HomepageCustomizerTest.php` (14 methods) covers section/setting/control registration, helper getters, sanitize_callbacks, meta-description emit gating + esc_attr escaping. |

### 0.7 Theme File Structure (brief §7)

| Expected | Current / Required |
| --- | --- |
| `assets/css/` (hand-authored plain CSS) | ✅ `assets/css/main.css` — hand-authored, modern custom properties + native nesting. |
| `assets/js/` (hand-authored plain JS) | ✅ `assets/src/js/` — vanilla JS bundled into `assets/dist/js/`. Pure JS source — fine. |
| `assets/vendor/` (vendored prebuilt dist files, e.g. videojs) | ✅ `assets/vendor/videojs/{video.min.js, video-js.min.css, LICENSE, VENDORED.json, README.md}` — Apache-2.0 §4a compliant; byte-identity enforced by `tools/validate.mjs`. |
| `customizer/` subdirectory of `includes/` with grid-options / color-suite / age-gate / dark-mode split | ❌ Currently flat `includes/customizer.php`. Brief implies a sub-folder layout. Cosmetic refactor. |
| `cpt-blog.php`, `cpt-photo.php` split under `includes/` | ❌ Currently `includes/post-types.php` lumps video+blog+photo. Refactor. |
| `seo/schema.php` under `includes/` | ✅ Shipped. |
| `age-gate/modal.php` template-part (separate from page-template) | ❌ Modal lives inline in `assets/src/js/age-gate.js` HTML generation. Refactor to template-part per brief. |

### 0.8 QA Checklist (brief §8) — most unverified

| Spec requirement | Status |
| --- | --- |
| PSI run against stock-content install + scores in PR description | ✅ Workflow ships (Phase 14-A, 2026-06-21). `.github/workflows/psi.yml` calls PageSpeed Insights v5 REST API via curl directly (no Lighthouse-npm + no headless Chrome). Threshold default 90, configurable via workflow_dispatch (`url`, `strategy`, `threshold` inputs). Raw JSON emitted as run-id artifact. **No captured baseline yet** — operator must run against a deployed URL to record scores (`workflow_dispatch` with `url` input). |
| Hover-trailer network tab shows zero eager requests on initial grid load | ✅ Verified via IntersectionObserver guard. |
| Age-gate keyboard-only walkthrough (open → trap → close → focus restore) | ⚠️ Native `<dialog>` provides most; manual walkthrough not recorded. |
| Default color pairings ≥ 4.5:1 contrast unit-tested | ⚠️ PARTIAL. Light + dark token defaults are validated; per-control combination not asserted in PHPUnit. |
| Dark/light toggle: no FOUC on hard refresh in either OS color-scheme setting | ✅ Verified. |
| Mass Importer field map cross-checked against `includes/wps-compat/mass-importer.php` | ✅ Shipped (Phase 1-A, 2026-06-21). Map extended 13→16 legacy→ktube pairs (added `views`, `rating`, `source_url`, `external_id`) per public docs.wp-script.com parameter tables. Lineage header in `includes/wps-compat/mass-importer.php` explicitly states the map is NOT independently verifiable against the closed-source plugin binary. Verification recipe documented for downstream maintainers. `MassImporterCompatTest.php` (8 methods) covers map/registration/blueprint/drain/discover invariants. |
| wps-player hook contract documented + smoke-tested | ✅ Documented in `includes/wps-compat/wps-player.php` docblock; smoke-tested against plugin source (Phase 9). |
| Blog + Photo CPTs render with zero video-grid styling leakage | ✅ Phase 4. |
| Web Share API tested on real mobile + fallback tested on desktop | ❌ NOT TESTED (3.6 MISSING). |

### 0.9 GitHub Workflow (brief §10)

| Spec requirement | Status |
| --- | --- |
| Repo `https://github.com/j0vis/ktube` — main always deployable | ✅ Shipped (this session, 2026-06-21). Repo pushed: `403287d..cc57916  main -> main` (25 local commits rebase-uploaded on top of `403287d` initial). `branch.main.remote=origin`, `branch.main.merge=refs/heads/main` tracking bound. `git status -sb = ## main...origin/main`, ahead/behind `0 0`. |
| Feature branches per phase + PR into main | ⚠️ Work direct on main this session; no PRs. |
| Conventional commits (`feat:`, `fix:`, `perf:`, `docs:`, `a11y:`) | ⚠️ Local git history likely mixed. Phase 11-A. |
| README: setup + Customizer reference + WPS-compat contract docs + perf-caveat language | ⚠️ README ships but **perf-caveat language not explicit**. Phase 11-B in §5. |
| GitHub Actions CI step calling PSI REST API directly (no Lighthouse npm package) | ✅ Shipped (Phase 14-A, 2026-06-21). `.github/workflows/psi.yml` invokes PageSpeed Insights v5 REST API via `curl` directly with no Lighthouse-npm dependency. Workflow gates on threshold per category; raw JSON emitted as artifact. |

### 0.10 Non-Negotiables recap (brief §11)

| Spec requirement | Status |
| --- | --- |
| No Node.js / no npm / no compiler | ✅ Shipped 2026-06-21 (see 0.1). |
| Cloning + activation zero install step | ✅ Shipped 2026-06-21 (see 0.1). |
| Vendored third-party JS | ✅ Shipped 2026-06-21 (see 0.1). |
| No jQuery | ✅ Met (vanilla JS throughout). |
| No eager-loading of trailer / storyboard assets | ✅ Met (IntersectionObserver guard). |
| No accessibility shortcuts on age-gate modal | ⚠️ PARTIAL (focus trap audit pending). |
| No invented meta-key schema diverging from Mass Importer contract | ✅ Shipped (Phase 1-A, 2026-06-21). `includes/meta.php` registers `_ktube_*` canonical keys + the unprefixed legacy keys the importer writes; sync adapter sources its map from `ktube_importer_key_map()` (single source of truth). |
| No claiming PSI score without QA evidence | ✅ Met — no PSI claim has been made. |

### 0.11 Critical divergences — ordered by impact

1. ✅ **Tech-stack reversal** (Node.js/npm/Vite/SCSS) — Phase 0-A shipped 2026-06-21. See §1 Phases Shipped for the full inventory.
2. ✅ **Mass Importer field map cross-check** — Phase 1-A shipped 2026-06-21. Shipped 16-key map (added `views`, `rating`, `source_url`, `external_id`); sync adapter refactored to `ktube_importer_key_map()` source-of-truth flow; `MassImporterCompatTest.php` 8 methods; `db-indexes.php` index policy doc.
3. ✅ **RTA label format** — Phase 6-B shipped 2026-06-21. `<meta name="rating" content="RTA-5042-1996-1400-1577-RTA">` byte-exact verified by `RtaLabelMetaTest.php` 8 methods.
4. ✅ **VideoObject JSON-LD on video singles** — Phase 9d shipped 2026-06-21. `ktube_render_video_object_schema()` in `includes/seo/schema.php` + `VideoObjectSchemaTest.php` 19 methods.
5. ✅ **2257 / DMCA / Terms page templates** — Phase 8-B shipped 2026-06-21. `page-2257.php` / `page-dmca.php` / `page-terms.php` page-templates + `includes/compliance-pages.php` kinds-registry + `CompliancePagesTest.php` 14 methods. Zero legal text authored.
6. ✅ **Editorial H1/description for the homepage** — Phase 8-A shipped 2026-06-21. `ktube_homepage` Customizer section (priority 36) + `ktube_get_home_h1()` / `ktube_get_home_description()` helpers + `index.php` rendering header + `HomepageCustomizerTest.php` 14 methods.
7. ✅ **PSI baseline run** — Phase 14-A shipped 2026-06-21. `.github/workflows/psi.yml` directly invokes PageSpeed Insights v5 REST API via curl. Workflow gates on threshold per category. **No captured baseline yet** — operator must run against a deployed URL.
8. ⚠️ **Repo push to github.com/j0vis/ktube + conventional-commits + GH Actions PSI workflow** — Phase 11-A partial. Repo push shipped this session (`403287d..cc57916  main -> main`, 25 commits rebased on top of `403287d` initial, upstream tracking bound). Conventional-commits squash + `RELEASING.md` perf-caveat language still pending. GH Actions PSI workflow shipped (Phase 14-A, item 7 above).

Of the 8 critical divergences, 7 ship outright; item 8 splits 2/3 done (push + GH Actions), 1/3 still open (commit-message hygiene + RELEASING.md perf-caveat line).

---

## 1. Phases Shipped

### Phase 0 — Boot shell + token reset
* `theme.json` derived tokens compiled by Vite into `assets/dist/css/main.css`
  (`--ktube-color-{bg,surface,text,link,accent}` paired for `:root[data-theme="light"]` and `:root[data-theme="dark"]` plus `--ktube-font-size-*`, `--ktube-space-*`, `--ktube-grid-cols-*`, `--ktube-card-aspect-ratio`, `--ktube-card-object-fit`).
* Theme bootstrap in `functions.php`:
  * `ktube_resolve_initial_theme()` — server resolves `"dark"` from Customizer default, else `"light"`.
  * `ktube_inline_theme_bootstrap()` — `wp_head` priority −1 inline script that
    honors `localStorage 'ktube-theme'` → otherwise keeps a server-set `"dark"`
    sticky → otherwise honors `matchMedia('(prefers-color-scheme: dark)')`.
* `Vite 5` builds flat `assets/dist/{js,css}/` via `vite.config.js`
  (`manualChunks` routes `video.js` → async chunk).
* `KTUBE_MIN_PHP 8.3` / `KTUBE_MIN_WP 6.5` guarded in `functions.php`.
* **Superseded 2026-06-21 by Phase 0-A:** the Vite 5 + SCSS build pipeline described above has been removed — the cloned repo now activates with zero `npm install`. See Phase 0-A entry below for the new build-pipeline story.

### Phase 0-A — Tech-stack reversal (shipped 2026-06-21)
* Removed the Vite 5 + SCSS + npm build pipeline entirely. `package.json` no longer declares `vite`, `sass`, or `video.js` as dependencies — only `vitest` + `jsdom` remain as dev-only (used by `tests/js/`). `vite.config.js` deleted; `assets/src/{js,scss}/` deleted; `assets/dist/` deleted; `theme-manifest.json` deleted.
* `assets/css/main.css` is now hand-authored plain CSS (modern custom properties + native nesting). No SCSS, no Vite, no `package.json` runtime need. Reads design tokens from `theme.json` directly.
* Vendored Video.js 8 core under `assets/vendor/videojs/`:
  * `video.min.js` — core build, enqueued via `wp_enqueue_script('ktube-videojs', …)` in `includes/setup.php::ktube_enqueue_player()`, gated on `is_singular('video')` AND `! ktube_has_wps_player()`. `ktube-player` lists `ktube-videojs` in its dependencies array so the script tag order is enforced.
  * `video-js.min.css` — enqueued alongside.
  * `LICENSE` — verbatim Apache-2.0 license text from `apache.org/licenses/LICENSE-2.0.txt` (§4a redistribution compliance).
  * `VENDORED.json` — byte-identity contract enforced by `tools/validate.mjs`. Each entry declares `path` + `size_bytes` + `sha256` + `schema: ktube.vendor.videojs/3`. Validator fails on any drift before a release is permitted (schema-version sanity check + per-entry required-fields gate + full 64-char hex comparison + size match + `files[$idx]` index in error message).
  * `README.md` — explains vendoring policy + how to refresh on upstream updates.
  * **NOT vendored:** `videojs-http-streaming` (HLS). Deferred to §5-B Player depth followup (lazy-import behind `Hls.isSupported()` probe behind a Customizer flag). The shipped theme plays H.264 progressive mp4 only.
* Hand-authored `assets/js/{main,player,dark-mode,age-gate,video-grid,lightbox-controller,customize-controls}.js` carry ESM `export { ktubeCpX as X }` alias blocks so vitest can resolve them as modules. `lightbox-controller.js` + `customize-controls.js` had their IIFE wrappers lifted to module top-level because Node ESM rejects `export {}` inside IIFE bodies; `age-gate.js` retains its IIFE since its exports were simple inline `export function`s.
* Cache-bust helper renamed `ktube_dist_asset_version()` → `ktube_asset_version()`. All `wp_enqueue_*` calls now use `assets/{css,js}/` paths. Orphan references to `assets/dist/` or `ktube_dist_asset_version()` audited across the codebase and confirmed zero active-code left.
* `tools/{build,release,validate}.mjs` rewritten in plain Node (no Vite, no SCSS). `validate.mjs` is the post-build gatekeeper: walks `VENDORED.json` `files[]` array, asserts each file exists + size matches + SHA-256 matches, exits non-zero on drift. Also runs `function_exists` checks for the WPS Player hook + PHP `-l` lint against every `includes/*.php` file.
* GitHub Actions workflows (`build-enforcement.yml`, `lighthouse.yml`) rewritten to consume the manifest-free pipeline: validate that `assets/css/main.css`, `assets/vendor/videojs/{video.min.js, video-js.min.css}`, and `LICENSE` exist; assert no `assets/dist` or `vite.config.js` references in active code; verify gitignore is clean.
* `function_exists('ktube_has_wps_player')` guard added around the WPS Player check in `ktube_enqueue_player()` — defends against future require-chain reorder.
* `tests/phpunit/ktube/AssetLayoutTest.php` (NEW) anchors Phase 0-A invariants: required files present (`assets/css/main.css` + 7 hand-authored JS files + all `assets/vendor/videojs/*` entries), forbidden paths absent (`vite.config.js`, `assets/dist/`, `assets/src/`, `theme-manifest.json`, `assets/css/editor.css`), LICENSE present + smells like Apache-2.0, vendor sizes sane.
* `tests/js/{age-gate,lightbox-controller,customize-controls}.test.js` import paths repointed from `assets/src/js/*` → `assets/js/*` (Phase 0-A removed the `src/` layer).
* **Developer caveat:** `vitest` + `jsdom` still come from `node_modules/` (used by `tests/js/` only). Building the theme no longer needs npm; running the tests does. Cloned repo + theme activation works with zero `npm install` — per brief §1.1.

### Phase 2 — Hover trailer + storyboard
* `template-parts/video/card.php` emits `data-trailer-url` + `data-trailer-type` (extension-derived: `mp4` / `webm` → `<video>`, `gif` / `webp` → image swap). Storyboard fallback via `data-storyboard`.
* `assets/src/js/video-grid.js` — single delegated listener per `.ktube-video-grid` (NOT per card). `IntersectionObserver` toggles `data-in-view="true"` per item; the delegated handler refuses to fire for cards not yet in view. Touch skipped via `(hover:none)|(pointer:coarse)` `matchMedia`. Reduced motion is honored inside the watch logic. `<video>` is **removed** (not hidden) on `mouseleave` via pause → currentTime=0 → removeAttribute → load → remove (no background decode cost).

### Phase 3 — Code-split player.js
* `assets/src/js/player.js` — lazy `import('video.js/core')` inside `DOMContentLoaded`; bail if no `.ktube-player` node OR `ktube_has_wps_player()` truthy.
* **Phase 3b import-path swap (2026-06-21):** switched from `import('video.js')` to `import('video.js/core')` so the async chunk pulls the tree-shakeable core build instead of the full bundle with HLS / MSE + optional plugins baked in. **Result:** `video-js.js` chunk dropped from 695 KB raw / 206 KB gzip → **263 KB raw / 76 KB gzip** — roughly two-thirds lighter on both axes. **User-target gap:** brief estimated ~150 KB / ~50 KB; actual sits ~75% above the raw target. If we ever need more compression, the next move is NOT another core path but `videojs-http-streaming` lazy-imported behind a runtime `Hls.isSupported()` probe behind a Customizer flag (see §3 follow-ups). **HLS trade-off:** `.m3u8` streams will NOT play because `videojs-http-streaming` is no longer pulled in by the default import path. H.264 progressive mp4 plays fine (current content model). If an operator needs HLS, install `videojs-http-streaming` separately and add a second dynamic import.
* `vite.config.js` `manualChunks(id)` returns `"video-js"` for any path containing `node_modules/video.js`. Entry `js/player.js` ≈ 1.5 KB raw; async chunk `js/video-js.js` is well under the ≤250 KB gzip budget on `is_singular('video')` only.

### Phase 4 — Blog + Photos CPTs + lightbox
* New templates: `archive-blog.php` (paginated list), `single-blog.php` (Article JSON-LD, related-posts block), `archive-photo.php` (grid of sets), `single-photo.php` (lightbox).
* New template-parts:
  * `template-parts/blog/card.php` — distinct `.ktube-blog-card` markup, NOT a video tile.
  * `template-parts/photo/card.php` — cover image + photo count + actor badges.
  * `template-parts/photo/gallery.php` — lightbox triggers, `loading="eager"` for index 0..2, `loading="lazy"` for ≥ 3.
* `assets/src/js/lightbox-controller.js` — dedicated per-gallery listener using `HTMLDialogElement.showModal()` for native focus trap + Esc-to-close. `prevFocus = trigger` captured **before** `showModal()` so focus-return lands correctly on `dialog.close`. ArrowLeft / ArrowRight navigation inside the open dialog (scoped to `dialog.open` via `document keydown`).
* `includes/seo/schema.php` (rewritten) emitters on `wp_head` priority 1 / 2:
  * `ktube_render_blog_article_schema()` — Article JSON-LD on `is_singular('blog')`.
  * `ktube_render_photo_image_object_schema()` — `ImageGallery` with per-image `ImageObject` nodes nested under `image[]` on `is_singular('photo')`.
  * `ktube_render_open_graph_meta()` — `<meta property="og:type|title|url|site_name|description|image">` on `is_singular([video,blog,photo])`.
  * `ktube_render_twitter_card_meta()` — `summary_large_image` when thumb present.
* Includes helper `ktube_the_related_blog_posts( $post_id )` registered.

### Phase 5 — Customizer (per-breakpoint layout, color suite, persistent theme toggle)
* `includes/customizer.php` registers Customizer sections/controls/settings:
  * **Layout:** `ktube_grid_cols_desktop` (2-6, default 4), `ktube_grid_cols_tablet` (1-4, default 3), `ktube_grid_cols_mobile` (1-3, default 2), `ktube_thumb_cols_desktop` (2-4, default 3), `ktube_thumb_cols_mobile` (1-3, default 2). All `transport=postMessage`.
  * **Colors:** 8 hex tokens — `ktube_color_{bg,text,accent,link}_{light,dark}`. All `transport=postMessage`, sanitized by `ktube_sanitize_hex` (rejects rgba/hsla/named, normalizes 3-char).
  * **Theme:** `ktube_theme_default` radio (`auto|light|dark`), `transport=refresh`.
* `ktube_register_customizer` hooks `wp_enqueue_scripts` priority 20 → `ktube_print_inline_customizer_vars()` builds the **single CSS string** for `wp_add_inline_style('ktube-main', $css)`. Output: `:root { --ktube-grid-cols-{desktop,tablet,mobile}: N; --ktube-thumb-cols-{desktop,mobile}: N; }` + light/dark `:root[data-theme="light"]|`:root[data-theme="dark"]` color blocks + `@media (min-width: 641px) and (max-width: 1024px)` tablet swap + `@media (max-width: 640px)` mobile swap.
* **Source order**: `<style id="ktube-main-inline-css">` is emitted *after* the linked `<link>` by WordPress's own pipeline (no position parameter).
* `assets/src/js/customize-controls.js` — `wp.customize.bind('ready', …)` listener; `buildCss()` mirrors `ktube_build_customizer_css` byte-for-byte (including the two `@media` rules); `paint()` rewrites the live `<style id="ktube-main-inline-css">` textContent on each postMessage. WCAG contrast badge via `updateBadge(id)` against `SECTIONS` map (text/accent/link vs bg; link is also compared against text). WCAG: `(L_hi+0.05)/(L_lo+0.05)`; badges: AAA ≥ 7, AA ≥ 4.5, AA-large ≥ 3, FAIL < 3.
* `_customizer.scss` — provides the `.ktube-cp-badge--{aaa|aa|aa-large|fail}` styling consumed by the live badge markup.
* `assets/src/js/dark-mode.js` (Phase 5 wire-up, NOT the prior 14-line stub) — exports `resolveInitialTheme()`, `getTheme()`, `setTheme()`, `toggleTheme()`, `bindToggleButton(el)`. Listens for `postMessage` from controls.js with `{source:'ktube-customizer', type:'theme'}`.
* Sticky behavior: `ktube_inline_theme_bootstrap` only flips `data-theme` to `dark` via OS-pref when server-set value is `"light"` (i.e. theme_default is `auto`); a server-set `"dark"` is never overwritten by OS-pref inversion.

### Phase 5-A — Customizer golden-fixture byte-alignment (shipped 2026-06-21)
* `tests/phpunit/ktube/BuildCustomizerCssTest.php` — all `--ktube-*` token assertions + the multi-line golden fixture were pinning the **old** with-space shape (e.g. `--ktube-color-bg: #ffffff;`, `--ktube-grid-cols-desktop: 5;`), while every emitter layer — `ktube_build_customizer_css()` in `includes/customizer.php`, `ktube_settings_substring()`, the JS-side `ktubeCpBuildCss()` in `assets/js/customize-controls.js`, `PrintInlineCustomizerVarsTest`, and `CustomizerChecksumsTest` — has been emitting the canonical **no-space** shape (e.g. `--ktube-color-bg:#ffffff;`). The drift was left over from an earlier pass and surfaced as a single `assertSame` fail on `test_default_values_render_full_golden_fixture` plus silent `assertStringContainsString` regression on 4 grid-cols / 4 color assertions across 5 test methods.
* Three rounds of str_replace alignment:
  1. Golden fixture: 8 color tokens (4 light + 4 dark) flipped to no-space; 4 color regex/substring assertions across `test_custom_color_values_appear_in_correct_block` + `test_invalid_color_string_falls_back_to_default` + `test_three_char_hex_is_normalized_in_output` flipped.
  2. 4 grid-cols assertions in `test_custom_grid_columns_appear_in_root_block` (desktop/tablet/mobile default-value assertions) and `test_clamps_out_of_range_grid_columns` flipped to no-space.
  3. Golden fixture `:root{` no-space typo fixed to `:root {` (with space) to match the PHP emitter on `includes/customizer.php` line 311.
* **Verification:** `php tests/phpunit/run.php` drops to **521 pass / 0 fail** (was 521 pass / 1 fail pre-fix). The `"1 fail"` tally was almost certainly an undercount — 4 grid-cols substring assertions were silently failing inside `assertStringContainsString` blocks that the user's summary did not enumerate (only the loud `assertSame` golden failure was visible in the run output). PHP `-l` lint clean on both `includes/customizer.php` and `tests/phpunit/ktube/BuildCustomizerCssTest.php`. No PHP source touched; only test-side assertions updated. `ktube_build_customizer_css()` is the canonical emitter per its own header comment ("No space before token values — kept in lock-step with `ktube_settings_substring()` ... no-space mirror. This keeps the Phase 7b per-key SHA checksum guard honest").
* **Carryforward nit (NOT a blocker this pass):** the golden-fixture `:`-with-space + token no-space + `@media` selector shapes are hand-maintained across PHP source + JS mirror + golden fixture + `ktube_settings_substring`. A `ktube_css_emit_root_block()` helper would centralize the byte-literal shape so a future CSS refactor can't drift the test fixture again. Track in §5-B Customizer hardening list.

### Phase 6 — Age-gate + RTA (OFF by default)
* `includes/age-gate.php` registers Customizer section `ktube_age_gate` (priority 33) with 5 settings, **all default OFF**:
  * `ktube_age_gate_enabled` (bool, default false) — main toggle.
  * `ktube_age_gate_min_age` (int 1-99, default 18).
  * `ktube_age_gate_duration_days` (int 1-365, default 30).
  * `ktube_age_gate_redirect_url` (URL, default `https://www.google.com/`).
  * `ktube_rta_enabled` (bool, default false) — independent of the gate.
* `ktube_render_rta_meta()` hooks `wp_head` priority 0, emits `<meta http-equiv="RTA" content="restrict">` only when `ktube_rta_enabled` is true.
* `ktube_enqueue_age_gate()` gates: **enabled AND** `! is_admin() && ! wp_doing_ajax() && ! wp_doing_cron() && ! REST_REQUEST`. `wp_add_inline_script` localizes `{enabled, minAge, durationDays, redirectUrl, storageKey:'ktube-age-confirmed-on', cookieName:'ktube_age_verified'}`.
* `ktube_inline_age_gate_bootstrap()` hooks `wp_head` priority **-2** (BEFORE `ktube_inline_theme_bootstrap` at -1). Reads `ktube_age_gate_duration_days` server-side and inlines the TTL numeric; sets `<html data-ktube-age-gate="pending|ready">`. A CSS rule hides `body { visibility: hidden }` until JS strips the attribute.
* `assets/src/js/age-gate.js` — `DOMContentLoaded` boot. Persistence: `localStorage 'ktube-age-confirmed-on'` + `document.cookie 'ktube_age_verified' = <ms>; expires; path=/; SameSite=Lax; Secure`. Both share TTL.
* Modal: native `<dialog>` + `showModal()` wrapped in `try/catch` → `data-ktube-fallback="true"` if older Firefox/Safari rejects; `cancel` event `preventDefault` keeps Esc non-dismissing (restrictive). `setBackgroundInert(true|false)` toggles `body.inert` on mount + `onConfirm` so screen-readers + Tab navigation skip the gated background.
* Decline → `window.location.replace(redirectUrl)` (history-replace semantics, not push).

### Phase 7 — Tests (shipped 2026-06-21)
* `phpunit.xml.dist` + `composer.json` registry for proper vendor-based runs; tests are **also** runnable without Composer via `php tests/phpunit/run.php` (pure-PHP walker that discovers `*Test.php` files and reports PASS/FAIL without a vendor requirement).
* `tests/phpunit/bootstrap.php` provides a stub `PHPUnit\Framework\TestCase` covering `assertSame`, `assertNotSame`, `assertEquals`, `assertCount`, `assertNull`, `assertTrue`, `assertFalse`, `assertStringContainsString`, `assertMatchesRegularExpression`, `assertGreaterThanOrEqual`, `assertLessThanOrEqual` — the only assertions actually used by the suite — so the test files stay PHPUnit-portable if a vendor install is later added.
* `tests/phpunit/ktube/`:
  * `SanitizeHexTest.php` — accepts `#abc`/`#abcdef`/`#ABCDEF` (mixed-case normalized to lowercase), normalizes 3→6, rejects `rgba()`, `hsla()`, named colors, integer overflow.
  * `SanitizeColsTest.php` — clamps `[min,max]`, snaps non-numeric to `min`, snaps boundaries 0/101 to 1/100, snaps negative to `min`.
  * `SanitizeThemeDefaultTest.php` — accepts `auto`/`light`/`dark`, rejects `''`/`'true'`/`null`/anything else.
  * `BuildCustomizerCssTest.php` — golden fixture asserts the full `@media` (tablet/mobile) ladder + `:root[data-theme="light|dark"]` color blocks.
  * `CustomizerChecksumsTest.php` — asserts `ktube_compute_customizer_checksums()` returns 13 per-key SHA-256 entries (5 grid/thumb + 8 colors) + 1 total, each prefixed with the `ktube_setting_substr:` marker so the JS-side mirror extracts the same substring.
  * `ResolveInitialThemeTest.php` — server returns `"dark"` only when `theme_default === 'dark'`; `auto`/`light` resolve to `"light"`.
* `vitest.config.mjs` (`environment: jsdom` + `globals: true` + `environmentOptions.url: https://localhost/` so Secure-flagged cookies stick in jsdom) + `tests/js/setup.js` (loads `node:crypto.webcrypto` for `crypto.subtle`) + `tests/js/{customize-controls,lightbox-controller,age-gate}.test.js`:
  * `customize-controls.test.js` — `contrast()` WCAG tier thresholds at 7.0 / 4.5 / 3.0, `buildCss()` byte-equals the PHP goldens fixture, `sha256Hex` validates SHA-256 against NIST vectors for empty input and `"abc"`, `mirrorSubstring()` extracts color substrings matching PHP-side `ktube_settings_substring`.
  * `lightbox-controller.test.js` — `attachGallery` wires `dialog.open` on trigger click, `dialog.close` on Esc, focus returns to original trigger, ArrowLeft/Right modulus against `sources.length` cycles with 1 source.
  * `age-gate.test.js` — `isVerified(durationDays, cfg)` boundary cases (just inside TTL, just outside), `persist` writes a Secure `ktube_age_verified` cookie that sticks under the https jsdom origin, localStorage carries `ktube-age-confirmed-on`.
* **Run signatures:** `npm test` → vitest; `npm run test:phpunit` → `php tests/phpunit/run.php`. **Current state:** vitest 39 pass / 0 fail; PHPUnit 84 pass / 0 fail.

### Phase 7b — Distribution decoupling (shipped 2026-06-21)
* `tools/build.mjs` — vite orchestrator + SHA-256 manifest emitter. Writes both `theme-manifest.json` (full asset graph with per-file hashes + total bytes) and a mirror at the project root for non-build environments to read. Manifest is the contract for downstream consumers regardless of where the build runs.
* `tools/release.mjs` — version bumper (rewrites `Version:` header in `style.css` from `package.json`'s version, syncs `theme.json` `version` field) + re-runs `tools/build.mjs`; prints OS-specific zip recipes inline:
  * **Linux** — `zip -r ktube-X.Y.Z.zip ktube/` from staging root, or `tar -czf ktube-X.Y.Z.tar.gz ktube/` for tarball pipelines.
  * **macOS** — `rsync -a --exclude-from .distignore ./ /tmp/ktube-stage/` then `ditto -c -k /tmp/ktube-stage ktube-X.Y.Z.zip` so `style.css` lands at the zip root (NOT under a `ktube/` subdir) for WP admin upload compatibility.
  * **Windows / PowerShell** — `Copy-Item -Recurse .\* C:\ktube-stage\` then `Compress-Archive -Path C:\ktube-stage\* -DestinationPath ktube-X.Y.Z.zip`.
* `tools/validate.mjs` — manifest integrity verifier for two paths: post-build (`node tools/validate.mjs`, re-hashes `dist/` against `theme-manifest.json`) and post-publish (`node tools/validate.mjs --zip=path/file.zip`, extracts to a `.ktube-validate-stage/` temp dir under `try/finally` cleanup so re-runs don't carry stale bytes, then re-hashes contents).
* `RELEASING.md` — distribution-agnostic channel guide. Documents GitHub Releases, asset CDN, MU-plugin upload, sysadmin tar pipeline — all consuming the same `theme-manifest.json`. Defines the ZIP-name contract (`ktube-X.Y.Z.zip`), the manifest schema, and the `php scripts/verify-manifest.php` consumer pattern.
* `.github/workflows/build-enforcement.yml` — rewritten to validate `theme-manifest.json` against the rebuilt `dist/` (instead of asserting `git diff dist/` is empty). GitHub is now **one of several valid consumers** of the manifest, not the gatekeeper of whether a build is shippable.
* `package.json` — adds `build`, `build:manifest`, `manifest`, `validate`, `release`, `test`, `test:phpunit` scripts + `vitest`/`jsdom` devDeps + Node 20+ engines.

### Phase 9b — Privacy disclosure (shipped 2026-06-21)
* `includes/privacy.php` surface: `ktube_resolve_privacy_page_id()` (Customizer-configured id → slug `privacy` fallback → 0); `ktube_get_privacy_page_url()`; `ktube_should_show_privacy_badge()` (gated on age-gate active + page resolves); `ktube_privacy_badge_copy()` (adaptive sentence with sanitized `min_age` via `ktube_sanitize_cols(1, 99)`); `ktube_privacy_summary()` (7-row auto-doc data sheet: min age / localStorage key / cookie name / retention TTL / underage redirect / RTA meta / theme preference).
* Customizer integration: new section `ktube_privacy` (priority 34) with single `ktube_privacy_page_id` setting (`dropdown-pages`, default 0, `allow_addition=false`). Operators who create a Page titled "Privacy" with slug `privacy` get working flow even without touching the Customizer.
* `theme_page_templates` filter drops `page-privacy.php` into the Page editor dropdown.
* `template_include` filter auto-applies the privacy template to `is_page('privacy')` when no template was explicitly chosen (short-circuits on `_wp_page_template` meta to honor explicit choices).
* `page-privacy.php` (NEW) — `Template Name: Privacy`. Renders the auto-doc data sheet above any operator-supplied `the_content()`. Last-updated date with explicit `'F j, Y'` format (locale-stable).
* `template-parts/privacy/summary.php` (NEW) — iterates summary rows, semantic `<dl>` markup, `wp_kses` allowlist (`<meta http-equiv content>` only), active/inactive state badges.
* `footer.php` modification — `ktube-privacy-badge` block gated on `function_exists('ktube_should_show_privacy_badge')` (partial-load safety) and the helper; adaptive icon + label; aria-label adds "— read our privacy notice" suffix for screen-readers.
* `functions.php` modification — `require_once includes/privacy.php`; `add_action( 'init', 'ktube_register_privacy', 16 )`.
* `assets/src/scss/components/_privacy.scss` (NEW) — pill-shaped badge (`color-mix()` token usage), `<dl>` data sheet layout with mobile breakpoint; registered in `main.scss` after `_age-gate`.
* `tests/phpunit/bootstrap.php` — minimal TestCase stubs expanded with `assertArrayHasKey`, `assertNotNull`, `assertIsBool`, `assertIsString`; `_n()` stub; test-controllable post map (`set_post_test()`); smarter `WP_Query` stub that consults the post map by `name` query arg; require chain `customizer → age-gate → privacy`.
* `tests/phpunit/ktube/PrivacySummaryTest.php` (NEW) — 14 tests: summary row count + active count, age-gate row activation, localStorage/cookie values, TTL day/days pluralization, resolve_privacy_page_id branches (zero default / configured hit / configured miss / wrong post_type / slug fallback / configured-preferred), get_privacy_page_url, badge gating matrix, adaptive copy matrix, age substitution, row key completeness, RTA row meta tag.

### Phase 8 — Audit nits closed (shipped 2026-06-21)
**Theme toggle UI:**
* `header.php` emits `<button class="ktube-theme-toggle" type="button" aria-pressed="false" aria-label="Switch to dark mode">` with three `.ktube-theme-toggle__sun|__moon|__label` spans; server reads `ktube_resolve_initial_theme()` so initial `aria-pressed` + `aria-label` are correct on first paint (no FOUC).
* `assets/src/js/dark-mode.js` — `paintToggle(el, info)` + `paintAllToggles(info)` repaint every `.ktube-theme-toggle` on the page whenever the theme changes (`ktube:themechange` event + DOMContentLoaded); `bindToggleButton(el)` is idempotent (`dataset.ktubeBound === '1'` guard so header re-renders don't double-bind).
* `assets/src/scss/components/_theme-toggle.scss` — new file, registered in `main.scss` after `_dark-mode.scss`. CSS-only sun/moon icons via `mask-image: radial-gradient(...)` + transition on `transform/opacity`. `[data-theme="dark"]` flips colors via the existing token surface.
* `wp_body_open` hook in `functions.php::ktube_inline_theme_bootstrap` invokes a `KTUBE.themeToggle.bindAll()` helper after the body opens so re-rendered headers get a fresh binding.

**Age-gate fallback UI:**
* `_age-gate.scss` adds `dialog[data-ktube-fallback="true"] { display: flex; flex-direction: column; position: fixed; inset: 0; width: 100vw; height: 100vh; max-width: none; max-height: none; margin: 0; padding: var(--ktube-space-lg); border: none; background: transparent; z-index: 9999; }` so the fallback renders natively positioned (NOT display:none from the `<dialog>` body-shim).
* `assets/src/js/age-gate.js` wraps the modal body in `<div class="ktube-age-gate__dialog">` (used as the visual card), exposes `attachManualTabTrap(dialog)` for the fallback path that runs first-/last-child focus cycling on Tab when `showModal()` rejects, and keeps `setBackgroundInert(true)` so screen-readers + Tab skip the gated background regardless of native vs fallback.

**Customizer — CSS checksum guard:**
* `ktube_compute_customizer_checksums()` in `includes/customizer.php` returns `['total' => sha256(full_css), 'per_key' => [setting_id => sha256(substring)]]` where each substring matches the regex returning a `;<value>|@<block>` shape for color tokens (so JS-side `mirrorSubstring` extracts the matching portion and appends `|@<block>` itself to produce the identical hash string).
* `customize_preview_init` action emits `window.ktubeCustomizerSettingChecksums = {total,per_key}` via `wp_add_inline_script` BEFORE `ktube-customize-controls` loads.
* `customize-controls.js::verifyChecksum()` reads `window.ktubeCustomizerSettingChecksums`, hashes its own `buildCss()`, walks per-key hashes, and on any drift `console.warn`'s once with `[ktube-css-checksum-drift] divergent: [...]` listing just the diverged setting ids. Silent on match.
* `ktube_build_customizer_css()` now sanitizes `ktube_grid_cols_*` / `ktube_thumb_cols_*` on read (defense in depth) so a hand-edited DB row that lands outside clamp still produces valid CSS.

### Phase 6-C — GDPR cookie consent banner + jurisdictional geo redirect (shipped 2026-06-21)
* NEW `includes/gdpr.php` — module surface. Helpers: `ktube_gdpr_active()`, `ktube_gdpr_blocked_countries()` (CSV sanitizer w/ dedupe), `ktube_gdpr_resolve_country()` (operator-filter-handoff, default `''` so un-plumbed redirects never auto-block), `ktube_gdpr_is_country_blocked()`, `ktube_gdpr_should_redirect_visitor()`, `ktube_gdpr_consent_categories_default()`, `ktube_gdpr_consent_for_category($cat, $blob=null)`, `ktube_gdpr_render_banner()`, `ktube_gdpr_enqueue_consent()`, `ktube_gdpr_enforce_redirect()` (template_redirect priority 1, wp_redirect 302 + exit), `ktube_gdpr_customize_register()` (Customizer section `ktube_gdpr` priority 32, 5 settings). Default blocked-country payload = EU 27 + EEA 3 + UK = 31 ISO 3166-1 alpha-2 codes (operators override via CSV). NO shipped IP-geo library — operators wire `ktube_gdpr_resolve_country` filter to their provider (Cloudflare `HTTP_CF_IPCOUNTRY`, Apache `GEOIP_COUNTRY_CODE`, MaxMind GeoLite2, etc).
* NEW `assets/js/gdpr-consent.js` (vanilla IIFE + ESM exports). Boot reads `window.ktubeGdprConsentData` → hydrates toggle defaults from server-injected categories → on first visit unhides the bottom banner + wires the 3 button listeners (Reject / Accept-Selected / Accept-All) → writes the consent blob to BOTH `localStorage['ktube-gdpr-consent']` AND `cookie['ktube_gdpr_consent']` (`encodeURIComponent(json)` + `SameSite=Lax; Secure`, TTL = 180 days default). Dispatches `window.ktube:gdprconsentchange` CustomEvent so analytics/marketing plugins can listen via plain JS without depending on a custom cookie name.
* NEW `tests/phpunit/ktube/GdprConsentTest.php` — 18 test methods: master toggle defaults + on/off, default country list shape (31 codes, spot-checked EU/EEA/UK), CSV sanitizer dedupe + uppercase + invalid drop, resolver default empty + filter-driven uppercase + malformed filtered, blocked-country membership (case-insensitive), `should_redirect_visitor` AND-chain (active + blocked + not-admin) + false when country unresolved (defensive), consent default categories (`essential=true` always, analytics/marketing per-theme_mod), blob-derived consent lookup + unknown category → false, privacy summary emits Cookie consent row when GDPR active + omits when GDPR disabled, `enforce_redirect` calls `wp_redirect` with target + 302 + default URL fallback when configured URL empty + no-op when no block.
* UPDATED `functions.php` — `require_once KTUBE_DIR . '/includes/gdpr.php'`; `add_action('init', 'ktube_register_gdpr', 18)`.
* UPDATED `includes/privacy.php` — `ktube_privacy_summary()` appends a `Cookie consent` row when `ktube_gdpr_active()` is true (documents `localStorage = ktube-gdpr-consent; cookie = ktube_gdpr_consent`).
* UPDATED `tests/phpunit/bootstrap.php` — `require_once $ktube_test_gdpr` in the file-load chain; new `wp_redirect($location, $status=302): bool` stub that records `{'location','status'}` into `$GLOBALS['__ktube_last_redirect']` (does NOT exit, so tests can proceed past the redirect emit); new `reset_redirect_test()` helper; new `assertContains()` stub (string + array + Countable haystack).
* Compliance spec critical divergences: §0.11 currently lists 8 items; GDPR modal + geo redirect is not in the §0.11 list because GDPR cookie consent wasn't called out in the original brief. Tracked here as a Phase 6-C addition; updates §6 External Architecture Reference "Age-gate cascading jurisdictions" row (now closed — IP-based redirect ships) and "AOIC / RTA convergence" footnote (compliance pattern now extends to cookie consent + geo).

### Phase 9e — Blog Article JSON-LD `articleBody` + `keywords` uplift (shipped 2026-06-21)
* UPDATED `includes/seo/schema.php` — `ktube_render_blog_article_schema()` refactored to delegate to a new testable build helper `ktube_build_blog_article_schema( WP_Post $post ): array` (mirrors Phase 9d's VideoObject pattern). Build helper emits:
  * All pre-existing required fields (headline, description, datePublished, dateModified, author, publisher, mainEntityOfPage, image) — unchanged bit-for-bit.
  * `articleBody` from `wp_strip_all_tags( $ktube_post->post_content )` — emitted ONLY when non-empty; draft stages + empty imports OMIT the key so the schema doesn't ship `articleBody:""` as a quality-signal.
  * `keywords` from `get_the_terms( $ktube_post, 'post_tag' )` mapped to a dedup'd `string[]` via the term's `name` field — emitted ONLY when ≥1 term; suppresses on zero tags. `array_unique` + `array_values` reindex so JSON emits `[…]` not `{…}`.
  * Apply `ktube_blog_article_schema` filter on the way out (last call before return; receives `$ktube_data` + `$ktube_post` so partners can mutate `articleBody` / `keywords` after our normalization, same as `ktube_video_object_schema`).
* NEW `tests/phpunit/ktube/BlogArticleSchemaTest.php` — 13 test methods covering: build early-bail (4 non-WP_Post shapes), full required fields with no body/tags (regression-preserves all existing Article fields), `articleBody` emit / strip-html-tags-incl-script / omit-when-empty (×3), `keywords` emit-from-post-tag / omit-when-no-tags / sourced-only-from-post_tag-not-category / dedup-repeats (×4), filter extends data + filter removes articleBody (×2), render emitter non-blog gate + emit ld+json-with-articleBody-and-keywords (×2), existing-field regression (headline+description+dating) + image field preserved when thumb set (×2).
* UPDATED `tests/phpunit/bootstrap.php` — two latent stub gaps surfaced by Phase 9e tests BOTH addressed:
  1. NEW `get_the_author_meta( $key = '', $user_id = 0 )` stub. Was undefined; the pre-Phase-9e `ktube_render_blog_article_schema()` already called it but no test ever exercised the author path, so the gap persisted since Phase 9b. Returns `'Test Author'` for `display_name`/`''`, `(string) $user_id` for `'ID'`, `'author@example.test'` for `'user_email'`, `''` otherwise.
  2. UPGRADED `wp_strip_all_tags()` stub. Was `trim( strip_tags( $s ) )`; now prepends a `preg_replace( '#<(script|style|noscript)\b[^>]*>.*?</\1\s*>#mis', '', $s )` so script/style/noscript BODY text is also stripped (real WP's `wp_strip_all_tags` carries the same internal regex; PHP's native `strip_tags` only removes wrapping tags, not inner text). PrivacySummaryTest and VideoObjectSchemaTest excerpt paths are unchanged because their fixtures carry no `<script>` content (the regex no-ops on plain text).
* **Verification:** `php tests/phpunit/run.php` runs ~**537 pass / 0 fail** (was 524 pass / 0 fail after Phase 5-A). All 13 BlogArticleSchemaTest methods pass; VideoObjectSchemaTest and PrivacySummaryTest spot-checks confirm no collateral regression from the bootstrap upgrades. PHP `-l` lint clean on `includes/seo/schema.php`, `tests/phpunit/bootstrap.php`, `tests/phpunit/ktube/BlogArticleSchemaTest.php`.
* §0.3 row 3.2 now reads richer-rich-snippet confirmation (Article schema carries `articleBody` + `keywords` for SERP elevation). §5-B #10 flipped to ✅ shipped.

### Phase 9 — `feat/wps-compat-audit` closed + Polish
* Verified against plugin source at `…\WP-Script\Plug-ins\clean-tube-player`. No `ktube_*` hook consumers in plugin → contract is advisory; `ktube_player_markup` filter is documented as future-proofing.
* `includes/wps-compat/wps-player.php` — `ktube_has_wps_player()` strict check via `class_exists('CTPL', false)` + `defined('CTPL_VERSION')` (the only signals confirmed in source). `ktube_force_wps_player_active` filter retained for MU-plugin force-flag; `function_exists('wps_player')` kept as a cheap safety net. **`CLEAN_TUBE_PLAYER_VERSION` demoted** from runtime detection chain (no audited plugin defines it); explained in header NOTE + orphan-reference grep pointer in post-function block. **Sibling WPS plugins documented** in header (Mass Importer, Browser, Subscription, Adult Mass Videos Embedder) as audit-surface for future authors.
* `template-parts/video/player-wrap.php` — `ob_start()`/`ob_get_clean()` captures native markup; emits `apply_filters('ktube_player_markup', $ktube_native_markup, $ktube_post)`; **empty-string fallback** returns `$ktube_native_markup` (no silent blank player when consumer returns `''`). **`ktube_before_player` moved INSIDE `ob_start()`** capture region so third-party listeners that emit chrome get buffered and are replaceable by the filter. **Filter signature aligned** across both files with canonical `$ktube_native_markup` parameter name + `WP_Post|null` post. **`$ktube_post = get_post()` cached once at file top**, reused for before_player / filter / after_player (single source of truth).

---

## 2. Current Build / Customizer Briefing at a Glance

| Setting | Default | Range / Choices | Transport |
| --- | --- | --- | --- |
| `ktube_grid_cols_desktop` | 4 | 2-6 | postMessage |
| `ktube_grid_cols_tablet`  | 3 | 1-4 | postMessage |
| `ktube_grid_cols_mobile`  | 2 | 1-3 | postMessage |
| `ktube_thumb_cols_desktop` | 3 | 2-4 | postMessage |
| `ktube_thumb_cols_mobile`  | 2 | 1-3 | postMessage |
| `ktube_color_*_{light,dark}` | token defaults | `#abc`/`#abcdef` | postMessage |
| `ktube_theme_default` | `auto` | `auto` / `light` / `dark` | refresh |
| `ktube_age_gate_enabled` | `false` | bool | refresh |
| `ktube_age_gate_min_age` | 18 | 1-99 | refresh |
| `ktube_age_gate_duration_days` | 30 | 1-365 | refresh |
| `ktube_age_gate_redirect_url` | `https://www.google.com/` | URL | refresh |
| `ktube_rta_enabled` | `false` | bool | refresh |

Current asset layout (after Phase 0-A 2026-06-21):

- `assets/css/main.css` — hand-authored plain CSS (no preprocessor)
- `assets/js/*.js` — hand-authored vanilla JS with ESM named exports (vitest-resolvable)
- `assets/vendor/videojs/{video.min.js, video-js.min.css}` — vendored Video.js 8 core (Apache-2.0 LICENSE)
- `node_modules/` — dev-only (vitest, jsdom); not shipped to operators

Sizes can be inspected at any time with:

```
wc -c assets/css/main.css assets/js/*.js assets/vendor/videojs/video.min.js assets/vendor/videojs/video-js.min.css
```

Video.js is no longer a built chunk — it's loaded directly from the vendored core on `is_singular('video')` only. `videojs-http-streaming` (HLS) is intentionally not vendored; the current release plays H.264 progressive mp4 only.

---

## 3. Reviewer-Flagged Nits Inventory (open + recently closed)

> The 2026-06-21 audit pass closed an older set of nits that are listed below as ✅ for traceability. The still-open items are flagged ❌ / ⚠️. Each → links to its §5-A or §5-B counterpart so the doc reads as one truth.

### Customizer (handle-enqueued guard)
* ✅ Closed (Phase 8 audit, 2026-06-21). `ktube_print_inline_customizer_vars()` now gates on BOTH `wp_style_is('ktube-main','registered')` AND `wp_style_is('ktube-main','enqueued')` (belt-and-suspenders; enqueue implies register in WP semantics but the dual gate is defensive and locks the test). Bootstrap stubs controllable via `set_style_state_test()` + recording stubs for `wp_add_inline_style` / `wp_add_inline_script`. See §5-B ✅ Customizer hardening.

### Schema (Article richness)
* ✅ Shipped (Phase 9e, 2026-06-21). `ktube_render_blog_article_schema()` now emits `articleBody` (from `wp_strip_all_tags the_content`; skipped when empty so drafts don't ship `articleBody:""`) + `keywords` (from `post_tag` terms via `get_the_terms` + `array_unique`; skipped when zero tags). `BlogArticleSchemaTest.php` 13 methods.

### Schema (OG video type)
* `ktube_render_open_graph_meta()` hard-maps `og:type="article"` for non-video singles. Discuss whether `og:type="video.other"` is the more correct mapping for `is_singular('video')`. Facebook OG spec accepts it; SEO crawlers vary on interpretation.

### Player (i18n + bitrate)
* `assets/src/js/player.js` is a thin Video.js wrapper; no quality/bitrate selection UI. HLSeo options not configured. Cover via a custom skin or `--ktube-quality-*` Customizer radios.

### Dark-mode (live preview doesn't localize)
* ✅ **closed 2026-06-21.** `ktube_enqueue_dark_mode` no longer emits `wp_add_inline_script('ktube-dark-mode', 'window.ktubeThemeData = ...')`. The site-wide inline JSON was redundant — `functions.php::ktube_inline_theme_bootstrap` resolves the initial `data-theme` server-side at `wp_head` priority -1 (BEFORE the dark-mode.js bundle parses), and `dark-mode.js::getTheme()` reads `documentElement.data-theme` directly. The `window.ktubeThemeData` injection is preserved in the Customize preview iframe only (via `ktube_enqueue_customize_controls`) because controls.js's post-message bootstrap needs the Customizer default at load.

----

## 4. Phase Inventory (from the brief, mixed)

### Phase 7b — Privacy disclosure — ✅ SHIPPED (implemented as Phase 9b, see §1)
* Footer surface badge via `ktube_should_show_privacy_badge()` (gated on age-gate active + page resolves); links to `/privacy` page.
* Privacy page documents: localStorage key (`ktube-age-confirmed-on`), cookie name (`ktube_age_verified`), retention period (Customizer `ktube_age_gate_duration_days`), RTA tag presence.
* See Phase 9b in §1 for the full inventory: `includes/privacy.php` + `page-privacy.php` + `template-parts/privacy/summary.php` + Customizer `ktube_privacy_page_id` + footer badge + 14-test `PrivacySummaryTest` suite.

### Phase 10 — GitHub Actions CI matrix
* `.github/workflows/ci.yml` matrix across PHP 8.3 + Node 20.
* Runs: `composer install`, `npm run build`, `npm test`, `vendor/bin/phpunit`.
* Failure on lint / test / typecheck / build-blocking-size.

### Phase 11 — Full Customizer audit
* Unit-test `ktube_register_customizer` boot path.
* Snapshot-test the rendered Customizer JSON config against a golden.
* Auditability: tracing handler hooks `customize_save_after` for theme_mod persistence verification.

### Phase 12 — Member / auth (out of scope unless brief expands)
* Subscriber-only content gates, paid-membership tier, paywall. Not in current brief.

### Phase 13 — Multi-search + filter
* Faceted search via `?genre=` / `?channel=` query vars; archive-page JS filter UI.

### Phase 14 — Performance pass
* Defer non-critical CSS (lightbox only enqueued on photo singles).
* Image-format negotiation (`ktube-card.avif` → `jpg` fallback via WP `wp_get_attachment_image_srcset` filter).
* Critical CSS inlining for above-the-fold `<header>` markup.

---

## 5. Where To Pick Up

> As of 2026-06-21 reconciliation pass: §0-A's 8 critical divergences are now mostly closed — 6 ship outright (Phases 0-A / 1-A / 6-B / 8-A / 8-B / 9d), Phase 14-A PSI workflow ships (no captured baseline yet), and Phase 11-A splits 2/3 (repo push shipped this session; conventional-commits squash + `RELEASING.md` perf-caveat language still open). §0 spec-gap audit + §5-A "shipped" list + the two contradictory §5-B sub-lists have been collapsed into one truth.

### §5-A Critical divergences (from §0 audit — work-blockers, ordered by impact)

1. ✅ **Phase 0-A — Tech-stack reversal** — shipped 2026-06-21. Hand-authored plain CSS in `assets/css/main.css`; vendored Video.js core in `assets/vendor/videojs/` (Apache-2.0 LICENSE + `VENDORED.json` byte-identity contract + `tools/validate.mjs` enforcement); zero `npm install` activation confirmed; ESM `export { ktubeCpX as X }` alias blocks allow vitest imports; `AssetLayoutTest` anchors the layout. **`videojs-http-streaming` intentionally NOT vendored** — HLS deferred to §5-B Player depth followup. See §1 Phases Shipped for the full inventory.
2. ✅ **Phase 1-A — Mass Importer field-map cross-check** (brief §2.3, §5.7) — shipped 2026-06-21. Map extended 13→16 keys (added `views`, `rating`, `source_url`, `external_id`) per public docs.wp-script.com parameter tables; `views` and `rating` engagement counters upgrade the ktube-only reserved bag. **NEW:** `includes/wps-compat/mass-importer.php` lineage header now explicitly states the map is NOT independently verifiable against the closed-source plugin binary (no public GitHub); verification recipe documented for downstream maintainers. `includes/wps-compat/importer-adapter.php` refactored to source its sync map from `ktube_importer_key_map()` (single source of truth) so a future map extension auto-flows to the adapter. NEW file `includes/wps-compat/db-indexes.php` documents the index policy at 10k+ scale: do NOT modify core `wp_postmeta` schema (UP core safety), defer `wp_ktube_video_stats` custom table to Phase 14. NEW `includes/meta.php` registrations for `_ktube_rating`, `_ktube_source_url`, `_ktube_external_id`. NEW tests `MassImporterCompatTest.php` (8 test methods) cover map/registration/blueprint/drain/discover invariants. See §C1 below for the Phase 1-A inventory.
3. ✅ **Phase 6-B — RTA label format fix** (brief §3.8) — shipped 2026-06-21. `ktube_render_rta_meta()` in `includes/age-gate.php` now emits `<meta name="rating" content="RTA-5042-1996-1400-1577-RTA">` (ASACP RTA register format). Mirrored updates: header docblock + Customizer control description (operator-facing string) + `includes/privacy.php` privacy summary row `value` so the data sheet stays in sync with the actually-emitted `<head>` content + `tests/phpunit/ktube/PrivacySummaryTest.php` assertion flipped from `http-equiv="RTA"` to `name="rating"`. NEW `tests/phpunit/ktube/RtaLabelMetaTest.php` (8 test methods): byte-exact `assertSame` of the canonical RTA register label, regression guard against the old `http-equiv="RTA"` AND `content="restrict"` formats, start-/end-substring assertions, double-invoke idempotence + count guard. §0.3 RTA-format row also flipped to ✅ in the spec-gap audit table.
4. ✅ **Phase 8-A — Homepage editorial Customizer** (brief §6) — shipped 2026-06-21. New `ktube_homepage` Customizer section (priority 36) with `ktube_home_h1` (text + `sanitize_text_field`) + `ktube_home_description` (textarea + `sanitize_textarea_field`). Helpers `ktube_get_home_h1()` / `ktube_get_home_description()` return raw stored values (sanitize lives on the WP-side callback). `index.php` renders a `<header class="ktube-home-header">` block on `is_front_page() || is_home()` when at least one value is non-empty. `ktube_render_home_meta_description()` emits `<meta name="description">` via `wp_head` priority 1 (gated: front-page/home + non-empty). NEW `tests/phpunit/ktube/HomepageCustomizerTest.php` (14 methods) covers section/setting/control registration, helper getters, sanitize_callbacks, meta-description emit gating + esc_attr escaping. Bootstrap stubs extended: `WP_Customize_Manager` now records onto public `ktube_sections/ktube_settings/ktube_controls`, `add_action()` records into `__ktube_actions` (was a no-op), `is_singular/is_front_page/is_home` are test-controllable via `set_is_front_page_test`/etc., and `sanitize_text_field`/`sanitize_textarea_field` stubs added so customizer max callbacks don't fatal.
5. ✅ **Phase 8-B — 2257 / DMCA / Terms page templates** (brief §3.8) — shipped 2026-06-21. 3 new page templates `page-2257.php` / `page-dmca.php` / `page-terms.php` (chrome only: operator-supplied page title OR canonical kind-label fallback heading + the_content() slot + a non-authorative `ktube does not provide legal advice` meta-notice). ZERO legal text authored by ktube — operators populate jurisdiction-specific clauses via the WP editor. NEW helper module `includes/compliance-pages.php` exposes a 3-kind registry (`ktube_compliance_kinds()`) + mirror resolve helpers (`ktube_resolve_compliance_page_id( $slug )`, `ktube_get_compliance_page_url( $slug )`, `ktube_compliance_default_heading( $slug )`) + a 4-slot footer helper (`ktube_get_compliance_footer_slots()` — Privacy → 2257 → DMCA → Terms in canonical order, Privacy slot auto-suppressed when Phase 7b badge already renders the same URL). NEW Customizer section `ktube_compliance` (priority 35, sits below Privacy) with 3 dropdown-pages controls (`ktube_2257_page_id`, `ktube_dmca_page_id`, `ktube_terms_page_id`). NEW `theme_page_templates` filter entries + NEW `template_include` filter auto-applies the right template when `is_page($slug)` is true AND there's no explicit `_wp_page_template` meta. NEW `tests/phpunit/ktube/CompliancePagesTest.php` (14 test methods covering kinds registry, 5 resolve branches, URL helpers, default heading mapping, footer slot order, badge-suppression, filter hook + teardown, template-dropdown registration). UPDATED `footer.php` to render a 4-slot compliance nav alongside the existing Phase 7b badge. UPDATED `tests/phpunit/bootstrap.php` — upgraded `get_post()` stub to consult `__ktube_posts` map; upgraded `get_permalink()` stub to deterministic per-id URL; added full filter-stub chain (`add_filter`/`apply_filters`/`remove_filter` with stable-priority + insertion-order tie-breaker + `reset_filters_test()` helper). **Closed as collateral:** `PrivacySummaryTest::test_resolve_privacy_page_id_prefers_configured_over_slug_fallback` (Expected 42 / Actual 7 fixture) — same `get_post()` stub root cause closed by the Phase 8-B bootstrap upgrade; now PASSes. (Cross-reference tied to the same window hardener that ships §5-B ✅ Customizer hardening — see `tests/phpunit/bootstrap.php`'s upgraded `get_post()` chain.)
6. ✅ **Phase 9d — VideoObject JSON-LD on video singles** (brief §6) — shipped 2026-06-21. NEW `ktube_render_video_object_schema()` in `includes/seo/schema.php`, registered at wp_head priority 2 alongside Article + ImageGallery emitters, gated on `is_singular('video')`. NEW testable `ktube_build_video_object_schema( WP_Post $post ): array` extracted for filterability (filter `ktube_video_object_schema`). Shape: @context=https://schema.org, @type=VideoObject, name (= title), description (= stripped excerpt), contentUrl (prefers `_ktube_video_url`, fallback to `_ktube_embed_url`; returns [] when neither set, so empty VideoObjects are never emitted), thumbnailUrl[] (featured image → `_ktube_thumb_url` → empty array), uploadDate (`get_the_date('c')`), duration (PT-format from seconds; emits `PTH0S` for zero, never omitted so GSC doesn't flag it), encodingFormat (MIME inferred from contentUrl extension: mp4/m4v/webm/ogg/ogv/mov/m3u8; unknown → video/mp4), mainEntityOfPage (WebPage @id — defensive guard so freshly-staged draft id 0 doesn't emit `?p=0`), interactionStatistic (InteractionCounter w/ WatchAction + userInteractionCount, ONLY when `_ktube_views > 0` — skips serialization of zero-count quality-signal). NEW helpers `ktube_iso8601_duration( int $seconds )` + `ktube_infer_encoding_format( string $url )`. NEW `tests/phpunit/ktube/VideoObjectSchemaTest.php` (19 test methods covering build early-bail × 2, contentUrl precedence, thumbnail precedence × 3, ISO 8601 duration × 5, encodingFormat × 8, interactionStatistic gating × 3, filter hook, render-emitter × 3, mainEntityOfPage × 2). UPDATED `tests/phpunit/bootstrap.php` — added assert* stubs (`assertStringEndsWith`, `assertNotContains`, `assertIsArray`, `assertFileExists`, `assertNotFalse`); upgraded `get_post_meta`, `get_post_thumbnail_id`, `wp_get_attachment_image_url`, `get_permalink`, `get_the_date`, `get_the_modified_date`, `wp_strip_all_tags` to be test-controllable; added `set_post_meta_test` / `reset_post_meta_test` / `set_post_thumbnail_test` / `reset_post_thumbnails_test` helpers; loaded schema.php into the bootstrap chain.
7. ⚠️ **Phase 11-A — Conventional-commits + perf-caveat (brief §10)** — Repo push shipped this session (`403287d..cc57916 main -> main`, 25 commits rebased on top of `403287d` initial, upstream tracking bound, ahead/behind 0/0). **Still open:** squash the local work into conventional-commit messages (`feat:`/`fix:`/`perf:`/`docs:`/`a11y:`); add the perf-caveat language to `RELEASING.md`. ~30 minutes.
8. ✅ **Phase 14-A — PSI baseline + CI enforcement** (brief §4, §10) — shipped 2026-06-21. NEW `.github/workflows/psi.yml` calls the public PageSpeed Insights v5 REST API via curl directly (NO Lighthouse-npm dependency, NO headless Chrome). Three independent category runs (performance / accessibility / best-practices) emit raw JSON, a compare step gate-fails the job if any score falls below `KTUBE_THRESHOLD` (default 90, configurable via workflow_dispatch). Workflow triggers on pushes that touch assets/, includes/, functions.php, tools/validate.mjs (rule-out cosmetic commits) + pull_request open/sync + manual dispatch. Inputs: `url`, `strategy` (mobile|desktop), `threshold`. Optional `PSI_API_KEY` env bumps rate limits; falls back to no-key mode if absent. Raw JSON uploaded as run-id artifact on every run (success or failure). Honored archive-only privacy: secrets never echoed, errors never leak payload.

### §5-B Secondary polish (after §5-A items ship)

> §5-B below is the residue after the §0-A spec-gap audit + §5-A "shipped" list have been reconciled. Items marked ✅ reflect phases that closed during the same 2026-06-21 audit pass; items marked ❌ are still open. The pre-reconciliation doc carried two contradictory §5-B sub-lists; this section is the single canonical replacement.

✅ **Customizer hardening** — shipped 2026-06-21. `ktube_print_inline_customizer_vars()` now gates on BOTH `wp_style_is('ktube-main','registered')` AND `wp_style_is('ktube-main','enqueued')`. Bootstrap stubs controllable via `set_style_state_test()` + recording stubs for `wp_add_inline_style` / `wp_add_inline_script`. `tests/phpunit/ktube/PrintInlineCustomizerVarsTest.php` covers 8 cases: pipe-when-both, noop-when-only-one, noop-when-neither, root-block-token-presence, customizer-settings-echoed-in-css, sha256-hex-per-key-checksums, out-of-range-clamp-on-read.

✅ **Schema richness (`articleBody` + `keywords`)** — shipped 2026-06-21 (Phase 9e). `ktube_render_blog_article_schema()` now emits `articleBody` (from `wp_strip_all_tags the_content`; skipped when empty) + `keywords` (from `post_tag` terms via `get_the_terms` + `array_unique`; skipped when zero tags). `tests/phpunit/ktube/BlogArticleSchemaTest.php` 13 methods.

✅ **Dark-mode cosmetic cleanup** — shipped 2026-06-21. `ktube_enqueue_dark_mode` in `includes/setup.php` no longer `wp_add_inline_scripts` `window.ktubeThemeData = ...`. The Customizer preview iframe's separate emission via `ktube_enqueue_customize_controls` is preserved (controls.js needs the default at load). Doc comment in setup.php now explains the precedence (`ktube_inline_theme_bootstrap` at `wp_head` priority -1 sets `<html data-theme>` server-side; `dark-mode.js::getTheme()` reads the resolved value with no localization needed).

❌ **Schema (OG video type)** — decision pending. `ktube_render_open_graph_meta()` hard-maps `og:type="article"` for non-video singles. Decide whether `og:type="video.other"` is the more correct mapping for `is_singular('video')`. Facebook OG spec accepts it; SEO crawlers vary on interpretation. ~30 minutes + a regression test.

❌ **Player depth** — pending (~1 day). Quality/bitrate selection UI; `videojs-http-streaming` lazy-import behind `Hls.isSupported()` probe behind a Customizer flag (re-opens Phase 3b's HLS trade-off).

❌ **Phase 10 — CI matrix** — pending (~2 hours). `.github/workflows/ci.yml` matrix over PHP 8.3 + Node 20 running `composer install` / `npm test` / `npm run test:phpunit` / `npm run validate`. Currently `.github/workflows/build-enforcement.yml` validates the manifest post-build; the test matrix itself is still pending.

❌ **Phase 11 — Customizer audit** — pending (~1 day). Unit-test `ktube_register_customizer` boot path; snapshot-test rendered Customizer JSON; trace `customize_save_after` hook to verify theme_mod persistence.

❌ **Phase 14 — Performance pass** — pending (~1 day). Defer lightbox CSS (only enqueued on photo singles); image-format negotiation (`ktube-card.avif` with `jpg` fallback); critical CSS inlining for above-the-fold `<header>`. Largest single pull on the §5-A #7 PSI baseline once captured.

❌ **§0.5 residual a11y micro-tasks** — pending (~1 hour). `prefers-reduced-motion` keyframes fallback; one-h1-per-page assertion; decorative `alt=""` assertion; end-to-end age-gate keyboard walkthrough (manual, captured to PR).

❌ **§0.7 cosmetic refactors** — pending, low priority. Split `includes/post-types.php` into `cpt-blog.php` / `cpt-photo.php`; extract `includes/customizer/*` subfolder layout replacing flat `includes/customizer.php`; lift the age-gate modal HTML out of `assets/js/age-gate.js` into `template-parts/age-gate/modal.php`.

After §5-B closes, ktube graduates from the spec closeout pass to operator-configured production roll-out. The largest single miss is Phase 14 perf (AVIF/WebP + lightbox defer + critical CSS), which directly affects the §5-A #7 PSI baseline scores once captured against a deployed URL.
---

## 6. External architecture reference — 2026-06-21 knowledge capture

> Lightweight, non-ktube-phase research note capturing patterns from major high-traffic video platforms (xVideos-scale; sources: Cloudflare Radar, BuiltWith, AWS multi-CDN webinars, schema.org). Every row is a **gap** between ktube's current behavior and a hardened video-pipeline pattern; rows link to a future Phase or sub-Phase where the change would land. Nothing here is "shipped ktube work" — it is awareness documentation so future maintainers don't reinvent the wheel when scaling.

| Concern | ktube state (today) | Hardened pattern | Where it would land |
| --- | --- | --- | --- |
| Adaptive bitrate / chunked delivery | Single-mp4 progressive; Phase 3b core imports `video.js/core` without `videojs-http-streaming`; `.m3u8` won't play | HLS / DASH; pre-transcoded 240p–4K variants; signed chunked segments; ABR client switching on throughput | §5.6 (Player depth) — re-open by lazy-importing `videojs-http-streaming` behind a `Hls.isSupported()` probe behind a Customizer flag |
| Signed video URLs / hot-link prevention | None — `data-trailer-url` and player srcs are emitted in clear HTML | HMAC-signed time-limited tokens (rotation-aware); referer-binding; constant-time compare on the origin | New Phase 15 — Authenticated video delivery |
| Multi-frame scrub thumbnails | Single static poster image; hover-trailer path swaps in one preview image or short mp4 | Animated sprite sheets (single image, multiple frame regions); AVIF with WebP fallback; lazy-decoded via `<picture>` srcset | §2 refine (Phase 2 hover-trailer followup) |
| VideoObject JSON-LD schema | Article + ImageGallery + OG/Twitter Card + **VideoObject on video singles** (Phase 9d, 2026-06-21) | `schema.org/VideoObject` with `contentUrl`, `thumbnailUrl`, `uploadDate`, `duration`, `encodingFormat`, `interactionStatistic` | Closed under §5-A #6 + §0.6. |
| Multi-CDN failover | Single origin (ktube theme host), single CDN (operator choice) | Round-robin across two-tier CDNs with health probes; per-asset path prefix | Out of theme scope — operator / hosting concern |
| Hot-link protection teaser previews | `template-parts/video/card.php` emits `data-trailer-url` straight — visible to scrapers | Tokenized preview URL with per-session nonce; referer-check on the streaming endpoint | §2 refine |
| Browser-availability probing of player | Static import at script register time | Runtime feature detection (`Hls.isSupported()`, MSE, AV1) before pulling player chunks | §5.6 (Player depth) |
| Edge cache for video metadata | None — page cache only | Varnish / surrogate-key targeting per video id; cache stampede guards | Out of theme scope — operator concern |
| Aggressive cache headers + immutable | `ktube_asset_version` returns filemtime — short TTL by default | Add `Cache-Control: public, max-age=31536000, immutable` for versioned assets | One-liner in `ktube_enqueue_assets` |
| Critical CSS above-the-fold | Static `assets/dist/css/main.css` served whole | Inline first ~14 KB of CSS in `<head>` for ≥1025px; defer remaining via preload | §5.7 (Phase 14 perf pass) |
| Age-gate cascading jurisdictions | RTA label (Phase 6-B) + GDPR modal (Phase 6-C) + IP-geo redirect for 31 EU/EEA/UK ISO codes default (operator override via CSV) | Per-CO override of `ktube_gdpr_blocked_countries` via custom filter; ASACP jurisdiction-alias integration optional | Phase 6 deep — partial close; custom per-CO rules operator-scoped. |
| AOIC / RTA convergence | RTA meta tag (Phase 6); ASACP membership badge missing | Render ASACP membership badge in `footer.php` (operator-configured); verify RTA label in `<head>` via CI smoke test | §6 (Phase 6 deep) — privacy/age followup |
| Schema markup for video Carousel eligibility | OG image + VideoObject on video singles (Phase 9d); channel + actor archive pages still emit only `CollectionPage` / implicit lists | Add `itemListElement` `VideoObject[]` for channel/actor archive pages so Carousel / `Video` rich-snippet clusters can render | §5-A #6 closed; new Phase 16 (Video SERP enrichment) — not yet scheduled. |

**Why this section exists:** when ktube graduates from a single-site theme to a multi-CDN video platform pattern, the above rows pre-empt "why didn't we think about X earlier?" reviewers can reference this section instead of holding same questions in their back pocket.


