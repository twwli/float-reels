<?php
/**
 * Plugin Name:       float Reels
 * Plugin URI:        https://floatmagazin.de
 * Description:       Standalone Reels functionality — CPT, ACF fields, carousel, popup and archive. Works independently of any theme.
 * Version:           1.0.3
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            float News
 * Text Domain:       float-reels
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Constants ─────────────────────────────────────────────────────────────────
define( 'float_REELS_VERSION', '1.0.3' );
define( 'float_REELS_DIR',     plugin_dir_path( __FILE__ ) );
define( 'float_REELS_URL',     plugin_dir_url( __FILE__ ) );

// ── Load sub-modules ──────────────────────────────────────────────────────────
require_once float_REELS_DIR . 'includes/cpt.php';
require_once float_REELS_DIR . 'includes/acf-fields.php';

// ── Custom image size for reel card posters ──────────────────────────────────
// 540×960 cropped 9:16 — sized for the carousel card poster, which renders at
// ~150–300 CSS pixels wide. Covers up to DPR 3× without serving the ~1024 px
// 'large' thumbnail unnecessarily.
//
// Existing attachments won't have this size until thumbnails are regenerated
// (e.g. via the "Regenerate Thumbnails" plugin or `wp media regenerate`).
// The helper below falls back gracefully when the size is missing.
add_action( 'after_setup_theme', function () {
	add_image_size( 'float-reel-card', 540, 960, true );
}, 20 );

/**
 * Return the best available poster URL for an attachment at a preferred size,
 * falling back to `medium_large` (or the next best) when the preferred size
 * isn't actually generated on disk.
 *
 * This avoids WordPress's default behaviour of silently returning the
 * original full-size file when an intermediate size is missing.
 *
 * @param int    $attachment_id
 * @param string $preferred_size  Intermediate size name (e.g. 'float-reel-card').
 * @return string Empty string when no image is available.
 */
function float_reels_poster_url( $attachment_id, $preferred_size ) {
	if ( ! $attachment_id ) {
		return '';
	}

	$meta = wp_get_attachment_metadata( $attachment_id );

	// Preferred size is generated → use it.
	if ( is_array( $meta ) && ! empty( $meta['sizes'][ $preferred_size ] ) ) {
		$src = wp_get_attachment_image_src( $attachment_id, $preferred_size );
		if ( $src ) {
			return $src[0];
		}
	}

	// Fallback chain: medium_large → large → full.
	foreach ( array( 'medium_large', 'large', 'full' ) as $fallback ) {
		$src = wp_get_attachment_image_src( $attachment_id, $fallback );
		if ( $src ) {
			return $src[0];
		}
	}

	return '';
}

// ── Activation hook — flush rewrite rules ─────────────────────────────────────
register_activation_hook( __FILE__, function () {
	float_reels_register_cpt();
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );

// ── Enqueue assets ────────────────────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', 'float_reels_enqueue_assets' );

function float_reels_enqueue_assets() {

	// Swiper — use a local copy if present, otherwise fall back to jsDelivr CDN.
	$swiper_css_local = float_REELS_URL . 'assets/js/libs/swiper-bundle.min.css';
	$swiper_js_local  = float_REELS_URL . 'assets/js/libs/swiper-bundle.min.js';

	if ( file_exists( float_REELS_DIR . 'assets/js/libs/swiper-bundle.min.js' ) ) {
		// Local copy bundled with the plugin.
		if ( ! wp_style_is( 'swiper-css', 'registered' ) ) {
			wp_register_style( 'swiper-css', $swiper_css_local, array(), '11' );
		}
		if ( ! wp_script_is( 'swiper-js', 'registered' ) ) {
			wp_register_script( 'swiper-js', $swiper_js_local, array(), '11', true );
		}
	} else {
		// No local copy — load from CDN (requires network access).
		if ( ! wp_style_is( 'swiper-css', 'registered' ) ) {
			wp_register_style( 'swiper-css', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css', array(), '11' );
		}
		if ( ! wp_script_is( 'swiper-js', 'registered' ) ) {
			wp_register_script( 'swiper-js', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', array(), '11', true );
		}
	}

	wp_enqueue_style( 'swiper-css' );
	wp_enqueue_script( 'swiper-js' );

	// Plugin CSS & JS.
	wp_enqueue_style(
		'float-reels-css',
		float_REELS_URL . 'assets/css/reels.css',
		array( 'swiper-css' ),
		float_REELS_VERSION
	);

	wp_enqueue_script(
		'float-reels-js',
		float_REELS_URL . 'assets/js/reels.js',
		array( 'swiper-js' ),
		float_REELS_VERSION,
		true
	);
}

// ── Archive template override ─────────────────────────────────────────────────
// Point the 'reel' CPT archive to the plugin's own template.
// Only fires if the active theme does NOT already have an archive-reel.php.
add_filter( 'template_include', 'float_reels_archive_template' );

function float_reels_archive_template( $template ) {
	if ( ! is_post_type_archive( 'reel' ) ) {
		return $template;
	}

	// If the theme already provides an archive-reel.php, respect it.
	$theme_template = get_stylesheet_directory() . '/archive-reel.php';
	if ( file_exists( $theme_template ) ) {
		return $template;
	}

	$plugin_template = float_REELS_DIR . 'templates/archive-reel.php';
	if ( file_exists( $plugin_template ) ) {
		return $plugin_template;
	}

	return $template;
}

// ── Public helper functions ───────────────────────────────────────────────────

/**
 * Render the reels carousel section (homepage partial).
 *
 * Usage in a theme template:
 *   <?php if ( function_exists( 'float_reels_carousel' ) ) float_reels_carousel(); ?>
 */
function float_reels_carousel() {
	load_template( float_REELS_DIR . 'templates/reels-carousel.php', false );
}
