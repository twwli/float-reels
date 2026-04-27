# float Reels

Standalone WordPress plugin that adds a short-form vertical video ("reel") feature to a site, independently of the active theme. Registers the `reel` custom post type, declares its ACF fields, ships a homepage carousel + full-screen popup viewer, and provides an archive template.

Videos are delivered as adaptive HLS from **Cloudflare Stream** — mobile clients get a low-bitrate variant automatically, desktop gets 1080p, and thumbnails are generated on-demand by Cloudflare's edge.

Built for [floatmagazin.de](https://floatmagazin.de) — portable to any WordPress 6.0+ install.

---

## Requirements

- WordPress **6.0** or higher
- PHP **8.0** or higher
- [Advanced Custom Fields](https://www.advancedcustomfields.com/) 5.x or later (Free or Pro)
- A [Cloudflare Stream](https://www.cloudflare.com/products/cloudflare-stream/) account with videos uploaded (used for both HLS playback and thumbnail generation)
- [Swiper 11](https://swiperjs.com/) — bundled in `assets/js/libs/`, with a jsDelivr CDN fallback
- [hls.js 1.5.15](https://github.com/video-dev/hls.js) — loaded from jsDelivr for non-Safari HLS playback

## Installation

1. Copy the plugin folder to `wp-content/plugins/float-reels/`, or upload the ZIP via **Plugins → Add New → Upload**.
2. Activate **float Reels** in the WordPress admin.
3. Make sure ACF is active (the field groups are registered on `acf/init`).
4. Configure the Cloudflare Stream customer subdomain — see [Cloudflare Stream setup](#cloudflare-stream-setup) below.
5. The archive is available at `/reels/` once permalinks are refreshed (the activation hook flushes rewrite rules automatically).

## Cloudflare Stream setup

Each reel references a Cloudflare Stream video by its 32-character Video ID. The plugin builds playback and thumbnail URLs against your account's **customer subdomain** (e.g. `customer-rt321xkquwe662b7`).

Configure the subdomain once, in order of precedence:

1. **Constant in `wp-config.php`** (recommended):

   ```php
   define( 'FLOAT_REELS_STREAM_SUBDOMAIN', 'customer-xxxxxxxxxxxx' );
   ```

2. **WordPress option** (if you prefer the DB):

   ```php
   update_option( 'float_reels_stream_subdomain', 'customer-xxxxxxxxxxxx' );
   ```

3. **Filter** (for multi-site / conditional scenarios):

   ```php
   add_filter( 'float_reels_stream_subdomain', fn() => 'customer-xxxxxxxxxxxx' );
   ```

You can find your customer subdomain in the Cloudflare dashboard under **Stream → any video → playback URL** — it's the prefix before `.cloudflarestream.com`.

For each reel, paste the Video ID (shown in the Stream dashboard under **Video details**) into the **Cloudflare Stream Video ID** field on the reel post. Reels without an ID are skipped by the carousel and archive templates.

## What the plugin registers

### Custom post type — `reel`

Registered in `includes/cpt.php`.

| Property       | Value                                |
| -------------- | ------------------------------------ |
| Slug           | `reels` (singular) / `/reels/` archive |
| Supports       | `title`, `thumbnail`                 |
| REST           | disabled (`show_in_rest => false`)   |
| Menu position  | 6, icon `dashicons-video-alt3`       |

Guarded behind `post_type_exists()` — if the theme already registers a `reel` CPT, the plugin steps aside.

### ACF fields

Declared in `includes/acf-fields.php` as local field groups (version-controlled, no admin export needed).

| Field                      | Type  | Required | Notes                                                                                                                                              |
| -------------------------- | ----- | -------- | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| `reel_stream_id`           | Text  | yes      | 32-char Cloudflare Stream Video ID (e.g. `07529a56ff78eb51f6ee5e72f892b6dc`).                                                                       |
| `top_title`                | Text  | no       | Kicker / small-caps label above the title. Max 60 chars.                                                                                            |
| `reel_title`               | Text  | no       | Display title shown on the card, in the popup, and on the archive tile. Leave empty to hide the heading entirely (no fallback to `post_title`).      |
| `reel_carousel_thumbnail`  | Image | no       | 9:16 image overlaid on the homepage carousel card. Fades out on hover (desktop) / tap (mobile) to reveal the video. Min 540 × 960. JPG/PNG/WebP.   |
| `thumbnail_square`         | Image | no       | Optional manual square crop (min 800 × 800) for listing views.                                                                                      |

### Public helper functions

Exposed by `float-reels.php` for use in templates or other plugins:

| Function                                                         | Returns                                                              |
| ---------------------------------------------------------------- | -------------------------------------------------------------------- |
| `float_reels_stream_subdomain()`                                 | Configured Cloudflare Stream customer subdomain.                     |
| `float_reels_stream_hls_url( $video_id )`                        | HLS manifest URL (`…/manifest/video.m3u8`).                          |
| `float_reels_stream_thumbnail_url( $video_id, $w, $h, $fit, $t )` | On-the-fly thumbnail URL at any requested size.                      |
| `float_reels_carousel()`                                         | Renders the homepage carousel + popup partial.                       |

## Usage

### Homepage carousel

Call the helper function from any theme template (usually `front-page.php` or `home.php`):

```php
<?php if ( function_exists( 'float_reels_carousel' ) ) float_reels_carousel(); ?>
```

This renders the horizontal Swiper carousel + the full-screen popup markup from `templates/reels-carousel.php`.

### Archive page

The plugin hooks `template_include` and serves `templates/archive-reel.php` for the `reel` CPT archive — **unless** the active theme already ships an `archive-reel.php`, in which case the theme wins.

### Assets

`wp_enqueue_scripts` registers and enqueues:

- `swiper-css` / `swiper-js` — local copy from `assets/js/libs/` when present, else jsDelivr CDN
- `hls-js` — `hls.js` 1.5.15 from jsDelivr (Safari / iOS load but don't execute it — native HLS wins)
- `float-reels-css` — `assets/css/reels.css`
- `float-reels-js`  — `assets/js/reels.js` (depends on `swiper-js` + `hls-js`)

Version constant `float_REELS_VERSION` is used as the cache-buster query string — bump it in `float-reels.php` when you ship CSS/JS changes.

## How the viewer works

`assets/js/reels.js` is split into three independent IIFE modules, plus a shared helper:

### Shared HLS helper

`window.floatReels.attachHls( video )` wires a `<video>` element to its Cloudflare Stream manifest (from the `data-hls` attribute):

- **Safari / iOS** — sets `video.src` to the manifest (native HLS playback).
- **Chromium / Firefox** — instantiates an `Hls` instance with `capLevelToPlayerSize`, `maxBufferLength: 10`, and auto start level, then calls `attachMedia()`.
- Idempotent — tracked via `video.__flReelsAttached`, safe to call repeatedly.

### Modules

1. **Reels slider** — horizontal Swiper on the homepage with **interaction-gated playback**. `<video>` elements ship with `preload="none"` and `data-hls` only — no network request fires until the user actually engages with a card. On `mouseenter` (desktop hover) or `click` (mobile tap), the carousel module adds an `is-revealed` class to the card, calls `attachHls()` and starts playback. `mouseleave` pauses and rewinds. When an ACF carousel thumbnail is set, that's what's visible in the default state; CSS fades it on `is-revealed` (and on `:hover` under `@media (hover: hover)` as a safety net). An `IntersectionObserver` is kept solely to pause revealed cards if the section scrolls out of view.
2. **Reels popup** — full-screen Swiper opened when a carousel item is clicked (or `Enter` / `Space` on a focused tile). Direction adapts to viewport: **vertical** under 768 px, **horizontal** above, with prev/next buttons on desktop. Popup videos hydrate on demand: when the popup opens, and on every slide change, `attachHls()` runs on the active slide + immediate neighbours. Mute state persists across slides. Pressing `Escape`, clicking the close button, or reaching past the last/first slide via the nav arrows closes the popup.
3. **Reels archive** — click-to-play/pause on `/reels/` tiles; `attachHls()` runs on first interaction.

### Posters & thumbnails

All three poster assets (carousel card 540×960, popup video 720×1280, desktop blurred background 768w) are served by Cloudflare's on-the-fly thumbnail API. No WordPress image regeneration is required.

The desktop blurred background is passed to CSS through a `--reels-popup-bg` custom property on the element, with `background-image` only applied inside the `@media (min-width: 768px)` rule — mobile browsers never fetch it.

## File layout

```
float-reels/
├── float-reels.php              # Bootstrap, Stream helpers, enqueue, archive filter
├── includes/
│   ├── cpt.php                  # Register the `reel` CPT
│   └── acf-fields.php           # ACF local field groups
├── templates/
│   ├── archive-reel.php         # /reels/ archive grid
│   └── reels-carousel.php       # Homepage carousel + full-screen popup
├── assets/
│   ├── css/reels.css            # Component styles
│   └── js/
│       ├── reels.js             # Slider, popup, archive modules + attachHls helper
│       └── libs/                # Swiper 11 bundle (optional local copy)
├── CHANGELOG.md
└── README.md
```

## Development notes

- The carousel and popup `<video>` elements use `data-hls` (not `src`) and `preload="none"`. Don't set `src` directly — always route through `window.floatReels.attachHls()` so the native-HLS vs hls.js branching is handled in one place.
- Treat `video.__flReelsAttached` as the source of truth for "is this video hydrated?" — the `src` attribute becomes unreliable once hls.js attaches a `MediaSource` blob URL.
- All query strings are escaped via `esc_url`, `esc_attr`, `esc_html` — keep it that way when extending templates.
- The `reel` CPT is intentionally excluded from the REST API; enable it in `includes/cpt.php` if a Gutenberg / headless workflow becomes necessary.
- Strings are wrapped in `__()` / `esc_attr_e()` under the `float-reels` text domain. Translations go in `languages/` (path defined by the plugin header).

## License

Proprietary — © float News. Not intended for public distribution.
