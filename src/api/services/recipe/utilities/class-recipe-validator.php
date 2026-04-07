<?php
/**
 * Recipe Validator
 *
 * Handles validation and sanitization for recipe data.
 *
 * @since 7.0.0
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Recipe\Utilities;

use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Status;

/**
 * Recipe_Validator Class
 *
 * Validates and sanitizes recipe data before persistence.
 */
class Recipe_Validator {

	/**
	 * Validate and sanitize recipe title.
	 *
	 * @param string $title Raw title input.
	 * @return string Sanitized title.
	 */
	public function validate_title( $title ) {

		$title = trim( $title );

		if ( empty( $title ) ) {
			$title = '(no-title)';
		}

		return sanitize_text_field( $title );
	}


	/**
	 * Validate and sanitize recipe notes.
	 *
	 * @param string $notes Raw notes input.
	 * @return string Sanitized notes.
	 */
	public function validate_notes( $notes ) {

		$notes = wp_kses_post( $notes );

		return sanitize_textarea_field( $notes );
	}


	/**
	 * Validate recipe type.
	 *
	 * @param string $type Recipe type.
	 * @return bool True if valid, false otherwise.
	 */
	public function is_valid_type( $type ) {

		return in_array( $type, array( 'user', 'anonymous' ), true );
	}


	/**
	 * Validate recipe status.
	 *
	 * @param string $status Recipe status.
	 * @return bool True if valid, false otherwise.
	 */
	public function is_valid_status( $status ) {

		return in_array( $status, array( Recipe_Status::DRAFT, Recipe_Status::PUBLISH ), true );
	}


	/**
	 * Validate that a recipe is ready to be published.
	 *
	 * Requires at least 1 published trigger and 1 published action.
	 * Actions may be direct children of the recipe or inside loops.
	 *
	 * @since 7.0.0
	 *
	 * @param int $recipe_id Recipe ID.
	 *
	 * @return null|\WP_Error Null on success, WP_Error if not ready.
	 */
	public function validate_publish_readiness( int $recipe_id ) {

		$missing = array();

		// Check triggers.
		$triggers = get_posts(
			array(
				'post_type'   => 'uo-trigger',
				'post_parent' => $recipe_id,
				'post_status' => 'publish',
				'numberposts' => 1,
				'fields'      => 'ids',
			)
		);

		if ( empty( $triggers ) ) {
			$missing[] = 'at least 1 live trigger (use save_trigger with status=publish)';
		}

		// Check actions — can be direct children or inside loops.
		$parent_ids = array( $recipe_id );
		$loops      = get_posts(
			array(
				'post_type'   => 'uo-loop',
				'post_parent' => $recipe_id,
				'post_status' => 'any',
				'numberposts' => 100,
				'fields'      => 'ids',
			)
		);

		if ( ! empty( $loops ) ) {
			$parent_ids = array_merge( $parent_ids, $loops );
		}

		$actions = get_posts(
			array(
				'post_type'       => 'uo-action',
				'post_parent__in' => $parent_ids,
				'post_status'     => 'publish',
				'numberposts'     => 1,
				'fields'          => 'ids',
			)
		);

		if ( empty( $actions ) ) {
			$missing[] = 'at least 1 live action (use save_action with status=publish)';
		}

		if ( ! empty( $missing ) ) {
			return new \WP_Error(
				'recipe_not_ready',
				'Cannot publish recipe. Missing: ' . implode( '; ', $missing )
				. '. Add the required components first, then set status=publish.'
			);
		}

		return null;
	}


	/**
	 * Sanitize recipe data array.
	 *
	 * @param array $data Recipe data to sanitize.
	 * @return array Sanitized recipe data.
	 */
	public function sanitize_recipe_data( array $data ) {

		$sanitized = array();

		if ( isset( $data['title'] ) ) {
			$sanitized['title'] = $this->validate_title( $data['title'] );
		}

		if ( isset( $data['notes'] ) ) {
			$sanitized['notes'] = $this->validate_notes( $data['notes'] );
		}

		if ( isset( $data['status'] ) ) {
			$sanitized['status'] = sanitize_text_field( $data['status'] );
		}

		if ( isset( $data['type'] ) ) {
			$sanitized['type'] = sanitize_text_field( $data['type'] );
		}

		if ( isset( $data['trigger_logic'] ) ) {
			$sanitized['trigger_logic'] = sanitize_text_field( $data['trigger_logic'] );
		}

		return $sanitized;
	}
}
