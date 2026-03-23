<?php
/**
 * Recipe Formatter
 *
 * Handles formatting recipe data for API responses.
 *
 * @since 7.0.0
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Recipe\Utilities;

use WP_Error;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Status;

/**
 * Recipe_Formatter Class
 *
 * Formats recipe data for consistent API responses.
 */
class Recipe_Formatter {

	/**
	 * Format recipe response data.
	 *
	 * @param array $recipe_data Recipe data array.
	 * @return array Formatted response data.
	 */
	public function format_recipe_response( array $recipe_data ) {

		return array(
			'id'             => $recipe_data['recipe_id'] ?? $recipe_data['id'] ?? null,
			'title'          => $recipe_data['recipe_title'] ?? $recipe_data['title'] ?? '',
			'status'         => $recipe_data['recipe_status'] ?? $recipe_data['status'] ?? Recipe_Status::DRAFT,
			'type'           => $recipe_data['recipe_type'] ?? $recipe_data['type'] ?? 'user',
			'notes'          => $recipe_data['notes'] ?? '',
			'meta'           => $recipe_data['meta'] ?? array(),
			'times_per_user' => $recipe_data['times_per_user'] ?? null,
			'total_times'    => $recipe_data['total_times'] ?? null,
			'throttle'       => $recipe_data['throttle'] ?? array(
				'enabled'  => false,
				'duration' => 1,
				'unit'     => 'hours',
				'scope'    => 'recipe',
			),
		);
	}


	/**
	 * Format error response.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param array  $data    Optional error data.
	 * @return WP_Error Error response.
	 */
	public function error_response( $code, $message, $data = array() ) {

		return new WP_Error( $code, $message, $data );
	}


	/**
	 * Format success response.
	 *
	 * @param string $message Success message.
	 * @param array  $data    Optional response data.
	 * @return array Success response.
	 */
	public function success_response( $message, array $data = array() ) {

		return array_merge(
			array(
				'success' => true,
				'message' => $message,
			),
			$data
		);
	}
}
