<?php

namespace Uncanny_Automator;

/**
 * Class Recipe_Post_Type
 *
 * @package Uncanny_Automator
 */
class Recipe_Post_Type {
	public function __construct() {
		// Create and register custom post type.
		add_action( 'init', array( $this, 'automator_post_type' ), 0 );

		// Default title of the New Recipe.
		add_filter( 'default_title', array( $this, 'default_recipe_title' ), 20, 2 );
	}

	/**
	 *
	 */
	public function automator_post_type() {

		if ( ! post_type_exists( 'uo-recipe' ) ) {
			$icon_url = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48c3ZnIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgdmlld0JveD0iMCAwIDI2LjQ1IDI5LjUxIj48cGF0aCBkPSJNMjIuODkgNS41N2MtLjE4LS4xOC0uMzYtLjM1LS41NS0uNTFsMi41NS0yLjU1YTEuNDcgMS40NyAwIDAwLjQzLTEuMDcgMS40OSAxLjQ5IDAgMDAtMi41NS0xbC0yLjk1IDNhMTIuMDkgMTIuMDkgMCAwMC01LjUyLTEuNDNoLTIuMTlhMTIgMTIgMCAwMC01LjUyIDEuMzNMMy42NC40NGExLjUgMS41IDAgMDAtMi4xLjMxIDEuNDkgMS40OSAwIDAwMCAxLjc2bDIuNTMgMi41NUExMi4wNyAxMi4wNyAwIDAwMCAxNC4xN2E0LjM0IDQuMzQgMCAwMDQuMyA0LjM0aDE3LjgxYTQuMzQgNC4zNCAwIDAwNC4zNC00LjM0IDEyLjE1IDEyLjE1IDAgMDAtMy41Ni04LjZ6TTcuMiA3LjUxYTEuNSAxLjUgMCAxMTEuNSAxLjUgMS41IDEuNSAwIDAxLTEuNS0xLjV6bTkuNTQgNS43MWE1IDUgMCAwMS03LjA3IDAgMSAxIDAgMDExLjQxLTEuNDEgMyAzIDAgMDA0LjI0IDAgMSAxIDAgMDExLjQxIDAgMSAxIDAgMDEuMDEgMS40MXptMS00LjIxYTEuNSAxLjUgMCAxMTEuNS0xLjUgMS41IDEuNSAwIDAxLTEuNSAxLjV6TTIyLjcgMjAuNTFoLTE5YTMuNzEgMy43MSAwIDAwLTMuNyAzLjd2MS42YTMuNzEgMy43MSAwIDAwMy43IDMuN2gxOWEzLjcxIDMuNzEgMCAwMDMuNy0zLjd2LTEuNmEzLjcxIDMuNzEgMCAwMC0zLjctMy43em0tNi41IDdoLTMuNXYtMmExIDEgMCAwMC0yIDB2MmgtLjVhMSAxIDAgMDEtMS0xIDQgNCAwIDAxOCAwIDEgMSAwIDAxLTEgMXoiIGZpbGw9IiM4Mjg3OEMiLz48Y2lyY2xlIGN4PSIxNC43IiBjeT0iMjUuNTEiIHI9IjEiIGZpbGw9IiM4Mjg3OEMiLz48L3N2Zz4=';

			$labels = array(
				'name'                  => esc_attr__( 'Recipes', 'uncanny-automator' ),
				'singular_name'         => esc_attr__( 'Recipe', 'uncanny-automator' ),
				'menu_name'             => 'Automator',
				/* translators: 1. Trademarked term */
				'name_admin_bar'        => sprintf( esc_attr__( '%1$s recipe', 'uncanny-automator' ), 'Automator' ),
				'archives'              => 'Recipe Archives',
				'attributes'            => 'Recipe Attributes',
				'parent_item_colon'     => 'Parent Recipe:',
				'all_items'             => esc_attr__( 'All recipes', 'uncanny-automator' ),
				/* translators: Non-personal infinitive verb */
				'add_new_item'          => esc_attr__( 'Add new recipe', 'uncanny-automator' ),
				/* translators: Non-personal infinitive verb */
				'add_new'               => esc_attr_x( 'Add new', 'Recipe', 'uncanny-automator' ),
				'new_item'              => esc_attr__( 'New recipe', 'uncanny-automator' ),
				/* translators: Non-personal infinitive verb */
				'edit_item'             => esc_attr__( 'Edit recipe', 'uncanny-automator' ),
				/* translators: Non-personal infinitive verb */
				'update_item'           => esc_attr__( 'Update recipe', 'uncanny-automator' ),
				/* translators: Non-personal infinitive verb */
				'view_item'             => esc_attr__( 'View recipe', 'uncanny-automator' ),
				/* translators: Non-personal infinitive verb */
				'view_items'            => esc_attr__( 'View recipes', 'uncanny-automator' ),
				/* translators: Non-personal infinitive verb */
				'search_items'          => esc_attr__( 'Search recipes', 'uncanny-automator' ),
				'not_found'             => esc_attr_x( 'Not found', 'Recipe', 'uncanny-automator' ),
				'not_found_in_trash'    => esc_attr_x( 'Not found in trash', 'Recipe', 'uncanny-automator' ),
				'featured_image'        => 'Featured Image',
				'set_featured_image'    => 'Set Featured Image',
				'remove_featured_image' => 'Remove Featured Image',
				'use_featured_image'    => 'Use as Featured Image',
				'insert_into_item'      => 'Insert Into the Recipe',
				'uploaded_to_this_item' => 'Uploaded to This Recipe',
				'items_list'            => 'Recipes List',
				'items_list_navigation' => 'Recipes List Navigation',
				'filter_items_list'     => 'Filter Recipes List',
			);
			$args   = array(
				'label'               => esc_attr__( 'Recipe', 'uncanny-automator' ),
				'description'         => 'Uncanny WordPress Automation',
				'labels'              => $labels,
				'supports'            => array( 'title', 'author' ),
				'hierarchical'        => false,
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_position'       => 40,
				'menu_icon'           => $icon_url,
				'show_in_admin_bar'   => true,
				'show_in_nav_menus'   => false,
				'can_export'          => true,
				'taxonomies'          => array( 'recipe_category', 'recipe_tag' ),
				'has_archive'         => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => true,
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
				'show_in_rest'        => true,
				'rest_base'           => 'uap',
			);

			register_post_type( 'uo-recipe', apply_filters( 'automator_post_type_recipe_args', $args ) );
		}
	}

	/**
	 * @param $post_title
	 * @param $post
	 *
	 * @return string
	 */
	public function default_recipe_title( $post_title, $post ) {

		if ( 'uo-recipe' === (string) $post->post_type ) {
			return esc_attr__( 'New recipe', 'uncanny-automator' );
		}

		return $post_title;
	}
}
