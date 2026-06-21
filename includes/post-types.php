<?php
/**
 * Custom post type registration: video / blog / photo.
 *
 * All show_in_rest=true so WPS Mass Importer + block-based admin UI
 * can target them via REST.
 *
 * @package ktube
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ktube_register_post_types — runs on init priority 9.
 */
function ktube_register_post_types(): void {
	register_post_type(
		'video',
		array(
			'labels' => array(
				'name'          => __( 'Videos',          'ktube' ),
				'singular_name' => __( 'Video',           'ktube' ),
				'add_new_item'  => __( 'Add New Video',   'ktube' ),
				'edit_item'     => __( 'Edit Video',      'ktube' ),
				'all_items'     => __( 'All Videos',      'ktube' ),
				'menu_name'     => __( 'Videos',          'ktube' ),
			),
			'public'                => true,
			'has_archive'           => true,
			'rewrite'               => array( 'slug' => 'videos', 'with_front' => false ),
			'show_in_rest'          => true,
			'rest_base'             => 'videos',
			'rest_controller_class' => 'WP_REST_Posts_Controller',
			'menu_icon'             => 'dashicons-video-alt3',
			'menu_position'         => 5,
			'supports'              => array(
				'title', 'editor', 'thumbnail', 'excerpt',
				'custom-fields', 'comments', 'revisions', 'author',
			),
			'taxonomies'            => array( 'category', 'post_tag', 'actor', 'channel' ),
		)
	);

	register_post_type(
		'blog',
		array(
			'labels' => array(
				'name'          => __( 'Blog Posts',         'ktube' ),
				'singular_name' => __( 'Blog Post',          'ktube' ),
				'add_new_item'  => __( 'Add New Blog Post',  'ktube' ),
				'edit_item'     => __( 'Edit Blog Post',     'ktube' ),
				'all_items'     => __( 'All Blog Posts',     'ktube' ),
				'menu_name'     => __( 'Blog',               'ktube' ),
			),
			'public'         => true,
			'has_archive'    => true,
			'rewrite'        => array( 'slug' => 'blog', 'with_front' => false ),
			'show_in_rest'   => true,
			'rest_base'      => 'blog',
			'menu_icon'      => 'dashicons-edit-large',
			'supports'       => array(
				'title', 'editor', 'thumbnail', 'excerpt',
				'comments', 'revisions', 'author', 'custom-fields',
			),
			'taxonomies'     => array( 'category', 'post_tag' ),
			'capability_type' => 'post',
		)
	);

	register_post_type(
		'photo',
		array(
			'labels' => array(
				'name'          => __( 'Photo Sets',         'ktube' ),
				'singular_name' => __( 'Photo Set',          'ktube' ),
				'add_new_item'  => __( 'Add New Photo Set',  'ktube' ),
				'edit_item'     => __( 'Edit Photo Set',     'ktube' ),
				'all_items'     => __( 'All Photo Sets',     'ktube' ),
				'menu_name'     => __( 'Photos',             'ktube' ),
			),
			'public'         => true,
			'has_archive'    => true,
			'rewrite'        => array( 'slug' => 'photos', 'with_front' => false ),
			'show_in_rest'   => true,
			'rest_base'      => 'photos',
			'menu_icon'      => 'dashicons-format-gallery',
			'supports'       => array(
				'title', 'editor', 'thumbnail', 'excerpt',
				'custom-fields', 'comments', 'revisions',
			),
			'taxonomies'     => array( 'category', 'post_tag', 'actor', 'channel' ),
		)
	);
}
