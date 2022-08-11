<?php

namespace Uncanny_Automator;

/**
 * Class Recipe_Post_Type
 *
 * @package Uncanny_Automator
 */
class Recipe_Post_Type {
	/**
	 *
	 */
	public function __construct() {
		// Create and register custom post type.
		add_action( 'init', array( $this, 'automator_post_type' ), 0 );

		// Default title of the New Recipe.
		add_filter( 'default_title', array( $this, 'default_recipe_title' ), 20, 2 );

		add_action( 'admin_head', array( $this, 'all_recipes_colours' ) );
	}

	/**
	 *
	 */
	public function automator_post_type() {

		if ( ! post_type_exists( 'uo-recipe' ) ) {
			$icon_url = 'data:image/svg+xml;base64,' . base64_encode( '<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><path d="m42.63 28.37c-0.27 2.32-0.27 4.67 0 6.99 0.4 3.44 6.7 3.44 7.27 0 0.37-2.31 0.37-4.65 0-6.96-0.56-3.44-6.9-3.44-7.27-0.03z" fill="#a7aaad"/><path d="m14.04 28.41c-0.37 2.31-0.37 4.65 0 6.96 0.57 3.44 6.88 3.44 7.28 0 0.27-2.32 0.27-4.67 0-6.99-0.37-3.42-6.67-3.42-7.28 0.03z" fill="#a7aaad"/><path d="m37.07 39.61c-3.13 0.82-6.41 0.82-9.54 0 0.29 5.9 9.25 5.9 9.54 0z" fill="#a7aaad"/><path d="m63.5 19.04c-0.34-3.07-2.25-4.75-5.15-5.4-8.67-1.82-17.51-2.69-26.38-2.58-8.84-0.08-17.66 0.78-26.32 2.58-2.96 0.65-4.86 2.33-5.2 5.4-0.39 4-0.53 8.01-0.4 12.02 0.09 4.27 0.61 8.52 1.56 12.69 0.65 2.73 3.6 4.49 6.05 5.52 11.5 4.91 37.1 4.91 48.65 0 2.45-1.04 5.36-2.79 6.05-5.52 0.96-4.16 1.5-8.42 1.6-12.69 0.13-4.01-0.02-8.03-0.46-12.02zm-5.42 23.64c-0.25 0.99-2.75 2.24-3.52 2.53-10.4 4.48-34.86 4.48-45.25 0-0.78-0.32-3.24-1.51-3.52-2.53-1.46-6.05-1.84-15.43-1.01-23.2 0.12-1.19 0.82-1.36 1.73-1.56 5.08-1.18 10.25-1.89 15.46-2.16 0.18-0.01 0.36 0.06 0.5 0.18 0.13 0.12 0.21 0.29 0.24 0.47 0.37 2.86 0.99 5.67 1.84 8.43 0.62 1.23 4.58 1.76 7.42 1.76 2.87 0 6.84-0.53 7.36-1.76 0.85-2.75 1.48-5.57 1.85-8.43 0.02-0.18 0.11-0.35 0.25-0.47 0.13-0.11 0.31-0.18 0.49-0.18 5.2 0.24 10.35 0.96 15.42 2.16 0.94 0.2 1.64 0.37 1.76 1.56 0.82 7.74 0.45 17.11-1.02 23.2z" fill="#a7aaad"/></svg>' );

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

	/**
	 * @return void
	 */
	public function all_recipes_colours() {
		$current_screen = get_current_screen();
		if ( ! $current_screen instanceof \WP_Screen ) {
			return;
		}
		if ( 'uo-recipe' !== $current_screen->post_type || 'edit-uo-recipe' !== $current_screen->id ) {
			return;
		}
		?>

		<style>

			.post-type-uo-recipe .wp-list-table .recipe-ui-dash.dashicons-warning {
				color: #e49d38;
			}

			.post-type-uo-recipe .wp-list-table .recipe-ui-dash.dashicons-yes-alt {
				color: #008800;
			}

		</style>

		<?php
	}
}
