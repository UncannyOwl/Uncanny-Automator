<?php

namespace Uncanny_Automator;

/**
 * Class Actions_Post_Type
 *
 * Hidden Post type to hold Actions
 *
 * @package uncanny_automator
 */
class Actions_Post_Type {

	/**
	 * Actions post type constructor.
	 */
	public function __construct() {

		// Register Custom Post Type
		add_action( 'init', array( $this, 'uo_automator_actions' ), 0 );
	}

	/**
	 * Register Custom Post Type without a menu page (internal use only)
	 */
	public function uo_automator_actions() {

		$labels = array(
			'name'                  => __( 'Automator Actions', 'uncanny-automator' ),
			'singular_name'         => __( 'Automator Action', 'uncanny-automator' ),
			'menu_name'             => __( 'Actions', 'uncanny-automator' ),
			'name_admin_bar'        => __( 'Post Type', 'uncanny-automator' ),
			'archives'              => __( 'Item Archives', 'uncanny-automator' ),
			'attributes'            => __( 'Item Attributes', 'uncanny-automator' ),
			'parent_item_colon'     => __( 'Parent Item:', 'uncanny-automator' ),
			'all_items'             => __( 'All Items', 'uncanny-automator' ),
			'add_new_item'          => __( 'Add New Item', 'uncanny-automator' ),
			'add_new'               => __( 'Add New', 'uncanny-automator' ),
			'new_item'              => __( 'New Item', 'uncanny-automator' ),
			'edit_item'             => __( 'Edit Item', 'uncanny-automator' ),
			'update_item'           => __( 'Update Item', 'uncanny-automator' ),
			'view_item'             => __( 'View Item', 'uncanny-automator' ),
			'view_items'            => __( 'View Items', 'uncanny-automator' ),
			'search_items'          => __( 'Search Item', 'uncanny-automator' ),
			'not_found'             => __( 'Not found', 'uncanny-automator' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'uncanny-automator' ),
			'featured_image'        => __( 'Featured Image', 'uncanny-automator' ),
			'set_featured_image'    => __( 'Set featured image', 'uncanny-automator' ),
			'remove_featured_image' => __( 'Remove featured image', 'uncanny-automator' ),
			'use_featured_image'    => __( 'Use as featured image', 'uncanny-automator' ),
			'insert_into_item'      => __( 'Insert into item', 'uncanny-automator' ),
			'uploaded_to_this_item' => __( 'Uploaded to this item', 'uncanny-automator' ),
			'items_list'            => __( 'Items list', 'uncanny-automator' ),
			'items_list_navigation' => __( 'Items list navigation', 'uncanny-automator' ),
			'filter_items_list'     => __( 'Filter items list', 'uncanny-automator' ),
		);

		$args   = array(
			'label'               => __( 'Automator Action', 'uncanny-automator' ),
			'description'         => __( 'Action for an Uncanny WordPress Automation', 'uncanny-automator' ),
			'labels'              => $labels,
			'supports'            => array( 'title' ),
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'menu_position'       => 5,
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'can_export'          => false,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => 'page',
		);

		register_post_type( 'uo-action', $args );
	}
}
