# Changelog

All notable changes to **float Reels** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] — 2026-04-26

### Added
- **Carousel thumbnail overlay.** New optional ACF field `reel_carousel_thumbnail` (image, 9:16 recommended, min 540 × 960) lets editors layer a curated still image on top of the homepage carousel video. Renders as `<img class="reel-card__thumbnail">` inside `.reel-card__media`, with a `srcset` from WordPress's responsive image API.
- New CSS state classes:
  - `.reel-card.is-revealed .reel-card__thumbnail` — fades the overlay to `opacity: 0`.
  - `.reel-card.is-revealed .video__play` — also fades the play-button glyph so the playing video reads cleanly.
  - `:hover` fallback under `@media (hover: hover)` for desktop pointers, in case the JS reveal listener hasn't attached yet.

### Changed
- **Carousel playback is now interaction-gated.** Cards no longer auto-play on viewport entry. Mouse hover (desktop) or tap (mobile) on a card adds the `is-revealed` class, attaches the HLS source via `floatReels.attachHls()`, and starts playback. Hovering away pauses and rewinds. The popup-on-click behaviour is unchanged — both effects fire together on touch.
- IntersectionObserver in the carousel module is now exit-only: it pauses any revealed card when the section scrolls out of view, but never auto-plays on entry.
- Removed `primeCarousel()`, `syncVideo()`, and `pauseAll()` helpers — replaced by the leaner `revealCard()` / `hideCard()` pair.

### Performance
- Users who scroll past the Reels section without hovering or tapping pay **zero** video bytes — only the thumbnail (or poster fallback) is fetched. On mobile data this typically saves several MB per page load when six reels are configured.
- HLS manifests are loaded on-demand per card, not all at once on viewport entry, smoothing first-paint network activity.

### Migration
- For each reel where the client wants a curated overlay, open the post in WP admin and upload an image into the new **Carousel thumbnail** field. Reels left without an overlay continue to display the Cloudflare Stream poster directly — no breakage.

## [1.1.1] — 2026-04-23

