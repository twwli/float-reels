# Changelog

All notable changes to **float Reels** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[1.0.2]: #102--2026-04-23
[1.0.1]: #101--2026-04-23
[1.0.0]: #100--2026-04-22
