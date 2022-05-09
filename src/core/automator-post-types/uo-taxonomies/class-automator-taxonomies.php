<?php

namespace Uncanny_Automator;

/**
 * Class Recipe_Taxonomies
 *
 * @package Uncanny_Automator
 */
class Automator_Taxonomies {

	/**
	 * RecipePostType constructor.
	 */
	public function __construct() {

		// Create and register custom taxonomies
		add_filter( 'init', array( $this, 'recipe_taxonomies' ) );

		// Create Filter drop downs for recipe taxonomies
		add_action( 'restrict_manage_posts', array( $this, 'filter_taxonomies' ), 10, 2 );

		// Add the custom columns to the book post type
		add_filter( 'manage_uo-recipe_posts_columns', array( $this, 'add_custom_columns' ), 10, 3 );

		// Add the data to custom columns
		add_action( 'manage_uo-recipe_posts_custom_column', array( $this, 'add_custom_column_data' ), 10, 2 );

	}

	/**
	 *
	 */
	public function recipe_taxonomies() {

		// Add recipe category
		register_taxonomy(
			'recipe_category', // The name of the taxonomy. Name should be in slug form (must not contain capital letters or spaces).
			'uo-recipe', // post type name
			array(
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'hierarchical'       => true,
				'label'              => esc_attr__( 'Recipe category', 'uncanny-automator' ), // display name
				'labels'             => array(
					'menu_name' => esc_attr__( 'Categories', 'uncanny-automator' ),
				),
				'query_var'          => true,
				'rewrite'            => array(
					'slug'       => 'uo-recipe', // This controls the base slug that will display before each term
					'with_front' => false, // Don't display the category base before
				),
				'show_in_nav_menus'  => false,
				'capabilities'       => array(
					'manage_terms' => 'manage_options',
					'edit_terms'   => 'manage_options',
					'delete_terms' => 'manage_options',
					'assign_terms' => 'manage_options',
				),
			)
		);

		// Add recipe tag
		register_taxonomy(
			'recipe_tag', // The name of the taxonomy. Name should be in slug form (must not contain capital letters or spaces).
			'uo-recipe', // post type name
			array(
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'hierarchical'       => false,
				'label'              => esc_attr__( 'Recipe tag', 'uncanny-automator' ), // display name
				'labels'             => array(
					'menu_name' => esc_attr__( 'Tags', 'uncanny-automator' ),
				),
				'query_var'          => true,
				'rewrite'            => array(
					'slug'       => 'uo-recipe', // This controls the base slug that will display before each term
					'with_front' => false, // Don't display the category base before
				),
				'show_in_nav_menus'  => false,
				'capabilities'       => array(
					'manage_terms' => 'manage_options',
					'edit_terms'   => 'manage_options',
					'delete_terms' => 'manage_options',
					'assign_terms' => 'manage_options',
				),
			)
		);
	}

	/**
	 * @param $post_type
	 * @param $which
	 */
	public function filter_taxonomies( $post_type, $which ) {

		// Apply this only on a specific post type
		if ( 'uo-recipe' !== $post_type ) {
			return;
		}

		// A list of taxonomy slugs to filter by
		$taxonomies = array( 'recipe_category', 'recipe_tag' );

		foreach ( $taxonomies as $taxonomy_slug ) {

			// Display filter HTML
			wp_dropdown_categories(
				array(
					'show_option_all' => get_taxonomy( $taxonomy_slug )->labels->all_items,
					'hierarchical'    => 1,
					'show_count'      => 0,
					'orderby'         => 'name',
					'name'            => $taxonomy_slug,
					'value_field'     => 'slug',
					'taxonomy'        => $taxonomy_slug,
					'hide_if_empty'   => true,
					'hide_empty'      => false,
				)
			);
		}

	}

	/**
	 * @param $columns
	 *
	 * @return mixed
	 */
	public function add_custom_columns( $columns ) {

		$term_args = array(
			'taxonomy'   => 'recipe_category',
			'hide_empty' => false,
			'fields'     => 'all',
			'count'      => true,
			'number'     => 1,
		);
		$cats      = new \WP_Term_Query( $term_args );

		if ( ! empty( $cats->terms ) ) {
			$columns['recipe_category'] = esc_attr__( 'Categories', 'uncanny-automator' );
		}

		$term_args = array(
			'taxonomy'   => 'recipe_tag',
			'hide_empty' => false,
			'fields'     => 'all',
			'count'      => true,
			'number'     => 1,
		);
		$tags      = new \WP_Term_Query( $term_args );

		if ( ! empty( $tags->terms ) ) {
			$columns['recipe_tag'] = esc_attr__( 'Tags', 'uncanny-automator' );
		}

		return $columns;
	}

	/**
	 * @param $column
	 * @param $post_id
	 */
	public function add_custom_column_data( $column, $post_id ) {

		switch ( $column ) {

			case 'recipe_category':
				$terms = get_the_term_list( $post_id, 'recipe_category', '', ',', '' );
				if ( is_string( $terms ) ) {
					echo wp_kses_post( $terms );
				}
				break;

			case 'recipe_tag':
				$terms = get_the_term_list( $post_id, 'recipe_tag', '', ',', '' );
				if ( is_string( $terms ) ) {
					echo wp_kses_post( $terms );
				}
				break;

		}
	}
}
