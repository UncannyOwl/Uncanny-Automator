<?php
/**
 * Field Label Resolver.
 *
 * Resolves field labels and readable values from configuration fields.
 * Shared utility used by both Action_Builder and Trigger_CRUD_Service.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Shared\Sentence_Html;

use Uncanny_Automator\Services\Integrations\Fields;

/**
 * Class Field_Label_Resolver
 *
 * Resolves field labels and option readable values from configuration fields.
 */
class Field_Label_Resolver {

	/**
	 * Get configuration fields for a given code.
	 *
	 * @param string $code        The trigger/action code.
	 * @param string $object_type Either 'triggers' or 'actions'.
	 *
	 * @return array Configuration fields.
	 */
	public function get_configuration_fields( string $code, string $object_type ): array {
		$fields = new Fields();
		$fields->set_config(
			array(
				'code'        => $code,
				'object_type' => $object_type,
			)
		);

		return $fields->get();
	}

	/**
	 * Extract field labels from configuration fields.
	 *
	 * @param array $configuration_fields Configuration fields array.
	 *
	 * @return array Map of field codes to labels (e.g., ['LDCOURSE' => 'Course']).
	 */
	public function extract_field_labels( array $configuration_fields ): array {
		$field_labels = array();

		foreach ( $configuration_fields as $field_group ) {
			if ( is_array( $field_group ) ) {
				foreach ( $field_group as $field ) {
					$field_code = $field['option_code'] ?? '';
					if ( ! empty( $field_code ) && ! empty( $field['label'] ) ) {
						$field_labels[ $field_code ] = $field['label'];
					}
				}
			}
		}

		return $field_labels;
	}

	/**
	 * Get option label for a given field option code and selected value.
	 *
	 * Handles both legacy format (key => label) and modern format ([value, text]).
	 *
	 * @param array  $configuration_fields Configuration fields array.
	 * @param string $option_code          Field option code.
	 * @param mixed  $selected_value       Selected value to find label for.
	 *
	 * @return string Option label or empty string if not found.
	 */
	public function get_option_label( array $configuration_fields, string $option_code, $selected_value ): string {
		if ( ! isset( $configuration_fields[ $option_code ] ) ) {
			return '';
		}

		foreach ( $configuration_fields[ $option_code ] as $field ) {
			if ( ! isset( $field['options'] ) || ! is_array( $field['options'] ) ) {
				continue;
			}

			foreach ( $field['options'] as $key => $option ) {
				// Modern format: ['value' => x, 'text' => 'Label'].
				if ( is_array( $option ) && isset( $option['value'] ) ) {
					if ( (string) $option['value'] === (string) $selected_value ) {
						return $option['text'] ?? '';
					}
				} elseif ( is_string( $option ) || is_numeric( $option ) ) {
					// Legacy format: [id => 'Label'] (e.g., WPForms uses this).
					if ( (string) $key === (string) $selected_value ) {
						return (string) $option;
					}
				}
			}
		}

		return '';
	}

	/**
	 * Enrich configuration with readable labels.
	 *
	 * For each key in the configuration, adds a {key}_readable entry
	 * with the human-readable label from the field options.
	 *
	 * For AJAX-loaded fields where static options aren't available,
	 * this will attempt to call the AJAX handler to resolve the label.
	 *
	 * @param array  $configuration        The configuration values (e.g., ['LDCOURSE' => 656]).
	 * @param array  $configuration_fields The field definitions with options.
	 * @param string $entity_code          Optional. The action/trigger code for AJAX resolution.
	 * @param string $entity_type          Optional. Either 'actions' or 'triggers'.
	 *
	 * @return array Configuration enriched with _readable suffixes.
	 */
	public function enrich_with_readable_labels( array $configuration, array $configuration_fields, string $entity_code = '', string $entity_type = 'actions' ): array {
		$enriched = $configuration;

		foreach ( $configuration as $key => $value ) {
			if ( empty( $key ) || empty( $value ) ) {
				continue;
			}

			// Skip keys that end with _readable or _custom (already processed).
			if ( str_ends_with( $key, '_readable' ) || str_ends_with( $key, '_custom' ) ) {
				continue;
			}

			$readable_key = $key . '_readable';

			// If the caller already provided a _readable value, preserve it.
			// This allows AI agents to pass human-readable labels directly.
			if ( ! empty( $configuration[ $readable_key ] ) ) {
				$enriched[ $readable_key ] = $configuration[ $readable_key ];
				continue;
			}

			// Try static options first.
			$label = $this->get_option_label( $configuration_fields, $key, $value );

			// If no label found and we have entity info, try AJAX resolution.
			if ( empty( $label ) && ! empty( $entity_code ) ) {
				$label = $this->resolve_ajax_option_label( $configuration_fields, $key, $value, $configuration, $entity_code, $entity_type );
			}

			// For text input fields (no options), use the raw value as the readable label.
			// If the value is a token ({{...}}), try to resolve its human-readable name.
			if ( empty( $label ) && ! empty( $value ) ) {
				$label = $this->resolve_token_name( (string) $value, $configuration );
			}

			$enriched[ $readable_key ] = $label;
		}

		return $enriched;
	}

