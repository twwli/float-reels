<?php
/**
 * float Reels — ACF Local Field Groups
 *
 * Defines the custom fields required by the Reel CPT in PHP so they are
 * version-controlled and portable across environments.
 *
 * Fields registered here:
 *   • reel_video   — Video file (attachment ID, MP4)
 *   • top_title    — Optional kicker / label shown above the reel title
 *   • reel_title   — Display title (falls back to post title when empty)
 *   • thumbnail_square — Optional manually-cropped square thumbnail
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

			// Video file
			array(
				'key'           => 'field_float_reel_video',
				'label'         => 'Video file',
				'name'          => 'reel_video',
				'type'          => 'file',
				'instructions'  => 'Upload an MP4 video in portrait format (9:16 ratio). Recommended: 1080 × 1920 px.',
				'required'      => 1,
				'return_format' => 'id',   // Returns attachment ID — use wp_get_attachment_url() to get the URL.
				'library'       => 'all',
				'mime_types'    => 'mp4',
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
				'instructions' => 'Display title shown on the reel card and in the popup. Falls back to the post title when left empty.',
				'required'     => 0,
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
