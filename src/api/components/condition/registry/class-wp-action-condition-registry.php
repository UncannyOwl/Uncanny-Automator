<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Condition\Registry;

/**
 * WordPress Action Condition Registry.
 *
 * WordPress implementation of the action condition registry interface.
 * Bridges between the new clean architecture and the legacy WordPress
 * filter-based condition registration system.
 *
 * @since 7.0.0
 */
class WP_Action_Condition_Registry implements Action_Condition_Registry {

	/**
	 * Cached conditions array.
	 *
	 * @var array|null
	 */
	private ?array $cached_conditions = null;

	/**
	 * Get all available action conditions.
	 *
	 * @return array Array of condition definitions grouped by integration.
	 */
	public function get_all_conditions(): array {
		if ( null === $this->cached_conditions ) {
			$this->cached_conditions = $this->load_conditions_from_wordpress();
		}

		return $this->cached_conditions;
	}

	/**
	 * Get conditions for a specific integration.
	 *
	 * @param string $integration_code Integration code (e.g., 'WP', 'GEN', 'LD').
	 * @return array Array of condition definitions for the integration.
	 */
	public function get_conditions_by_integration( string $integration_code ): array {
		$all_conditions = $this->get_all_conditions();

		return $all_conditions[ $integration_code ] ?? array();
	}

	/**
	 * Get a specific condition definition.
	 *
	 * @param string $integration_code Integration code.
	 * @param string $condition_code Condition code.
	 * @return array|null Condition definition or null if not found.
	 */
	public function get_condition_definition( string $integration_code, string $condition_code ): ?array {

		$fields = apply_filters( 'automator_pro_actions_conditions_fields', array(), $integration_code, $condition_code );

		$integration_conditions = $this->get_conditions_by_integration( $integration_code );

		if ( ! isset( $integration_conditions[ $condition_code ] ) ) {
			return null;
		}

		$condition = $integration_conditions[ $condition_code ];

		// Add computed fields for API compatibility
		return array(
			'integration_code' => $integration_code,
			'condition_code'   => $condition_code,
			'name'             => $condition['name'] ?? '',
			'dynamic_name'     => $condition['dynamic_name'] ?? '',
			'is_pro'           => $condition['is_pro'] ?? true,
			'requires_user'    => $condition['requires_user'] ?? false,
			'deprecated'       => $condition['deprecated'] ?? false,
			'integration_name' => $this->get_integration_name( $integration_code ),
			'fields'           => $fields,
			'manifest'         => $condition['manifest'] ?? array(),
		);
	}

	/**
	 * Check if a condition exists.
	 *
	 * @param string $integration_code Integration code.
	 * @param string $condition_code Condition code.
	 * @return bool True if condition exists.
	 */
	public function condition_exists( string $integration_code, string $condition_code ): bool {
		return null !== $this->get_condition_definition( $integration_code, $condition_code );
	}

	/**
	 * Search conditions by term.
	 *
	 * @param string $search_term Search term to match against condition names.
	 * @param string $integration_filter Optional integration to filter by.
	 * @return array Array of matching condition definitions.
	 */
	public function search_conditions( string $search_term, string $integration_filter = '' ): array {
		$all_conditions = $this->get_all_conditions();
		$results        = array();

		$search_term = strtolower( trim( $search_term ) );

		foreach ( $all_conditions as $integration_code => $conditions ) {
			// Apply integration filter if specified
			if ( ! empty( $integration_filter ) && $integration_code !== $integration_filter ) {
				continue;
			}

			foreach ( $conditions as $condition_code => $condition ) {
				$condition_name = strtolower( $condition['name'] ?? '' );
				$dynamic_name   = strtolower( $condition['dynamic_name'] ?? '' );

				// Fuzzy search: check if search term appears in name or dynamic name
				if ( false !== strpos( $condition_name, $search_term ) ||
					false !== strpos( $dynamic_name, $search_term ) ||
					false !== strpos( strtolower( $condition_code ), $search_term ) ) {

					$results[] = array(
						'integration_code' => $integration_code,
						'condition_code'   => $condition_code,
						'name'             => $condition['name'] ?? '',
						'dynamic_name'     => $condition['dynamic_name'] ?? '',
						'is_pro'           => $condition['is_pro'] ?? true,
						'requires_user'    => $condition['requires_user'] ?? false,
						'deprecated'       => $condition['deprecated'] ?? false,
						'integration_name' => $this->get_integration_name( $integration_code ),
						'relevance_score'  => $this->calculate_relevance_score( $search_term, $condition_name, $dynamic_name ),
						'manifest'         => $condition['manifest'] ?? array(),
					);
				}
			}
		}

		// Sort by relevance score (higher is better)
		usort(
			$results,
			function ( $a, $b ) {
				return $b['relevance_score'] - $a['relevance_score'];
			}
		);

		return $results;
	}

	/**
	 * Get field schema for a specific condition (JSON Schema format).
	 *
	 * @param string $integration_code Integration code.
	 * @param string $condition_code Condition code.
	 * @return array Field schema definitions for the condition in JSON Schema format.
	 */
	public function get_condition_fields( string $integration_code, string $condition_code ): array {
		$fields = $this->get_raw_condition_fields( $integration_code, $condition_code );

		if ( empty( $fields ) ) {
			return array();
		}

		// Convert WordPress field format to JSON Schema format.
		return $this->convert_fields_to_json_schema( $fields );
	}