### Fixed
- **Empty / black video frame after poster on Chromium and Firefox.** `primeCarousel()` and `ensureVideoLoaded()` called `video.load()` right after `window.floatReels.attachHls()`. On the hls.js branch, that detaches the `MediaSource` that `attachMedia()` just wired up — the `<video>` element keeps a live but empty blob URL, the poster disappears, and playback silently renders nothing. `.load()` has been moved **inside** `attachHls()`, scoped to the native-HLS branch only (where it's still useful for the iOS Safari poster quirk). External callers must no longer pair `attachHls()` with a defensive `.load()`.
- **First-slide play sometimes no-op on slow connections.** `video.play()` fires before hls.js has buffered a segment (or before Safari has fetched enough of the manifest); the returned promise rejects and no retry happens. New shared helper `window.floatReels.play( video )` retries once on `canplay`. The carousel and popup now route through it instead of calling `video.play().catch()` directly.

### Changed
- Bumped `float_REELS_VERSION` to `1.1.1` to force cache invalidation of `reels.js` for returning visitors.

## [1.1.0] — 2026-04-23

### Added
- **Cloudflare Stream integration.** All reel videos are now delivered via adaptive HLS from Cloudflare Stream instead of direct MP4s hosted on WordPress. Mobile clients on constrained connections automatically fall back to a lower-bitrate variant; desktop pulls 1080p.
- New ACF field `reel_stream_id` (required) — stores the 32-char Cloudflare Stream Video ID. Editors paste the ID from the Stream dashboard.
- New public helpers:
  - `float_reels_stream_subdomain()` — resolves the account's customer subdomain in order: `FLOAT_REELS_STREAM_SUBDOMAIN` constant → WP option `float_reels_stream_subdomain` → filter `float_reels_stream_subdomain`.
  - `float_reels_stream_hls_url( $video_id )` — builds the HLS manifest URL.
  - `float_reels_stream_thumbnail_url( $video_id, $width, $height, $fit, $time )` — builds an on-the-fly thumbnail URL. Cloudflare renders any size on demand, so no WP thumbnail regeneration is needed for Stream-hosted reels.
- New shared JS helper `window.floatReels.attachHls( video )`:
  - Safari / iOS — sets `video.src` to the manifest (native HLS playback).
  - Chromium / Firefox — wires up hls.js with `capLevelToPlayerSize`, `maxBufferLength: 10`, and auto start level.
  - Idempotent; tracks attachment via `video.__flReelsAttached`.
- `hls.js` 1.5.15 enqueued from jsDelivr as a dependency of `float-reels-js`. Safari/iOS load but don't execute it (native HLS wins the feature-detection check).

### Changed
- Carousel and archive `<video>` elements now use `data-hls` (HLS manifest URL) instead of direct `src`. `preload="none"` preserved so viewport / interaction gating is unchanged.
- Popup `<video>` elements use `data-hls` instead of `data-src`. Lazy-loading of active slide + neighbours unchanged.
- Popup currentTime guard switched from `video.getAttribute('src')` to `video.__flReelsAttached` — the former is unreliable once hls.js attaches a MediaSource blob URL via the `.src` property.
- Posters for carousel card, popup video, and desktop blurred background now come from Cloudflare's thumbnail API at the exact dimensions needed (540×960, 720×1280, 768w).

### Removed
- ACF field `reel_video` (file upload). Stream is the only supported source going forward.
- Custom WP image size `float-reel-card` registration — Cloudflare generates thumbnails at any size on the fly.
- Helper `float_reels_poster_url()` — no longer used.

### Migration
- Define the Cloudflare Stream customer subdomain in `wp-config.php`:
  ```php
  define( 'FLOAT_REELS_STREAM_SUBDOMAIN', 'customer-xxxxxxxxxxxx' );
  ```
  Alternatively set the `float_reels_stream_subdomain` WP option or use the filter of the same name.
- For each reel in WP admin: open the post, paste the Cloudflare Stream Video ID into the new **Cloudflare Stream Video ID** field, save. Reels without an ID are skipped by the carousel and archive templates.

### Performance
- Video bytes scale with what the client can actually play (adaptive bitrate) — typically a 5–10× reduction in mobile payload vs. serving a single 1080p MP4.
- Thumbnail delivery moves from WP's `wp-content/uploads/` (single-origin, often un-CDNed) to Cloudflare's global edge, with on-demand resizing.
- Video segments served from Cloudflare's edge — TTFB drops substantially for international traffic.

## [1.0.4] — 2026-04-23

### Changed
- Homepage carousel `<video>` elements now ship with `preload="none"` instead of `preload="metadata"`. Nothing is fetched until the user actually scrolls the Reels section into view.
- Carousel module uses an `IntersectionObserver` (with a 200 px `rootMargin` to anticipate the scroll) to prime videos on entry: `preload` is upgraded to `metadata`, `.load()` is triggered (handles the iOS Safari poster quirk), and the active slide starts playing. When the section leaves the viewport, videos are paused to save CPU and stop progressive download.
- Swiper interactions (keyboard / touch before viewport entry) prime lazily on `slideChangeTransitionEnd` too, so users who reach the carousel by tabbing don't sit on silent tiles.
- Legacy browsers without `IntersectionObserver` fall back to the previous eager behaviour.

### Removed
- Top-level iOS `.load()` IIFE that primed **every** `.reel-card__video` at `DOMContentLoaded`. For the homepage carousel, that work now happens inside `primeCarousel()` once the section is near the viewport. A scoped equivalent remains inside the archive module (`[data-reel-archive]` only), since the archive page isn't viewport-gated.

### Performance
- Homepage first paint no longer triggers any video network request at all when the Reels section is below the fold. Users who never scroll to it pay zero video bytes.
- Users who do scroll to it get metadata preloaded ~200 px before entry, so perceived latency is unchanged.

## [1.0.3] — 2026-04-23

### Added
- Custom image size `float-reel-card` (540×960, cropped 9:16) registered via `add_image_size()` and used as the carousel card poster. Replaces the previous `large` (1024 px) thumbnail for a tile that renders at ~150–300 CSS pixels wide. Roughly 4× lighter posters on the homepage.
- New helper `float_reels_poster_url( $attachment_id, $preferred_size )` that falls back cleanly to `medium_large` → `large` → `full` when the preferred intermediate size isn't generated — avoids WordPress's silent fallback to the original full-size upload.

### Changed
- Popup `<video poster>` now uses `large` explicitly (1024 px, adequate for a near-fullscreen view on mobile retina).
- Desktop blurred background (`.reels-popup__bg`) now receives its URL through a `--reels-popup-bg` CSS custom property declared on the element itself. The `background-image` property is only applied inside the `@media (min-width: 768px)` rule, so mobile browsers never fetch this asset — previously, some browsers resolved the inline `style="background-image:url()"` even when the element was `display: none`.
- Desktop background image dropped from `large` to `medium_large` (768w). It's blurred 60 px and scaled 1.15× — the extra resolution was wasted.

### Migration notes
- **Regenerate thumbnails for existing reels** so the new `float-reel-card` size is actually generated on disk. Easiest options: the free [Regenerate Thumbnails](https://wordpress.org/plugins/regenerate-thumbnails/) plugin (**Tools → Regenerate Thumbnails**), or WP-CLI: `wp media regenerate --yes`. Until this is done, existing reels degrade automatically to `medium_large` via the helper — still better than the previous `large`, but not optimal.

## [1.0.2] — 2026-04-23

### Added
- `poster` attribute on the popup `<video>` elements, reusing the same thumbnail URL already set on the carousel card. Before hydration (and during the brief load once `src` is set from `data-src`), the popup now displays the poster instead of a black frame — noticeably better perceived performance on mobile. Cost is zero bandwidth: the browser reuses the thumbnail already cached by the carousel.

## [1.0.1] — 2026-04-23

### Fixed
- **Videos loaded twice on page render.** The homepage template iterated the reels collection twice — once for the carousel and once for the popup — with each `<video>` carrying `src` + `preload="metadata"`. Browsers were fetching metadata for every reel twice on first paint. Popup `<video>` elements are now rendered with `data-src` and `preload="none"`; the JS hydrates them on demand (active slide + immediate neighbours) when the popup opens and on each slide change.
- **Broken iOS guard at the top of `assets/js/reels.js`.** A stray `video.load()` call referenced an undefined variable, which threw a `ReferenceError` on iOS and halted execution of the entire JS file — breaking the carousel, popup and archive on iPhone / iPad. Replaced with an IIFE that waits for `DOMContentLoaded` and calls `.load()` on every `.reel-card__video` element, matching the original intent.
- Hardened `closePopup()` and `syncPopupVideo()` so they no longer touch `currentTime` on non-hydrated popup videos (which would be a no-op at best, a console warning at worst).

### Changed
- Bumped `float_REELS_VERSION` to `1.0.1` to force cache invalidation of `reels.js` / `reels.css` for returning visitors.

### Performance
- Initial video bandwidth on the homepage cut in roughly half: each reel now triggers a single metadata preload instead of two.
- Popup videos no longer contribute to first-paint network load at all — they only start fetching when the popup opens.

## [1.0.0] — 2026-04-22

### Added
- Initial release.
- `reel` custom post type with `reels` rewrite slug and `/reels/` archive.
- ACF local field groups:
  - `reel_video` (MP4 file, required)
  - `top_title` (optional kicker)
  - `reel_title` (optional display title, falls back to post title)
  - `thumbnail_square` (optional 1:1 override)
- Homepage carousel partial (`templates/reels-carousel.php`) rendered via `float_reels_carousel()` helper.
- Full-screen popup viewer with responsive Swiper direction (vertical under 768 px, horizontal above), mute toggle, prev / next navigation, keyboard support (`Enter`, `Space`, `Escape`, arrow keys via Swiper) and auto-advance on `ended`.
- Archive template (`templates/archive-reel.php`) with click-to-play/pause; deferred to the theme when an `archive-reel.php` is already present.
- Swiper 11 bundled locally (`assets/js/libs/`) with jsDelivr CDN fallback.
- Activation hook flushes rewrite rules after registering the CPT.

[1.2.0]: #120--2026-04-26
[1.1.1]: #111--2026-04-23
[1.1.0]: #110--2026-04-23
[1.0.4]: #104--2026-04-23
[1.0.3]: #103--2026-04-23
[1.0.2]: #102--2026-04-23
[1.0.1]: #101--2026-04-23
[1.0.0]: #100--2026-04-22
