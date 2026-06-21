<?php
/**
 * Taxonomy registrations: actor + channel (custom).
 *
 * category + post_tag are core; attached via 'taxonomies' arg in post-types.php.
 *
 * @package ktube
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ktube_register_taxonomies — runs on init priority 8 (before CPT registration).
 */
function ktube_register_taxonomies(): void {
	register_taxonomy(
		'actor',
		array( 'video', 'photo' ),
		array(
			'labels' => array(
				'name'          => __( 'Actors',         'ktube' ),
				'singular_name' => __( 'Actor',          'ktube' ),
				'menu_name'     => __( 'Actors',         'ktube' ),
				'all_items'     => __( 'All Actors',     'ktube' ),
				'edit_item'     => __( 'Edit Actor',     'ktube' ),
				'add_new_item'  => __( 'Add New Actor',  'ktube' ),
				'new_item_name' => __( 'New Actor Name', 'ktube' ),
			),
			'public'            => true,
			'hierarchical'      => false,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rest_base'         => 'actors',
			'rewrite'           => array( 'slug' => 'actor', 'with_front' => false ),
		)
	);

	register_taxonomy(
		'channel',
		array( 'video', 'photo' ),
		array(
			'labels' => array(
				'name'          => __( 'Channels',         'ktube' ),
				'singular_name' => __( 'Channel',          'ktube' ),
				'menu_name'     => __( 'Channels',         'ktube' ),
				'all_items'     => __( 'All Channels',     'ktube' ),
				'edit_item'     => __( 'Edit Channel',     'ktube' ),
				'add_new_item'  => __( 'Add New Channel',  'ktube' ),
				'new_item_name' => __( 'New Channel Name', 'ktube' ),
			),
			'public'            => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rest_base'         => 'channels',
			'rewrite'           => array( 'slug' => 'channel', 'with_front' => false ),
		)
	);
}
