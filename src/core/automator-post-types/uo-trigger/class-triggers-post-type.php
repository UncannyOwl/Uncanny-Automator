<?php

namespace Uncanny_Automator;

/**
 * Class Triggers_Post_Type
 *
 * Hidden Post Type to hold Triggers
 *
 * @package Uncanny_Automator
 */
class Triggers_Post_Type {

	/**
	 * TriggersPostType constructor.
	 */
	public function __construct() {
		// Register Custom Post Type
		add_action( 'init', array( $this, 'uo_automator_triggers' ), 0 );
	}

	/**
	 * Register Custom Post Type without a menu page (internal use only)
	 */
	public function uo_automator_triggers() {

		$labels = array(
			'name'                  => 'Automator Triggers',
			'singular_name'         => 'Automator Trigger',
			'menu_name'             => 'Triggers',
			'name_admin_bar'        => 'Post Type',
			'archives'              => 'Item Archives',
			'attributes'            => 'Item Attributes',
			'parent_item_colon'     => 'Parent Item:',
			'all_items'             => 'All Items',
			'add_new_item'          => 'Add New Item',
			'add_new'               => 'Add New',
			'new_item'              => 'New Item',
			'edit_item'             => 'Edit Item',
			'update_item'           => 'Update Item',
			'view_item'             => 'View Item',
			'view_items'            => 'View Items',
			'search_items'          => 'Search Item',
			'not_found'             => 'Not found',
			'not_found_in_trash'    => 'Not found in Trash',
			'featured_image'        => 'Featured Image',
			'set_featured_image'    => 'Set featured image',
			'remove_featured_image' => 'Remove featured image',
			'use_featured_image'    => 'Use as featured image',
			'insert_into_item'      => 'Insert into item',
			'uploaded_to_this_item' => 'Uploaded to this item',
			'items_list'            => 'Items list',
			'items_list_navigation' => 'Items list navigation',
			'filter_items_list'     => 'Filter items list',
		);
		$args   = array(
			'label'               => 'Automator Trigger',
			'description'         => 'Trigger for an Uncanny WordPress Automation',
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
			'capabilities'        => array(
				'publish_posts'       => 'manage_options',
				'edit_posts'          => 'manage_options',
				'edit_others_posts'   => 'manage_options',
				'delete_posts'        => 'manage_options',
				'delete_others_posts' => 'manage_options',
				'read_private_posts'  => 'manage_options',
				'edit_post'           => 'manage_options',
				'delete_post'         => 'manage_options',
			),
		);

		register_post_type( 'uo-trigger', apply_filters( 'automator_post_type_trigger_args', $args ) );
	}

}