	/**
	 * Resolve option label via AJAX handler for dynamic fields.
	 *
	 * @param array  $configuration_fields The field definitions.
	 * @param string $option_code          Field option code.
	 * @param mixed  $selected_value       Selected value to find label for.
	 * @param array  $configuration        Full configuration for parent field resolution.
	 * @param string $entity_code          The action/trigger code.
	 * @param string $entity_type          Either 'actions' or 'triggers'.
	 *
	 * @return string Option label or empty string if not found.
	 */
	private function resolve_ajax_option_label( array $configuration_fields, string $option_code, $selected_value, array $configuration, string $entity_code, string $entity_type ): string {
		// Find the field definition to get AJAX endpoint.
		$field_def = $this->find_field_definition( $configuration_fields, $option_code );
		if ( empty( $field_def ) ) {
			return '';
		}

		// Get AJAX endpoint (modern or legacy format).
		$endpoint = $field_def['ajax']['endpoint'] ?? $field_def['endpoint'] ?? '';
		if ( empty( $endpoint ) ) {
			return '';
		}

		// Get parent field info for cascading dropdowns.
		$parent_codes = $field_def['ajax']['listen_fields'] ?? array();
		$parent_value = '';
		$parent_code  = '';

		if ( ! empty( $parent_codes ) ) {
			$parent_code  = $parent_codes[0];
			$parent_value = $configuration[ $parent_code ] ?? '';
		}

		// Call the dropdown REST endpoint to get options.
		$options = $this->fetch_dropdown_options( $entity_type, $entity_code, $option_code, $parent_code, $parent_value );

		// Find the matching option.
		foreach ( $options as $option ) {
			if ( isset( $option['value'] ) && (string) $option['value'] === (string) $selected_value ) {
				return $option['text'] ?? '';
			}
		}

		return '';
	}

	/**
	 * Find field definition by option code.
	 *
	 * @param array  $configuration_fields Configuration fields array.
	 * @param string $option_code          Field option code to find.
	 *
	 * @return array|null Field definition or null if not found.
	 */
	private function find_field_definition( array $configuration_fields, string $option_code ): ?array {
		foreach ( $configuration_fields as $field_group ) {
			if ( ! is_array( $field_group ) ) {
				continue;
			}
			foreach ( $field_group as $field ) {
				if ( ( $field['option_code'] ?? '' ) === $option_code ) {
					return $field;
				}
			}
		}
		return null;
	}

	/**
	 * Fetch dropdown options via internal REST request.
	 *
	 * @param string $entity_type   Either 'actions' or 'triggers'.
	 * @param string $entity_code   The action/trigger code.
	 * @param string $option_code   Field option code.
	 * @param string $parent_code   Parent field code (for cascading).
	 * @param string $parent_value  Parent field value (for cascading).
	 *
	 * @return array Array of options with 'value' and 'text' keys.
	 */
	private function fetch_dropdown_options( string $entity_type, string $entity_code, string $option_code, string $parent_code = '', string $parent_value = '' ): array {
		// Use the Dropdown_Controller directly instead of HTTP request.
		$controller = new \Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Standalone\Dropdown_Controller();

		// Build a mock request.
		$request = new \WP_REST_Request( 'GET', '/automator/v1/mcp/dropdown' );
		$request->set_param( 'entity_type', 'actions' === $entity_type ? 'action' : 'trigger' );
		$request->set_param( 'entity_code', $entity_code );
		$request->set_param( 'field_option_code', $option_code );

		if ( ! empty( $parent_code ) && ! empty( $parent_value ) ) {
			$request->set_param( 'field_parent_option_code', $parent_code );
			$request->set_param( 'field_parent_option_value', $parent_value );
		}

		$response = $controller->get_dropdown_options( $request );

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$data = $response->get_data();
		if ( ! isset( $data['success'] ) || ! $data['success'] ) {
			return array();
		}

		return $data['field']['options'] ?? array();
	}

