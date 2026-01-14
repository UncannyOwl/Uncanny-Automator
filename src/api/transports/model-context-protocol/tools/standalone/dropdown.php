<?php
/**
 * Standalone Dropdown Options Endpoint.
 *
 * Returns dropdown options as simple JSON (not MCP wrapped).
 * Used by external agents to fetch field options via AJAX handlers.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Standalone;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\OAuth\Token_Manager;
use Uncanny_Automator\Services\Integrations\Fields;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Dropdown REST Controller.
 *
 * @since 7.0.0
 */
class Dropdown_Controller extends WP_REST_Controller {

	/**
	 * @var string
	 */
	protected $namespace = 'automator/v1';

	/**
	 * @var string
	 */
	protected $rest_base = 'mcp/dropdown';

	/**
	 * @var Token_Manager
	 */
	private $token_manager;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->token_manager = new Token_Manager();
	}

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_dropdown_options' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => $this->get_endpoint_args(),
				),
			)
		);
	}

	/**
	 * Get dropdown options.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function get_dropdown_options( $request ) {

		$entity_type       = $request->get_param( 'entity_type' );
		$entity_code       = $request->get_param( 'entity_code' );
		$field_option_code = $request->get_param( 'field_option_code' );

		if ( empty( $entity_code ) || empty( $field_option_code ) ) {
			return new WP_Error(
				'missing_params',
				'entity_code and field_option_code are required',
				array( 'status' => 400 )
			);
		}

		if ( empty( $entity_type ) || ! in_array( $entity_type, array( 'action', 'trigger' ), true ) ) {
			return new WP_Error(
				'invalid_entity_type',
				'entity_type must be either "action" or "trigger"',
				array( 'status' => 400 )
			);
		}

		try {
			// Get fields for this entity.
			$fields = new Fields();
			$fields->set_config(
				array(
					'object_type' => $entity_type . 's', // e.g., actions, triggers, but only accept without 's' suffix.
					'code'        => $entity_code,
				)
			);

			$entity_fields = $fields->get();
		} catch ( \Exception $e ) {
			return new WP_Error(
				'fields_error',
				'One or more parameters are invalid. ' . $e->getMessage(),
				array( 'status' => 400 )
			);
		}

		// Extract field info (static options or AJAX endpoint).
		$field_info = $this->extract_field_info( $entity_fields, $field_option_code );

		if ( null === $field_info ) {
			return new WP_Error(
				'field_not_found',
				sprintf( 'Field with option code %s not found for %s %s.', $field_option_code, $entity_type, $entity_code ),
				array( 'status' => 404 )
			);
		}

		// Check for unsupported field types.
		if ( ! empty( $field_info['unsupported'] ) ) {
			$field_label = $field_info['label'] ?? $field_option_code;

			return new WP_REST_Response(
				array(
					'success'    => false,
					'error_code' => 'unsupported_field_type',
					'field'      => array(
						'code'  => $field_option_code,
						'label' => $field_label,
						'type'  => $field_info['unsupported_reason'] ?? 'unknown',
					),
					'message'    => sprintf(
						'The "%s" field is a complex field type that cannot be populated automatically. Leave this field empty and inform the user they can configure it manually later.',
						$field_label
					),
					'action'     => 'skip_and_notify_user',
				),
				200
			);
		}

		// Build parent field data if this field depends on parent(s).
		$parent_field_data = false;
		if ( ! empty( $field_info['parent_codes'] ) ) {
			// For now, just use the first parent (most common case).
			$parent_code       = $field_info['parent_codes'][0];
			$parent_label      = $this->get_field_label( $entity_fields, $parent_code );
			$parent_field_data = array(
				'code'  => $parent_code,
				'label' => $parent_label ?? $parent_code,
			);
		}

		// Build child field data if this field populates a child.
		$has_child_field  = false;
		$child_field_data = false;
		if ( ! empty( $field_info['populates'] ) ) {
			$has_child_field  = true;
			$child_code       = $field_info['populates'];
			$child_label      = $this->get_field_label( $entity_fields, $child_code );
			$child_field_data = array(
				'code'  => $child_code,
				'label' => $child_label ?? $child_code,
			);
		}

		// Build metadata with relationship info.
		$metadata = array(
			'is_parent_field' => $field_info['is_parent'],
			'relationship'    => array(
				'has_parent_field' => $parent_field_data,
				'has_child_field'  => $has_child_field,
				'child_field'      => $child_field_data,
			),
		);

		// Build field info.
		$field_data = array(
			'code'  => $field_option_code,
			'label' => $field_info['label'],
		);

		// If field has static options, return them directly.
		if ( ! empty( $field_info['options'] ) ) {
			$field_data['options'] = $this->normalize_options( $field_info['options'] );

			return new WP_REST_Response(
				array(
					'success'  => true,
					'field'    => $field_data,
					'metadata' => $metadata,
				),
				200
			);
		}

		// If field has an AJAX endpoint, call it.
		if ( ! empty( $field_info['endpoint'] ) ) {
			$field_parent_option_code  = $request->get_param( 'field_parent_option_code' );
			$field_parent_option_value = $request->get_param( 'field_parent_option_value' );

			// Parse multi-parent values JSON if provided.
			$all_parent_values  = array();
			$parent_values_json = $request->get_param( 'parent_values' );
			if ( ! empty( $parent_values_json ) ) {
				$decoded = json_decode( $parent_values_json, true );
				if ( is_array( $decoded ) ) {
					$all_parent_values = $decoded;
				}
			}

			$options = $this->call_ajax_handler( $field_info['endpoint'], $field_parent_option_code, $field_parent_option_value, $all_parent_values );

			if ( is_wp_error( $options ) ) {
				return $options;
			}

			// Handle repeater rows (e.g., Google Sheets columns) - return as-is without normalization.
			if ( is_array( $options ) && ! empty( $options['__repeater_rows'] ) ) {
				$field_data['repeater_rows'] = $options['rows'];

				return new WP_REST_Response(
					array(
						'success'  => true,
						'field'    => $field_data,
						'metadata' => $metadata,
						'type'     => 'repeater_rows',
						'message'  => 'This field returns pre-populated repeater rows. Each row contains column names that should be filled with values.',
					),
					200
				);
			}

			$field_data['options'] = $this->normalize_options( $options );

			return new WP_REST_Response(
				array(
					'success'  => true,
					'field'    => $field_data,
					'metadata' => $metadata,
				),
				200
			);
		}

		// For legacy cascading: child field has no endpoint, but parent field does.
		// Check if a parent field populates this field.
		$field_parent_option_code  = $request->get_param( 'field_parent_option_code' );
		$field_parent_option_value = $request->get_param( 'field_parent_option_value' );

		if ( ! empty( $field_parent_option_code ) && ! empty( $field_parent_option_value ) ) {
			// Look up the parent field to get its endpoint.
			$parent_field_info = $this->extract_field_info( $entity_fields, $field_parent_option_code );

			if ( ! empty( $parent_field_info['endpoint'] ) && ! empty( $parent_field_info['populates'] ) ) {
				// Verify the parent populates this field.
				if ( strtoupper( $parent_field_info['populates'] ) === strtoupper( $field_option_code ) ) {
					$options = $this->call_ajax_handler( $parent_field_info['endpoint'], $field_parent_option_code, $field_parent_option_value );

					if ( is_wp_error( $options ) ) {
						return $options;
					}

					// Update metadata to reflect this is a child field.
					$parent_label                                 = $this->get_field_label( $entity_fields, $field_parent_option_code );
					$metadata['is_parent_field']                  = false;
					$metadata['relationship']['has_parent_field'] = array(
						'code'  => $field_parent_option_code,
						'label' => $parent_label ?? $field_parent_option_code,
					);

					$field_data['options'] = $this->normalize_options( $options );

					return new WP_REST_Response(
						array(
							'success'  => true,
							'field'    => $field_data,
							'metadata' => $metadata,
						),
						200
					);
				}
			}
		}

		// Field exists but has no options and no AJAX endpoint.
		return new WP_Error(
			'no_options_available',
			sprintf( 'Field %s exists but has no options or AJAX endpoint configured.', $field_option_code ),
			array( 'status' => 404 )
		);
	}

	/**
	 * Call AJAX handler via HTTP request.
	 *
	 * Makes an actual HTTP POST request to the WordPress AJAX endpoint.
	 * This is necessary because filter_input_array(INPUT_POST) reads from
	 * php://input which can't be faked programmatically.
	 *
	 * @param string      $ajax_endpoint    The AJAX action name.
	 * @param string|null $parent_code      Parent field option code for cascading fields.
	 * @param string|null $parent_value     Parent value for cascading fields.
	 * @param array       $all_parent_values All parent field values for multi-level cascading.
	 * @return array|WP_Error Options array or error.
	 */
	private function call_ajax_handler( string $ajax_endpoint, ?string $parent_code = null, ?string $parent_value = null, array $all_parent_values = array() ) {
		// Get an admin user for the request.
		$admin_user = $this->get_admin_user();
		if ( ! $admin_user ) {
			return new WP_Error(
				'no_admin_user',
				'No administrator user found to execute AJAX request.',
				array( 'status' => 500 )
			);
		}

		// Generate cookies and nonce for admin authentication.
		// Nonce must be generated after setting current user for it to be valid.
		$auth = $this->generate_auth_cookies_and_nonce( $admin_user->ID );

		// Build POST body.
		$body = array(
			'action' => $ajax_endpoint,
			'nonce'  => $auth['nonce'],
		);

		// Build the values array from all parent values.
		$values_array = array();

		// Add all parent values from the multi-parent parameter.
		if ( ! empty( $all_parent_values ) ) {
			$values_array = $all_parent_values;
		}

		// Add single parent value (may override or add to the array).
		if ( null !== $parent_value && '' !== $parent_value ) {
			$body['value'] = $parent_value;

			if ( null !== $parent_code && '' !== $parent_code ) {
				$values_array[ $parent_code ] = $parent_value;
			} else {
				$values_array['value'] = $parent_value;
			}
		}

		// Set the combined values array.
		if ( ! empty( $values_array ) ) {
			$body['values'] = $values_array;
		}

		// Make HTTP request to admin-ajax.php.
		$ajax_url = admin_url( 'admin-ajax.php' );
		$response = wp_remote_post(
			$ajax_url,
			array(
				'timeout'   => 30,
				'cookies'   => $auth['cookies'],
				'body'      => $body,
				'sslverify' => false, // Disable SSL verification for same-server requests.
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'ajax_request_failed',
				'AJAX request failed: ' . $response->get_error_message(),
				array( 'status' => 500 )
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			return new WP_Error(
				'ajax_http_error',
				sprintf( 'AJAX request returned HTTP %d', $response_code ),
				array( 'status' => 500 )
			);
		}

		// Parse JSON response.
		$data = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error(
				'ajax_parse_error',
				'Failed to parse AJAX response: ' . json_last_error_msg(),
				array( 'status' => 500 )
			);
		}

		// Check for error responses from AJAX handlers.
		// Many handlers return {success: false, error: "message"} when missing required values.
		if ( isset( $data['success'] ) && false === $data['success'] ) {
			$error_message = $data['error'] ?? 'Unknown error from AJAX handler';
			return new WP_Error(
				'ajax_handler_error',
				$error_message,
				array( 'status' => 400 )
			);
		}

		// Check for field_properties response (repeater fields via AJAX).
		if ( isset( $data['field_properties'] ) ) {
			return new WP_Error(
				'unsupported_ajax_response',
				'This field returns a complex data structure that cannot be handled as dropdown options.',
				array( 'status' => 400 )
			);
		}

		// Return options from response.
		if ( isset( $data['options'] ) ) {
			return $data['options'];
		}

		// Handle Google Sheets column format: { success: true, rows: [{GS_COLUMN_NAME: "col1", GS_COLUMN_VALUE: ""}, ...] }
		// This is a repeater field that returns pre-populated rows, not dropdown options.
		// Return with a special flag so the caller knows not to normalize.
		if ( isset( $data['rows'] ) && is_array( $data['rows'] ) ) {
			return array(
				'__repeater_rows' => true,
				'rows'            => $data['rows'],
			);
		}

		// Some handlers return the full response as options array.
		// But only if it looks like an options array (numeric keys or value/text structure).
		if ( is_array( $data ) && ! isset( $data['success'] ) && ! isset( $data['error'] ) ) {
			return $data;
		}

		return new WP_Error(
			'unexpected_ajax_response',
			'AJAX handler returned an unexpected response format.',
			array( 'status' => 500 )
		);
	}

	/**
	 * Extract field info from entity fields.
	 *
	 * Returns static options if available, otherwise the AJAX endpoint.
	 * Handles both new framework (ajax.endpoint) and legacy framework (endpoint).
	 * Also searches inside repeater fields for sub-fields with AJAX dependencies.
	 *
	 * @param array  $entity_fields Fields grouped by option code.
	 * @param string $option_code   Field option code to find.
	 * @return array|null Field info with 'options', 'endpoint', 'is_parent', 'parent', 'populates', or null if not found.
	 */
	private function extract_field_info( array $entity_fields, string $option_code ): ?array {
		foreach ( $entity_fields as $fields ) {
			foreach ( $fields as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}

				$field_code = $field['option_code'] ?? '';

				// Check for repeater fields - might contain the target sub-field.
				$input_type = $field['input_type'] ?? 'select';
				if ( 'repeater' === $input_type ) {
					// If asking for the repeater itself, mark as unsupported.
					if ( strtoupper( $field_code ) === strtoupper( $option_code ) ) {
						return array(
							'unsupported'        => true,
							'unsupported_reason' => 'repeater_field',
							'label'              => $field['label'] ?? $field_code,
						);
					}

					// Check if the target option_code is a sub-field inside this repeater.
					$sub_field_info = $this->extract_repeater_subfield_info( $field, $option_code );
					if ( null !== $sub_field_info ) {
						return $sub_field_info;
					}

					continue;
				}

				if ( strtoupper( $field_code ) !== strtoupper( $option_code ) ) {
					continue;
				}

				$info = array(
					'label'        => $field['label'] ?? $field_code,
					'options'      => $field['options'] ?? array(),
					'endpoint'     => null,
					'is_parent'    => true,
					'parent_codes' => array(), // Raw codes for lookup.
					'populates'    => null,
				);

				// New framework: ajax.endpoint with listen_fields.
				if ( ! empty( $field['ajax']['endpoint'] ) ) {
					$info['endpoint'] = $field['ajax']['endpoint'];

					// Check if it loads on page load (parent) or depends on other fields (child).
					$event = $field['ajax']['event'] ?? '';
					if ( 'on_load' === $event ) {
						$info['is_parent'] = true;
					} elseif ( 'parent_fields_change' === $event && ! empty( $field['ajax']['listen_fields'] ) ) {
						$info['is_parent']    = false;
						$info['parent_codes'] = $field['ajax']['listen_fields'];
					}
				}

				// Legacy framework: endpoint with fill_values_in or target_field.
				if ( ! empty( $field['endpoint'] ) ) {
					$info['endpoint'] = $field['endpoint'];

					// Legacy fields with fill_values_in/target_field are parent fields that populate children.
					$target_field = $field['fill_values_in'] ?? $field['target_field'] ?? null;
					if ( ! empty( $target_field ) ) {
						// This is a parent field - loads its own options and populates a child field.
						$info['is_parent'] = true;
						$info['populates'] = $target_field;
					} elseif ( empty( $info['options'] ) ) {
						// This is a child field - depends on parent.
						$info['is_parent'] = false;
					}
				}

				// Static options are always parent fields (self-sufficient).
				if ( ! empty( $info['options'] ) && empty( $info['endpoint'] ) ) {
					$info['is_parent'] = true;
				}

				return $info;
			}
		}

		return null;
	}

	/**
	 * Extract sub-field info from a repeater field.
	 *
	 * Handles repeater fields where sub-fields have AJAX dependencies.
	 * For example, Google Sheets "Column" sub-field depends on Worksheet selection.
	 *
	 * @param array  $repeater_field The repeater field configuration.
	 * @param string $option_code    The sub-field option code to find.
	 * @return array|null Field info or null if not found.
	 */
	private function extract_repeater_subfield_info( array $repeater_field, string $option_code ): ?array {
		$sub_fields = $repeater_field['fields'] ?? array();
		if ( empty( $sub_fields ) ) {
			return null;
		}

		// Check if the repeater has AJAX config with mapping_column.
		$ajax_config    = $repeater_field['ajax'] ?? array();
		$mapping_column = $ajax_config['mapping_column'] ?? '';
		$listen_fields  = $ajax_config['listen_fields'] ?? array();
		$endpoint       = $ajax_config['endpoint'] ?? '';

		foreach ( $sub_fields as $sub_field ) {
			if ( ! is_array( $sub_field ) ) {
				continue;
			}

			$sub_code = $sub_field['option_code'] ?? '';
			if ( strtoupper( $sub_code ) !== strtoupper( $option_code ) ) {
				continue;
			}

			// Found the sub-field. Check if it's the mapping column for AJAX.
			$info = array(
				'label'        => $sub_field['label'] ?? $sub_code,
				'options'      => $sub_field['options'] ?? array(),
				'endpoint'     => null,
				'is_parent'    => false,
				'parent_codes' => array(),
				'populates'    => null,
				'is_repeater_subfield' => true,
			);

			// If this sub-field is the mapping column, use the repeater's AJAX endpoint.
			if ( $sub_code === $mapping_column && ! empty( $endpoint ) ) {
				$info['endpoint']     = $endpoint;
				$info['parent_codes'] = $listen_fields;
			}

			return $info;
		}

		return null;
	}

	/**
	 * Get field label by option code.
	 *
	 * @param array  $entity_fields All entity fields.
	 * @param string $option_code   Field option code.
	 * @return string|null Field label or null if not found.
	 */
	private function get_field_label( array $entity_fields, string $option_code ): ?string {
		foreach ( $entity_fields as $fields ) {
			foreach ( $fields as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}

				$field_code = $field['option_code'] ?? '';
				if ( strtoupper( $field_code ) === strtoupper( $option_code ) ) {
					return $field['label'] ?? null;
				}
			}
		}

		return null;
	}

	/**
	 * Normalize options to consistent format.
	 *
	 * Handles multiple formats:
	 * - Key => value pairs: [1820 => "Blank Form"]
	 * - Array of objects: [['value' => 1820, 'text' => "Blank Form"]]
	 *
	 * @param array $options Raw options array.
	 * @return array Normalized options with 'value' and 'text' keys.
	 */
	private function normalize_options( array $options ): array {
		$normalized = array();

		foreach ( $options as $key => $item ) {
			// Already in {value, text} format.
			if ( is_array( $item ) && isset( $item['value'] ) && isset( $item['text'] ) ) {
				$normalized[] = array(
					'value' => (string) $item['value'],
					'text'  => (string) $item['text'],
				);
				continue;
			}

			// Key => value pair format.
			$normalized[] = array(
				'value' => (string) $key,
				'text'  => (string) $item,
			);
		}

		return $normalized;
	}

	/**
	 * Generate authentication cookies and nonce for a user.
	 *
	 * Creates WordPress auth cookies that can be passed to wp_remote_post,
	 * and generates a nonce that will be valid for those cookies.
	 *
	 * @param int $user_id The user ID.
	 * @return array{cookies: array, nonce: string} Array with cookies and nonce.
	 */
	private function generate_auth_cookies_and_nonce( int $user_id ): array {
		// Set the current user.
		wp_set_current_user( $user_id );

		// Create a session token - this is stored in the database.
		$expiration = time() + 60; // 1 minute expiration is enough.
		$manager    = \WP_Session_Tokens::get_instance( $user_id );
		$token      = $manager->create( $expiration );

		// Generate auth cookies WITH the same token.
		$auth_cookie      = wp_generate_auth_cookie( $user_id, $expiration, 'auth', $token );
		$logged_in_cookie = wp_generate_auth_cookie( $user_id, $expiration, 'logged_in', $token );

		// Fake the logged_in cookie in $_COOKIE so wp_get_session_token() works.
		// This is needed for wp_create_nonce() to use the correct token.
		$_COOKIE[ LOGGED_IN_COOKIE ] = $logged_in_cookie;

		// Generate nonce - now it will use the correct session token.
		$nonce = wp_create_nonce( 'wp_rest' );

		$cookies = array();

		// Auth cookie.
		$cookies[] = new \WP_Http_Cookie(
			array(
				'name'  => AUTH_COOKIE,
				'value' => $auth_cookie,
			)
		);

		// Secure auth cookie (for HTTPS).
		$cookies[] = new \WP_Http_Cookie(
			array(
				'name'  => SECURE_AUTH_COOKIE,
				'value' => $auth_cookie,
			)
		);

		// Logged in cookie.
		$cookies[] = new \WP_Http_Cookie(
			array(
				'name'  => LOGGED_IN_COOKIE,
				'value' => $logged_in_cookie,
			)
		);

		return array(
			'cookies' => $cookies,
			'nonce'   => $nonce,
		);
	}

	/**
	 * Get an admin user for capability checks.
	 *
	 * @return \WP_User|null Admin user or null if not found.
	 */
	private function get_admin_user(): ?\WP_User {
		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
			)
		);

		return ! empty( $admins ) ? $admins[0] : null;
	}

	/**
	 * Check permissions using Bearer token authentication.
	 *
	 * Validates the OAuth Bearer token from the Authorization header
	 * and sets the current WordPress user for the request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if authenticated, WP_Error otherwise.
	 */
	public function check_permissions( $request ) {
		// Try Bearer token authentication.
		$auth_header = $request->get_header( 'authorization' );

		// Fallback header for Apache setups that strip Authorization.
		$creds = $request->get_header( 'x-automator-creds' );

		// Use fallback if Authorization header doesn't contain Bearer.
		if ( false === strpos( strtolower( (string) $auth_header ), 'bearer' ) ) {
			$auth_header = $creds;
		}

		if ( $auth_header && preg_match( '/^Bearer\s+(.+)$/i', $auth_header, $matches ) ) {
			$token = $matches[1];
			$user  = $this->token_manager->get_user_from_token( $token );

			if ( $user && user_can( $user, 'manage_options' ) ) {
				// Set current user for the request.
				wp_set_current_user( $user->ID );
				return true;
			}

			return new WP_Error(
				'rest_forbidden',
				'Invalid or expired Bearer token',
				array( 'status' => 401 )
			);
		}

		// No valid Bearer token provided.
		return new WP_Error(
			'rest_forbidden',
			'Authentication required. Provide a valid Bearer token in the Authorization header.',
			array( 'status' => 401 )
		);
	}

	/**
	 * Get endpoint arguments.
	 *
	 * @return array
	 */
	private function get_endpoint_args() {
		return array(
			'entity_type'       => array(
				'required'          => true,
				'type'              => 'string',
				'description'       => 'The entity type: "action" or "trigger".',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'entity_code'       => array(
				'required'          => true,
				'type'              => 'string',
				'description'       => 'The action/trigger code (e.g., SLACKSENDMESSAGE).',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'field_option_code' => array(
				'required'          => true,
				'type'              => 'string',
				'description'       => 'The field option code (e.g., SLACKCHANNEL).',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'field_parent_option_code' => array(
				'required'          => false,
				'type'              => 'string',
				'description'       => 'Parent field option code for cascading dropdowns (e.g., ASANA_WORKSPACE).',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'field_parent_option_value' => array(
				'required'          => false,
				'type'              => 'string',
				'description'       => 'Parent option value for cascading dropdowns.',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'parent_values'             => array(
				'required'          => false,
				'type'              => 'string',
				'description'       => 'JSON object of multiple parent field values for multi-level cascading (e.g., {"GSSPREADSHEET":"abc123","GSWORKSHEET":"xyz789"}).',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}
}
