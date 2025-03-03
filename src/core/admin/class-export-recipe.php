<?php

namespace Uncanny_Automator;

use WP_Error;

/**
 * Class Export_Recipe
 *
 * @package Uncanny_Automator
 */
class Export_Recipe {

	/**
	 * Copy recipe parts class instance.
	 *
	 * @var \Uncanny_Automator\Copy_Recipe_Parts
	 */
	public $copy_recipe_parts = null;

	/**
	 * Export_Recipe constructor.
	 */
	public function __construct() {

		add_action( 'admin_init', array( $this, 'export_recipe_json' ) );
		add_filter( 'post_row_actions', array( $this, 'add_export_action_rows' ), 10, 2 );
		add_filter( 'bulk_actions-edit-uo-recipe', array( $this, 'add_bulk_export_action' ) );
		add_filter( 'handle_bulk_actions-edit-uo-recipe', array( $this, 'handle_bulk_export_action' ), 10, 3 );

	}

	/**
	 * Handle the export recipe action.
	 *
	 * @param $recipe_id
	 *
	 * @return void
	 */
	public function export_recipe_json() {

		if ( ! automator_filter_has_var( 'action' ) ) {
			return;
		}

		if ( 'export_recipe' !== automator_filter_input( 'action' ) ) {
			return;
		}

		if ( ! automator_filter_has_var( '_wpnonce' ) ) {
			$this->die_with_error( _x( 'Security issue, invalid nonce. Please refresh the page and try again.', 'Export Recipe', 'uncanny-automator' ) );
		}

		if ( ! wp_verify_nonce( automator_filter_input( '_wpnonce' ), 'Aut0Mat0R' ) ) {
			$this->die_with_error( _x( 'Security issue, invalid nonce. Please refresh the page and try again.', 'Export Recipe', 'uncanny-automator' ) );
		}

		$recipe_id = absint( automator_filter_input( 'post' ) );

		$json = $this->fetch_recipe_as_json( $recipe_id );
		$json = apply_filters( 'automator_recipe_export_json', $json, $recipe_id );

		if ( is_wp_error( $json ) ) {
			$this->die_with_error( $json->get_error_message() );
		}

		$filename = $this->generate_filename( $recipe_id );

		$this->handle_download( $json, $filename );
	}

	/**
	 * Handle the bulk export action.
	 *
	 * @param string $redirect_to
	 * @param string $doaction
	 * @param array $post_ids
	 *
	 * @return void
	 */
	public function handle_bulk_export_action( $redirect_to, $doaction, $post_ids ) {

		if ( $doaction !== 'export_recipes' ) {
			return $redirect_to;
		}

		if ( ! wp_verify_nonce( automator_filter_input( '_wpnonce' ), 'bulk-posts' ) ) {
			$this->die_with_error( _x( 'Security issue, invalid nonce. Please refresh the page and try again.', 'Export Recipe', 'uncanny-automator' ) );
			return;
		}

		if ( empty( $post_ids ) ) {
			$this->die_with_error( _x( 'No recipes selected for export.', 'Export Recipe', 'uncanny-automator' ) );
			return;
		}

		// If only a single recipe is selected, export it directly.
		if ( count( $post_ids ) === 1 ) {
			$recipe_id = reset( $post_ids );
			$json      = $this->fetch_recipe_as_json( $recipe_id );
			$json      = apply_filters( 'automator_recipe_export_json', $json, $recipe_id );

			if ( is_wp_error( $json ) ) {
				$this->die_with_error( $json->get_error_message(), true );
				return;
			}

			$filename = $this->generate_filename( $recipe_id );
			$this->handle_download( $json, $filename );
			return;
		}

		// Generate a single JSON file for all the selected recipes.
		$recipes = array();
		foreach ( $post_ids as $post_id ) {
			$json = $this->fetch_recipe_as_json( $post_id, false );
			$json = apply_filters( 'automator_recipe_export_json', $json, $post_id );
			if ( is_wp_error( $json ) ) {
				$this->die_with_error( $json->get_error_message(), true );
				return;
			}
			// Decode the JSON data and add it to the recipes array to avoid excessive encoding.
			$recipes[] = json_decode( $json );
		}

		// Set the filename for the exported recipes.
		$filename = $this->generate_bulk_export_filename( $post_ids );
		$this->handle_download( wp_json_encode( $recipes, JSON_UNESCAPED_UNICODE ), $filename );
	}

	/**
	 * Handle the download of the JSON file.
	 *
	 * @param string $json
	 * @param string $filename
	 *
	 * @return void
	 */
	private function handle_download( $json, $filename ) {
		// Set the headers to force download the JSON file
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '.json"' );

