<?php

namespace Uncanny_Automator;

/**
 * Class Block_Modifier_Post_Type
 *
 * Hidden post type to hold block modifiers — leaf entities attached to a
 * `uo-block` parent and discriminated by the `block_modifier_type`
 * post-meta key (`'filter_condition'`, `'loop_filter'`, future opaque).
 *
 * Replaces the earlier `uo-filter-condition` post type, which was only
 * ever a single-modifier-kind container. The unified `uo-block-modifier`
 * slug matches the wire-shape contract — `type: 'block_modifier'` plus a
 * narrowing `block_modifier_type` field — so storage and wire shape share
 * one mental model.
 *
 * Uses `post_parent` to reference the parent `uo-block` post and
 * `menu_order` for sequencing within the block.
 *
 * @package Uncanny_Automator
 */
class Block_Modifier_Post_Type {

	/**
	 * Post type slug.
	 *
	 * @var string
	 */
	const POST_TYPE = AUTOMATOR_POST_TYPE_BLOCK_MODIFIER;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ), 0 );
	}

	/**
	 * Register the uo-block-modifier post type.
	 *
	 * @return void
	 */
	public function register_post_type() {

		$labels = array(
			'name'          => 'Automator Block Modifiers',
			'singular_name' => 'Automator Block Modifier',
			'menu_name'     => 'Block Modifiers',
		);

		$args = array(
			'label'               => 'Automator Block Modifier',
			'description'         => 'Block modifier for an Uncanny WordPress Automation recipe block',
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

		register_post_type( self::POST_TYPE, apply_filters( 'automator_post_type_block_modifier_args', $args ) );
	}
}
