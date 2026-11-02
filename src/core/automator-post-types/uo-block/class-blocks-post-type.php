<?php

namespace Uncanny_Automator;

/**
 * Class Blocks_Post_Type
 *
 * Hidden post type to hold recipe blocks (filters, delays, loops).
 * Uses post_parent for hierarchy and menu_order for sequencing.
 *
 * @package Uncanny_Automator
 */
class Blocks_Post_Type {

	/**
	 * Post type slug.
	 *
	 * @var string
	 */
	const POST_TYPE = AUTOMATOR_POST_TYPE_BLOCK;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ), 0 );
	}

	/**
	 * Register the uo-block post type.
	 *
	 * @return void
	 */
	public function register_post_type() {

		$labels = array(
			'name'          => 'Automator Blocks',
			'singular_name' => 'Automator Block',
			'menu_name'     => 'Blocks',
		);

		$args = array(
			'label'               => 'Automator Block',
			'description'         => 'Block for an Uncanny WordPress Automation recipe',
			'labels'              => $labels,
			'supports'            => array( 'title', 'custom-fields' ),
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'can_export'          => false,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capabilities'        => array(
				'publish_posts'       => automator_get_capability(), // phpcs:ignore WordPress.WP.Capabilities.Undetermined
				'edit_posts'          => automator_get_capability(), // phpcs:ignore WordPress.WP.Capabilities.Undetermined
				'edit_others_posts'   => automator_get_capability(), // phpcs:ignore WordPress.WP.Capabilities.Undetermined
				'delete_posts'        => automator_get_capability(), // phpcs:ignore WordPress.WP.Capabilities.Undetermined
				'delete_others_posts' => automator_get_capability(), // phpcs:ignore WordPress.WP.Capabilities.Undetermined
				'read_private_posts'  => automator_get_capability(), // phpcs:ignore WordPress.WP.Capabilities.Undetermined
				'edit_post'           => automator_get_capability(), // phpcs:ignore WordPress.WP.Capabilities.Undetermined
				'delete_post'         => automator_get_capability(), // phpcs:ignore WordPress.WP.Capabilities.Undetermined
			),
		);

		register_post_type( self::POST_TYPE, apply_filters( 'automator_post_type_block_args', $args ) );
	}
}