		// Output the JSON data
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit();
	}

	/**
	 * Add the export action to the bulk actions dropdown.
	 *
	 * @param array $actions
	 *
	 * @return array
	 */
	public function add_bulk_export_action( $actions ) {
		$actions['export_recipes'] = esc_html__( 'Export', 'uncanny-automator' );
		return $actions;
	}

	/**
	 * Add the export action to the row actions.
	 *
	 * @param array $actions
	 * @param \WP_Post $post
	 *
	 * @return array
	 */
	public function add_export_action_rows( $actions, $post ) {

		if ( 'uo-recipe' !== $post->post_type ) {
			return $actions;
		}

		$actions['export'] = sprintf(
			'<a href="%s" title="%s">%s</a>',
			esc_url(
				add_query_arg(
					array(
						'action'   => 'export_recipe',
						'post'     => $post->ID,
						'_wpnonce' => wp_create_nonce( 'Aut0Mat0R' ),
					)
				)
			),
			esc_attr( esc_html__( 'Export this recipe', 'uncanny-automator' ) ),
			esc_html( esc_html__( 'Export', 'uncanny-automator' ) )
		);

		return $actions;
	}

	/**
	 * Validate the recipe ID provided for export.
	 *
	 * @param int $recipe_id
	 *
	 * @return mixed - WP_Error if the recipe ID is invalid, otherwise the recipe ID.
	 */
	public function validate_recipe_id( $recipe_id ) {

		// Check if the post ID is valid
		if ( ! is_numeric( $recipe_id ) || null === get_post( $recipe_id ) ) {
			return new WP_Error(
				'invalid_recipe_id',
				sprintf(
					/* translators: %d: Recipe ID */
					_x( 'Invalid recipe ID: %d', 'Export Recipe', 'uncanny-automator' ),
					$recipe_id
				)
			);
		}

		// Check if the post type is valid
		if ( 'uo-recipe' !== get_post_type( $recipe_id ) ) {
			return new WP_Error(
				'invalid_post_type',
				sprintf(
					/* translators: %d: Recipe ID */
					_x( 'Invalid post type for recipe ID: %d', 'Export Recipe', 'uncanny-automator' ),
					$recipe_id
				)
			);
		}

		return absint( $recipe_id );
	}

	/**
	 * Fetch the recipe data as JSON.
	 *
	 * @param int $recipe_id
	 *
	 * @return string - JSON encoded recipe data.
	 */
	public function fetch_recipe_as_json( $recipe_id ) {
		// Check if the post ID is valid
		$recipe_id = $this->validate_recipe_id( $recipe_id );

		if ( is_wp_error( $recipe_id ) ) {
			return $recipe_id;
		}

		$recipe = (object) array(
			'recipe'   => array(
				'post' => get_post( $recipe_id ),
				'meta' => $this->fetch_post_meta( $recipe_id ),
			),
			'triggers' => $this->fetch_recipe_parts( $recipe_id, 'uo-trigger' ),
			'actions'  => $this->fetch_recipe_parts( $recipe_id, 'uo-action' ),
			'loops'    => $this->fetch_recipe_parts( $recipe_id, 'uo-loop' ),
			'closure'  => $this->fetch_recipe_parts( $recipe_id, 'uo-closure' ),
		);

		$recipe = apply_filters( 'automator_recipe_export_object', $recipe );
		return wp_json_encode( $recipe, JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Fetch the post meta for a given post ID.
	 *
	 * @param int $post_id
	 *
	 * @return array - JSON encoded post meta data.
	 */
	public function fetch_post_meta( $post_id ) {
		// Check if the post ID is valid
		if ( ! is_numeric( $post_id ) || null === get_post( $post_id ) ) {
			return array( 'error' => 'Invalid post ID' );
		}

		return get_post_meta( $post_id );
	}

	/**
	 * Fetch the recipe parts for a given post ID as JSON.
	 *
	 * @param int $parent_id - The post ID of the recipe or loop to fetch parts for.
	 * @param string $post_type - The post type of the recipe parts to fetch.
	 *
	 * @return mixed
	 */
	public function fetch_recipe_parts( $post_id, $post_type ) {

		if ( is_null( $this->copy_recipe_parts ) ) {
			$this->copy_recipe_parts = Automator_Load::get_core_class_instance( 'Copy_Recipe_Parts' );
		}

		$recipe_parts = $this->copy_recipe_parts->get_recipe_parts_posts( $post_type, $post_id );
		if ( empty( $recipe_parts ) ) {
			return false;
		}

		$parts = array();
		foreach ( $recipe_parts as $r => $recipe_part ) {

			if ( $post_type !== $recipe_part->post_type ) {
				continue;
			}

			$parts[ $r ] = (object) array(
				'post' => $recipe_part,
				'meta' => $this->fetch_post_meta( $recipe_part->ID ),
			);

			if ( 'uo-loop' === $post_type ) {
				$parts[ $r ]->loops = array(
					'filters' => $this->fetch_recipe_parts( $recipe_part->ID, 'uo-loop-filter' ),
					'actions' => $this->fetch_recipe_parts( $recipe_part->ID, 'uo-action' ),
				);
			}
		}

		return ( empty( (array) $parts ) ) ? false : apply_filters( 'automator_recipe_export_parts', $parts, $recipe_parts, $post_id, $post_type );
	}

	/**
	 * Generate a filename for the exported recipe.
	 *
	 * @param int $recipe_id
	 *
	 * @return string
	 */
	public function generate_filename( $recipe_id ) {

		$filename = 'recipe-';

		// Get the title of the recipe
		$title = get_the_title( $recipe_id );

		// If the title is empty, use the id
		$filename .= ! empty( $title ) ? sanitize_title( $title ) : 'id-' . $recipe_id;

		return apply_filters( 'automator_recipe_export_filename', $filename, $recipe_id );
	}

	/**
	 * Generate a filename for bulk exported recipes.
	 *
	 * @param array $recipe_ids
	 *
	 * @return string
	 */
	public function generate_bulk_export_filename( $recipe_ids ) {
		$filename = 'recipes-' . wp_date( 'Y-m-d-H-i-s' );

		return apply_filters( 'automator_recipe_bulk_export_filename', $filename, $recipe_ids );
	}

	/**
	 * Kill processing and display an error message.
	 *
	 * @param string $message - The message to display.
	 *
	 * @return sting - The error message.
	 */
	public function die_with_error( $message ) {
		wp_die(
			esc_attr(
				sprintf(
					/* translators: %s: The error message */
					_x( 'Recipe Export Failed : %s', 'Export Recipe', 'uncanny-automator' ),
					$message
				)
			)
		);
	}

}
