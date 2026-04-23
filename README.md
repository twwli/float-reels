# float Reels

Standalone WordPress plugin that adds a short-form vertical video ("reel") feature to a site, independently of the active theme. Registers the `reel` custom post type, declares its ACF fields, ships a homepage carousel + full-screen popup viewer, and provides an archive template.

Built for [floatmagazin.de](https://floatmagazin.de) — portable to any WordPress 6.0+ install.

---

## Requirements

- WordPress **6.0** or higher
- PHP **8.0** or higher
- [Advanced Custom Fields](https://www.advancedcustomfields.com/) 5.x or later (Free or Pro)
- [Swiper 11](https://swiperjs.com/) — bundled in `assets/js/libs/`, with a jsDelivr CDN fallback

## Installation

1. Copy the plugin folder to `wp-content/plugins/float-reels/`, or upload the ZIP via **Plugins → Add New → Upload**.
2. Activate **float Reels** in the WordPress admin.
3. Make sure ACF is active (the field groups are registered on `acf/init`).
4. The archive is available at `/reels/` once permalinks are refreshed (the activation hook flushes rewrite rules automatically).

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

| Field              | Type  | Required | Notes                                                                 |
| ------------------ | ----- | -------- | --------------------------------------------------------------------- |
| `reel_video`       | File  | yes      | MP4, 9:16, recommended 1080 × 1920. Returns attachment **ID**.        |
| `top_title`        | Text  | no       | Kicker / small-caps label above the title. Max 60 chars.              |
| `reel_title`       | Text  | no       | Display title. Falls back to `post_title` when empty.                 |
| `thumbnail_square` | Image | no       | Optional manual square crop (min 800 × 800) for listing views.        |

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
- `float-reels-css` — `assets/css/reels.css`
- `float-reels-js`  — `assets/js/reels.js`

Version constant `float_REELS_VERSION` is used as the cache-buster query string — bump it in `float-reels.php` when you ship CSS/JS changes.

## How the viewer works

`assets/js/reels.js` is split into three independent IIFE modules:

1. **Reels slider** — horizontal Swiper on the homepage. The active slide's video plays muted; non-active slides pause and reset to `currentTime = 0`.
2. **Reels popup** — full-screen Swiper opened when a carousel item is clicked (or `Enter` / `Space` on a focused tile). Direction adapts to viewport: **vertical** under 768 px, **horizontal** above, with prev/next buttons on desktop. Mute state persists across slides. Pressing `Escape`, clicking the close button, or reaching past the last/first slide via the nav arrows closes the popup.
3. **Reels archive** — click-to-play/pause on `/reels/` tiles.

### Popup video lazy-loading

To avoid preloading the same video twice (once in the carousel, once in the popup) the popup `<video>` elements are rendered with `data-src` and `preload="none"`. The popup module hydrates videos on demand: when the popup opens, and on every slide change, it sets `src` on the active slide plus the immediate neighbours (prev/next). This keeps navigation smooth while halving the initial video payload on page load.

### iOS quirk

iOS Safari occasionally ignores `preload="metadata"` and fails to render the poster frame. A small IIFE at the top of `reels.js` detects iOS, waits for `DOMContentLoaded`, and calls `.load()` on every `.reel-card__video` to force the poster to appear.

## File layout

```
float-reels/
├── float-reels.php              # Bootstrap, constants, enqueue, archive filter, helper fn
├── includes/
│   ├── cpt.php                  # Register the `reel` CPT
│   └── acf-fields.php           # ACF local field groups
├── templates/
│   ├── archive-reel.php         # /reels/ archive grid
│   └── reels-carousel.php       # Homepage carousel + full-screen popup
├── assets/
│   ├── css/reels.css            # Component styles
│   └── js/
│       ├── reels.js             # Slider, popup, archive modules
│       └── libs/                # Swiper 11 bundle (optional local copy)
├── CHANGELOG.md
└── README.md
```

## Development notes

- The ACF field group for `reel_video` stores the **attachment ID**, not the URL — use `wp_get_attachment_url()` on the retrieved value, as `templates/reels-carousel.php` does.
- All query strings are escaped via `esc_url`, `esc_attr`, `esc_html` — keep it that way when extending templates.
- The `reel` CPT is intentionally excluded from the REST API; enable it in `includes/cpt.php` if a Gutenberg / headless workflow becomes necessary.
- Strings are wrapped in `__()` / `esc_attr_e()` under the `float-reels` text domain. Translations go in `languages/` (path defined by the plugin header).

## License

Proprietary — © float News. Not intended for public distribution.