	/**
	 * Ensure HTML format for fields that support fullpage editing (TinyMCE).
	 *
	 * Converts plain text with newlines to HTML paragraphs using wpautop().
	 * This ensures AI-generated content matches the format expected by TinyMCE
	 * fields in the Automator UI.
	 *
	 * @param array $config               The configuration values (e.g., ['EMAILBODY' => "line1\nline2"]).
	 * @param array $configuration_fields The field definitions from get_configuration_fields().
	 *
	 * @return array Configuration with HTML-formatted values for TinyMCE fields.
	 */
	public function ensure_html_format( array $config, array $configuration_fields ): array {
		foreach ( $config as $key => $value ) {
			// Skip non-string values.
			if ( ! is_string( $value ) || empty( $value ) ) {
				continue;
			}

			// Skip _readable suffix keys.
			if ( str_ends_with( $key, '_readable' ) ) {
				continue;
			}

			// Find the field definition.
			$field_def = $this->find_field_definition( $configuration_fields, $key );
			if ( empty( $field_def ) ) {
				continue;
			}

			// Check if field supports fullpage editing (TinyMCE).
			if ( empty( $field_def['supports_fullpage_editing'] ) ) {
				continue;
			}

			// Convert plain text to HTML paragraphs.
			$config[ $key ] = wpautop( $value );
		}

		return $config;
	}

	/**
	 * Resolve token name from a value that may contain a token pattern.
	 *
	 * If the value is a token like {{2152:WPFFORMS:1820|3}}, this method will
	 * look up the token's human-readable name from the recipe context.
	 *
	 * @param string $value         The value that may be a token.
	 * @param array  $configuration The full configuration (may contain recipe_id).
	 *
	 * @return string The token name if found, or the original value.
	 */
	private function resolve_token_name( string $value, array $configuration ): string {
		// Check if value looks like a token: {{...}}.
		if ( ! preg_match( '/^\{\{(.+)\}\}$/', $value, $matches ) ) {
			return $value;
		}

		$token_content = $matches[1];

		// Parse the token format: trigger_id:TRIGGER_CODE:parent_value|field_id.
		// Example: 2152:WPFFORMS:1820|3.
		if ( ! preg_match( '/^(\d+):([A-Z_]+):/', $token_content, $token_parts ) ) {
			// Not a trigger token format, return as-is.
			return $value;
		}

		$trigger_id   = (int) $token_parts[1];
		$trigger_code = $token_parts[2];

		// Get the recipe_id from configuration if available.
		$recipe_id = $configuration['recipe_id'] ?? 0;
		if ( empty( $recipe_id ) ) {
			// Try to get recipe_id from the trigger post.
			$trigger_post = get_post( $trigger_id );
			if ( $trigger_post ) {
				$recipe_id = $trigger_post->post_parent;
			}
		}

		if ( empty( $recipe_id ) ) {
			return $value;
		}

		// Get recipe object to find the token name.
		$recipe_object = \Automator()->get_recipe_object( $recipe_id, ARRAY_A );
		if ( empty( $recipe_object ) || ! isset( $recipe_object['triggers']['items'] ) ) {
			return $value;
		}

		// Find the trigger and its tokens.
		foreach ( $recipe_object['triggers']['items'] as $trigger ) {
			if ( (int) ( $trigger['id'] ?? 0 ) !== $trigger_id ) {
				continue;
			}

			$tokens = $trigger['tokens'] ?? array();
			foreach ( $tokens as $token ) {
				// Match by the full token ID pattern.
				$token_id = $token['id'] ?? '';
				if ( $token_id === $token_content ) {
					return $token['name'] ?? $value;
				}
			}
		}

		// Also check action tokens.
		if ( isset( $recipe_object['actions']['items'] ) ) {
			foreach ( $recipe_object['actions']['items'] as $action ) {
				$tokens = $action['tokens'] ?? array();
				foreach ( $tokens as $token ) {
					$token_id = $token['id'] ?? '';
					if ( $token_id === $token_content ) {
						return $token['name'] ?? $value;
					}
				}
			}
		}

		return $value;
	}
}
