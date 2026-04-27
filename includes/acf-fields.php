<?php
/**
 * float Reels — ACF Local Field Groups
 *
 * Defines the custom fields required by the Reel CPT in PHP so they are
 * version-controlled and portable across environments.
 *
 * Fields registered here:
 *   • reel_stream_id          — Cloudflare Stream Video ID (required)
 *   • top_title               — Optional kicker / label shown above the reel title
 *   • reel_title              — Display title (falls back to post title when empty)
 *   • reel_carousel_thumbnail — Optional image overlaid on the carousel card
 *                               (fades out on hover / tap to reveal the video)
 *   • thumbnail_square        — Optional manually-cropped square thumbnail (listing views)
 *
 * Requires: Advanced Custom Fields PRO (or ACF Free ≥ 5.x)
 *
 * @package floatReels
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only run when ACF is active.
add_action( 'acf/init', 'float_reels_register_acf_fields' );

function float_reels_register_acf_fields() {

	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	// ── Reel fields ───────────────────────────────────────────────────────────
	acf_add_local_field_group( array(
		'key'    => 'group_float_reel_fields',
		'title'  => 'Reel',
		'fields' => array(

			// Cloudflare Stream Video ID
			array(
				'key'           => 'field_float_reel_stream_id',
				'name'          => 'reel_stream_id',
				'label'         => 'Cloudflare Stream Video ID',
				'type'          => 'text',
				'instructions'  => 'Paste the 32-character Video ID from the Cloudflare Stream dashboard (Stream → Videos → select the video → the ID is visible under "Video details", next to the copy icon). Example: 07529a56ff78eb51f6ee5e72f892b6dc',
				'required'      => 1,
				'maxlength'     => 32,
				'placeholder'   => '07529a56ff78eb51f6ee5e72f892b6dc',
			),

			// Kicker / label
			array(
				'key'          => 'field_float_reel_top_title',
				'label'        => 'Kicker (optional)',
				'name'         => 'top_title',
				'type'         => 'text',
				'instructions' => 'Short label displayed in small caps above the reel title (e.g. a category name or city).',
				'required'     => 0,
				'maxlength'    => 60,
			),

			// Display title
			array(
				'key'          => 'field_float_reel_title',
				'label'        => 'Reel title (optional)',
				'name'         => 'reel_title',
				'type'         => 'text',
				'instructions' => 'Display title shown on the reel card and in the popup. Leave empty to hide the heading on this reel.',
				'required'     => 0,
			),

			// Carousel thumbnail overlay
			array(
				'key'           => 'field_float_reel_carousel_thumbnail',
				'label'         => 'Carousel thumbnail (optional)',
				'name'          => 'reel_carousel_thumbnail',
				'type'          => 'image',
				'instructions'  => 'Image displayed on top of the video in the homepage carousel. Fades out on mouse hover (desktop) or tap (mobile) to reveal the video, which only starts playing on that interaction. Recommended ratio 9:16, min 540 × 960 px. Leave empty to show the video poster directly.',
				'required'      => 0,
				'return_format' => 'array',
				'preview_size'  => 'medium',
				'library'       => 'all',
				'min_width'     => 540,
				'min_height'    => 960,
				'mime_types'    => 'jpg, jpeg, png, webp',
			),

		),
		'location' => array(
			array(
				array(
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'reel',
				),
			),
		),
		'menu_order'            => 5,
		'position'              => 'normal',
		'style'                 => 'default',
		'label_placement'       => 'top',
		'instruction_placement' => 'label',
		'active'                => true,
	) );

	// ── Square thumbnail override ─────────────────────────────────────────────
	// Registers the thumbnail_square field for the reel CPT.
	// If the active theme already registers this field group for 'reel',
	// this group is skipped (ACF ignores duplicate keys).
	acf_add_local_field_group( array(
		'key'    => 'group_float_reels_thumbnail',
		'title'  => 'Thumbnail Override',
		'fields' => array(
			array(
				'key'               => 'field_float_reels_thumbnail_square',
				'label'             => 'Square thumbnail (optional)',
				'name'              => 'thumbnail_square',
				'type'              => 'image',
				'instructions'      => 'Upload a square image to replace the auto-crop used in listing views. Minimum: 800 × 800 px.',
				'required'          => 0,
				'return_format'     => 'array',
				'preview_size'      => 'thumbnail',
				'library'           => 'all',
				'min_width'         => 800,
				'min_height'        => 800,
				'mime_types'        => 'jpg, jpeg, png, webp',
			),
		),
		'location' => array(
			array(
				array(
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'reel',
				),
			),
		),
		'menu_order'            => 20,
		'position'              => 'side',
		'style'                 => 'default',
		'label_placement'       => 'top',
		'instruction_placement' => 'label',
		'active'                => true,
	) );
}
