<?php
/**
 * Plugin Name:       float Reels
 * Plugin URI:        https://floatmagazin.de
 * Description:       Standalone Reels functionality — CPT, ACF fields, carousel, popup and archive. Works independently of any theme.
 * Version:           1.1.1
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
define( 'float_REELS_VERSION', '1.1.1' );
define( 'float_REELS_DIR',     plugin_dir_path( __FILE__ ) );
define( 'float_REELS_URL',     plugin_dir_url( __FILE__ ) );

// ── Load sub-modules ──────────────────────────────────────────────────────────
require_once float_REELS_DIR . 'includes/cpt.php';
require_once float_REELS_DIR . 'includes/acf-fields.php';

// ── Cloudflare Stream integration ─────────────────────────────────────────────
//
// All reel videos are hosted on Cloudflare Stream and delivered as HLS.
// Each reel stores its Stream Video ID in the ACF field `reel_stream_id`.
// The account's customer subdomain (e.g. `customer-rt321xkquwe662b7`) must
// be configured — in order of precedence:
//
//   1. define( 'FLOAT_REELS_STREAM_SUBDOMAIN', 'customer-xxxxxxxxxxxx' );  (wp-config.php)
//   2. update_option( 'float_reels_stream_subdomain', 'customer-xxxx' );    (WP option)
//   3. add_filter( 'float_reels_stream_subdomain', fn() => 'customer-xxxx' ); (filter)

/**
 * Return the configured Cloudflare Stream customer subdomain
 * (the prefix before `.cloudflarestream.com`, without the dot).
 */
function float_reels_stream_subdomain() {
	$subdomain = '';
	if ( defined( 'FLOAT_REELS_STREAM_SUBDOMAIN' ) && FLOAT_REELS_STREAM_SUBDOMAIN ) {
		$subdomain = FLOAT_REELS_STREAM_SUBDOMAIN;
	} else {
		$subdomain = (string) get_option( 'float_reels_stream_subdomain', '' );
	}
	return (string) apply_filters( 'float_reels_stream_subdomain', $subdomain );
}

/**
 * Build the HLS manifest URL for a Cloudflare Stream video.
 *
 * @param string $video_id  Cloudflare Stream video UID (32-char hex).
 * @return string  Empty string when the subdomain or ID is missing.
 */
function float_reels_stream_hls_url( $video_id ) {
	$video_id  = trim( (string) $video_id );
	$subdomain = float_reels_stream_subdomain();
	if ( ! $video_id || ! $subdomain ) {
		return '';
	}
	return sprintf(
		'https://%s.cloudflarestream.com/%s/manifest/video.m3u8',
		rawurlencode( $subdomain ),
		rawurlencode( $video_id )
	);
}

/**
 * Build a thumbnail URL for a Cloudflare Stream video. Thumbnails are
 * rendered on-the-fly by Cloudflare at any requested size, so we don't
 * pre-generate anything on the WP side.
 *
 * Omitting $time falls back to the poster frame configured in the Stream
 * dashboard (or the first second of the video when no poster was set).
 *
 * @param string   $video_id
 * @param int      $width   Requested width in px.
 * @param int|null $height  Requested height in px, or null to let Cloudflare compute it.
 * @param string   $fit     'crop' | 'contain' | 'cover' | 'scale-down' | 'pad'
 * @param string   $time    Optional timestamp (e.g. '0s', '1.5s'). Empty = Stream default.
 * @return string
 */
function float_reels_stream_thumbnail_url( $video_id, $width, $height = null, $fit = 'crop', $time = '' ) {
	$video_id  = trim( (string) $video_id );
	$subdomain = float_reels_stream_subdomain();
	if ( ! $video_id || ! $subdomain ) {
		return '';
	}

	$args = array( 'width' => (int) $width, 'fit' => $fit );
	if ( $height ) {
		$args['height'] = (int) $height;
	}
	if ( $time !== '' ) {
		$args['time'] = $time;
	}

	return add_query_arg(
		$args,
		sprintf(
			'https://%s.cloudflarestream.com/%s/thumbnails/thumbnail.jpg',
			rawurlencode( $subdomain ),
			rawurlencode( $video_id )
		)
	);
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

	// hls.js — HLS playback for browsers without native support (Chrome,
	// Firefox, Edge). Safari / iOS have native HLS via the <video> element
	// and will never actually execute hls.js code, but the script is small
	// enough (~45 KB gzipped) that we ship it uniformly to keep the logic
	// simple. Upgrade to a feature-detected conditional load later if needed.
	if ( ! wp_script_is( 'hls-js', 'registered' ) ) {
		wp_register_script(
			'hls-js',
			'https://cdn.jsdelivr.net/npm/hls.js@1.5.15/dist/hls.min.js',
			array(),
			'1.5.15',
			true
		);
	}
	wp_enqueue_script( 'hls-js' );

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
		array( 'swiper-js', 'hls-js' ),
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
