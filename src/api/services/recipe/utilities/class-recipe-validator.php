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
