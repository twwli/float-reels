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
		'taxonomies'         => array( 'city' ),
	);

	// Only register if not already registered (e.g. by the active theme).
	if ( ! post_type_exists( 'reel' ) ) {
		register_post_type( 'reel', $args );
	}

	// ── City taxonomy ─────────────────────────────────────────────────────────
	// Register 'city' for 'reel' only if the taxonomy does not already exist.
	// If it exists (registered by the theme), we simply add 'reel' to it.
	if ( ! taxonomy_exists( 'city' ) ) {

		$city_labels = array(
			'name'              => __( 'Cities',          'float-reels' ),
			'singular_name'     => __( 'City',            'float-reels' ),
			'search_items'      => __( 'Search Cities',   'float-reels' ),
			'all_items'         => __( 'All Cities',      'float-reels' ),
			'edit_item'         => __( 'Edit City',       'float-reels' ),
			'update_item'       => __( 'Update City',     'float-reels' ),
			'add_new_item'      => __( 'Add New City',    'float-reels' ),
			'new_item_name'     => __( 'New City Name',   'float-reels' ),
			'menu_name'         => __( 'Cities',          'float-reels' ),
		);

		$city_args = array(
			'hierarchical'      => true,
			'labels'            => $city_labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'city', 'with_front' => false ),
			'show_in_rest'      => true,
		);

		register_taxonomy( 'city', array( 'reel' ), $city_args );

	} else {
		// Taxonomy already exists — make sure 'reel' is included.
		register_taxonomy_for_object_type( 'city', 'reel' );
	}
}


// ── Admin column: City ────────────────────────────────────────────────────────

add_filter( 'manage_reel_posts_columns',       'float_reels_add_city_column' );
add_action( 'manage_reel_posts_custom_column', 'float_reels_city_column_content', 10, 2 );

function float_reels_add_city_column( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( $key === 'title' ) {
			$new['city'] = __( 'City', 'float-reels' );
		}
	}
	return $new;
}

function float_reels_city_column_content( $column, $post_id ) {
	if ( $column !== 'city' ) {
		return;
	}
	$terms = get_the_terms( $post_id, 'city' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		echo '—';
		return;
	}
	echo esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) );
}
