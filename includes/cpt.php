<?php
/**
 * float Reels — CPT & Taxonomy Registration
 *
 * Registers the 'reel' custom post type.
 * Registers the 'city' taxonomy for reels (shared with other post types if
 * already registered by the active theme or another plugin).
 *
 * @package floatReels
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'float_reels_register_cpt' );

/**
 * Register the Reel CPT.
 */
function float_reels_register_cpt() {

	$labels = array(
		'name'               => __( 'Reels',             'float-reels' ),
		'singular_name'      => __( 'Reel',              'float-reels' ),
		'add_new'            => __( 'Add New',           'float-reels' ),
		'add_new_item'       => __( 'Add New Reel',      'float-reels' ),
		'edit_item'          => __( 'Edit Reel',         'float-reels' ),
		'new_item'           => __( 'New Reel',          'float-reels' ),
		'view_item'          => __( 'View Reel',         'float-reels' ),
		'search_items'       => __( 'Search Reels',      'float-reels' ),
		'not_found'          => __( 'No reels found',    'float-reels' ),
		'not_found_in_trash' => __( 'No reels in trash', 'float-reels' ),
		'all_items'          => __( 'All Reels',         'float-reels' ),
		'menu_name'          => __( 'Reels',             'float-reels' ),
	);

	$args = array(
		'labels'             => $labels,
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_in_rest'       => false,
		'query_var'          => true,
		'rewrite'            => array( 'slug' => 'reels' ),
		'capability_type'    => 'post',
		'has_archive'        => 'reels',
		'hierarchical'       => false,
		'menu_position'      => 6,
		'menu_icon'          => 'dashicons-video-alt3',
		'supports'           => array( 'title', 'thumbnail' ),
	);

	// Only register if not already registered (e.g. by the active theme).
	if ( ! post_type_exists( 'reel' ) ) {
		register_post_type( 'reel', $args );
	}
}