	/**
	 * Get raw field definitions for a specific condition.
	 *
	 * Returns the raw field array from the WordPress filter without
	 * JSON Schema conversion. Includes supports_markdown and supports_tinymce flags.
	 *
	 * @param string $integration_code Integration code.
	 * @param string $condition_code Condition code.
	 * @return array Raw field definitions array.
	 */
	public function get_raw_condition_fields( string $integration_code, string $condition_code ): array {
		/**
		 * Filter to get condition field definitions.
		 *
		 * @since 4.0
		 *
		 * @param array  $fields           Empty array to populate with field definitions.
		 * @param string $integration_code Integration code (e.g., 'WP', 'GEN').
		 * @param string $condition_code   Condition code.
		 */
		$fields = apply_filters( 'automator_pro_actions_conditions_fields', array(), $integration_code, $condition_code );

		return is_array( $fields ) ? $fields : array();
	}

	/**
	 * Get available integrations that have conditions.
	 *
	 * @return array Array of integration codes with condition counts.
	 */
	public function get_available_integrations(): array {
		$all_conditions = $this->get_all_conditions();
		$integrations   = array();

		foreach ( $all_conditions as $integration_code => $conditions ) {
			$integrations[] = array(
				'code'  => $integration_code,
				'name'  => $this->get_integration_name( $integration_code ),
				'count' => count( $conditions ),
			);
		}

		// Sort by condition count (descending)
		usort(
			$integrations,
			function ( $a, $b ) {
				return $b['count'] - $a['count'];
			}
		);

		return $integrations;
	}

	/**
	 * Load conditions from WordPress filter system.
	 *
	 * @return array Conditions array from WordPress.
	 */
	private function load_conditions_from_wordpress(): array {
		// This is the legacy filter that provides all action conditions
		$conditions = apply_filters( 'automator_pro_actions_conditions_list', array() );

		return is_array( $conditions ) ? $conditions : array();
	}

	/**
	 * Get integration name from code.
	 *
	 * @param string $integration_code Integration code.
	 * @return string Human-readable integration name.
	 */
	private function get_integration_name( string $integration_code ): string {
		// Use existing Automator integration registry
		if ( function_exists( 'Automator' ) && method_exists( \Automator(), 'get_integration' ) ) {
			$integration = \Automator()->get_integration( $integration_code );
			if ( $integration && isset( $integration['name'] ) ) {
				return $integration['name'];
			}
		}

		// Fallback to common mappings
		$fallback_names = array(
			'WP'  => 'WordPress',
			'GEN' => 'General',
			'LD'  => 'LearnDash',
			'WC'  => 'WooCommerce',
			'UOA' => 'Automator',
			'GP'  => 'GravityForms',
			'CF7' => 'Contact Form 7',
		);

		return $fallback_names[ $integration_code ] ?? $integration_code;
	}

	/**
	 * Calculate relevance score for search results.
	 *
	 * @param string $search_term Search term.
	 * @param string $condition_name Condition name.
	 * @param string $dynamic_name Dynamic name template.
	 * @return float Relevance score (0-100).
	 */
	private function calculate_relevance_score( string $search_term, string $condition_name, string $dynamic_name ): float {
		$score = 0;

		// Exact match in name gets highest score
		if ( false !== strpos( $condition_name, $search_term ) ) {
			$score += 50;
			// Bonus for exact match
			if ( $condition_name === $search_term ) {
				$score += 30;
			}
		}

		// Match in dynamic name gets medium score
		if ( false !== strpos( $dynamic_name, $search_term ) ) {
			$score += 20;
		}

		// Bonus for matches at the beginning of strings
		if ( 0 === strpos( $condition_name, $search_term ) ) {
			$score += 15;
		}

		if ( 0 === strpos( $dynamic_name, $search_term ) ) {
			$score += 10;
		}

		return $score;
	}

	/**
	 * Convert WordPress field format to JSON Schema format.
	 *
	 * @param array $fields WordPress field definitions.
	 * @return array JSON Schema field definitions.
	 */
	private function convert_fields_to_json_schema( array $fields ): array {
		$schema_fields = array();

		foreach ( $fields as $field ) {
			if ( ! isset( $field['option_code'] ) ) {
				continue;
			}

			$field_code   = $field['option_code'];
			$field_schema = array(
				'type'        => 'string', // Default type
				'description' => $field['label'] ?? $field_code,
			);

			// Add required flag
			if ( isset( $field['required'] ) && $field['required'] ) {
				$field_schema['required'] = true;
			}

			// Handle different input types
			if ( isset( $field['input_type'] ) ) {
				switch ( $field['input_type'] ) {
					case 'select':
					case 'dropdown':
						$field_schema['type'] = 'string';
						if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
							$field_schema['enum']      = array_column( $field['options'], 'value' );
							$field_schema['enumNames'] = array_column( $field['options'], 'text' );
						}
						break;

					case 'checkbox':
						$field_schema['type'] = 'boolean';
						break;

					case 'number':
						$field_schema['type'] = 'number';
						if ( isset( $field['min'] ) ) {
							$field_schema['minimum'] = $field['min'];
						}
						if ( isset( $field['max'] ) ) {
							$field_schema['maximum'] = $field['max'];
						}
						break;

					case 'textarea':
						$field_schema['type']   = 'string';
						$field_schema['format'] = 'textarea';
						break;

					default:
						$field_schema['type'] = 'string';
						break;
				}
			}

			$schema_fields[ $field_code ] = $field_schema;
		}

		return $schema_fields;
	}
}
