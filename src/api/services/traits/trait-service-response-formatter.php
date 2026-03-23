<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Traits;

/**
 * Service Response Formatter Trait
 *
 * Provides standardized response formatting for all service layer classes.
 * Ensures consistent response structure, error handling, and data formatting.
 *
 * @since 7.0.0
 * @package Uncanny_Automator
 */
trait Service_Response_Formatter {

	/**
	 * Create a standardized success response.
	 *
	 * @since 7.0.0
	 * @param array $data Additional data to include in response.
	 * @return array Success response with consistent structure.
	 */
	protected function success_response( array $data = array() ): array {
		return array_merge(
			array( 'success' => true ),
			$data
		);
	}

	/**
	 * Create a standardized error response.
	 *
	 * @since 7.0.0
	 * @param string $code Error code.
	 * @param string $message Error message.
	 * @param array  $data Additional error context data.
	 * @return \WP_Error Error response.
	 */
	protected function error_response( string $code, string $message, array $data = array() ): \WP_Error {
		return new \WP_Error( $code, $message, $data );
	}

	/**
	 * Format recipe data for response.
	 *
	 * @since 7.0.0
	 * @param \Uncanny_Automator\Api\Components\Recipe\Recipe $recipe Recipe entity.
	 * @return array Formatted recipe data.
	 */
	protected function format_recipe_response( $recipe ): array {
		if ( method_exists( $recipe, 'to_array' ) ) {
			return $recipe->to_array();
		}

		// Fallback for legacy data structures
		return array(
			'id'     => $recipe->get_recipe_id()->get_value(),
			'title'  => $recipe->get_recipe_title()->get_value(),
			'status' => $recipe->get_recipe_status()->get_value(),
			'type'   => $recipe->get_recipe_type()->get_value(),
		);
	}

	/**
	 * Format multiple recipes for response.
	 *
	 * @since 7.0.0
	 * @param array $recipes Array of Recipe entities.
	 * @return array Array of formatted recipe data.
	 */
	protected function format_recipes_response( array $recipes ): array {
		return array_map(
			function ( $recipe ) {
				return $this->format_recipe_response( $recipe );
			},
			$recipes
		);
	}

	/**
	 * Format trigger data for response.
	 *
	 * @since 7.0.0
	 * @param array $trigger Trigger data from registry.
	 * @return array Formatted trigger data with consistent structure.
	 */
	protected function format_trigger_response( array $trigger ): array {
		$formatted = array(
			'code'        => $trigger['trigger_code'] ?? $trigger['code'] ?? '',
			'type'        => $trigger['trigger_type'] ?? $trigger['type'] ?? '',
			'integration' => $trigger['integration'] ?? '',
		);

		// Include optional fields if present
		if ( isset( $trigger['sentence'] ) ) {
			$formatted['sentence'] = $trigger['sentence'];
		}

		if ( isset( $trigger['input_schema'] ) ) {
			$formatted['input_schema'] = $trigger['input_schema'];
		}

		if ( isset( $trigger['fields'] ) ) {
			$formatted['fields'] = $trigger['fields'];
		}

		return $formatted;
	}

	/**
	 * Format multiple triggers for response.
	 *
	 * @since 7.0.0
	 * @param array $triggers Array of trigger data from registry.
	 * @return array Array of formatted trigger data.
	 */
	protected function format_triggers_response( array $triggers ): array {
		return array_map(
			function ( $trigger ) {
				return $this->format_trigger_response( $trigger );
			},
			$triggers
		);
	}

	/**
	 * Format list response with count and items.
	 *
	 * @since 7.0.0
	 * @param array  $items Array of items to return.
	 * @param string $item_key Key name for items array (e.g., 'recipes', 'triggers').
	 * @param string $count_key Key name for count (e.g., 'recipe_count', 'count').
	 * @param array  $additional_data Additional data to include in response.
	 * @return array Formatted list response.
	 */
	protected function format_list_response( array $items, string $item_key, string $count_key = 'count', array $additional_data = array() ): array {
		return $this->success_response(
			array_merge(
				array(
					$count_key => count( $items ),
					$item_key  => $items,
				),
				$additional_data
			)
		);
	}

	/**
	 * Format search response with query metadata.
	 *
	 * @since 7.0.0
	 * @param array  $items Search results.
	 * @param string $query Original search query.
	 * @param string $item_key Key name for items array.
	 * @param string $count_key Key name for count.
	 * @param array  $filters Applied filters.
	 * @return array Formatted search response.
	 */
	protected function format_search_response( array $items, string $query, string $item_key, string $count_key = 'count', array $filters = array() ): array {
		$response = array(
			'query'    => $query,
			$count_key => count( $items ),
			$item_key  => $items,
		);

		if ( ! empty( $filters ) ) {
			$response['filters'] = $filters;
		}

		return $this->success_response( $response );
	}

	/**
	 * Format statistics response.
	 *
	 * @since 7.0.0
	 * @param array $stats Statistics data.
	 * @return array Formatted statistics response.
	 */
	protected function format_statistics_response( array $stats ): array {
		return $this->success_response(
			array( 'stats' => $stats )
		);
	}

	/**
	 * Validate required parameters.
	 *
	 * @since 7.0.0
	 * @param array $params Parameters to validate.
	 * @param array $required Required parameter names.
	 * @return \WP_Error|true WP_Error if validation fails, true if valid.
	 */
	protected function validate_required_params( array $params, array $required ) {
		$missing = array();

		foreach ( $required as $param ) {
			if ( ! isset( $params[ $param ] ) || '' === $params[ $param ] ) {
				$missing[] = $param;
			}
		}

		if ( ! empty( $missing ) ) {
			return $this->error_response(
				'missing_required_params',
				sprintf( 'Missing required parameters: %s', implode( ', ', $missing ) ),
				array( 'missing_params' => $missing )
			);
		}

		return true;
	}

	/**
	 * Validate enum parameter.
	 *
	 * @since 7.0.0
	 * @param string $value Value to validate.
	 * @param array  $allowed Allowed values.
	 * @param string $param_name Parameter name for error message.
	 * @return \WP_Error|true WP_Error if invalid, true if valid.
	 */
	protected function validate_enum_param( string $value, array $allowed, string $param_name ) {
		if ( ! in_array( $value, $allowed, true ) ) {
			return $this->error_response(
				'invalid_' . $param_name,
				sprintf( 'Invalid %s. Must be one of: %s', $param_name, implode( ', ', $allowed ) ),
				array(
					'allowed_values' => $allowed,
					'provided_value' => $value,
				)
			);
		}

		return true;
	}
}
