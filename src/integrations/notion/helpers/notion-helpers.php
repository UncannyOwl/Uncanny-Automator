<?php
//phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound
namespace Uncanny_Automator\Integrations\Notion;

use DateTime;
use Exception;
use Uncanny_Automator\Api_Server;
use Uncanny_Automator\Automator_Helpers_Recipe;
use Uncanny_Automator\Integrations\Notion\Fields\Mapper;
use WP_Error;

/**
 * Class Notion_Helpers
 *
 * @package Uncanny_Automator
 */
class Notion_Helpers {

	/**
	 * @var string
	 */
	public $auth_url = '';

	/**
	 * @var string
	 */
	const API_ENDPOINT = 'v2/notion';

	/**
	 * @var string
	 */
	const OPTION_KEY = 'automator_notion_credentials';

	/**
	 * @var string
	 */
	const PERSONS_TRANSIENT_KEY = 'automator_notion_person_options_transient';

	/**
	 * The field separator.
	 *
	 * @var string
	 */
	const FIELD_SEPARATOR = '||__||';

	/**
	 * @var string[]
	 */
	protected static $update_column_fields_not_supported = array(
		'people', // This requires user ID which can't be found by the user unless we have a search field, that returns user IDs.
		'files',
		'formula',
	);

	/**
	 * Sets the auth_url.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->auth_url = AUTOMATOR_API_URL . self::API_ENDPOINT;

		self::$update_column_fields_not_supported = array_merge(
			self::$update_column_fields_not_supported,
			Mapper::get_not_supported_fields()
		);
	}

	/**
	 * The authorization URL.
	 *
	 * @return string
	 */
	public function get_auth_url() {

		$nonce = wp_create_nonce( 'notion_authorization' );

		return add_query_arg(
			array(
				'action'  => 'authorization',
				'wp_site' => rawurlencode( admin_url( 'admin-ajax.php' ) . '?action=notion_authorization&nonce=' . $nonce ),
				'nonce'   => $nonce,
			),
			$this->auth_url
		);
	}

	/**
	 * Does 302 redirect if there are any errors.
	 *
	 * @return void
	 */
	public function authorize_handler() {

		if ( ! wp_verify_nonce( automator_filter_input( 'nonce' ), 'notion_authorization' ) ) {
			wp_die( 'Invalid nonce', 403 );
		}

		$message = Automator_Helpers_Recipe::automator_api_decode_message(
			automator_filter_input( 'automator_api_message' ),
			automator_filter_input( 'nonce' )
		);

		// Redirect with error if there are errors.
		if ( empty( $message ) ) {
			$this->redirect(
				$this->get_settings_page_url(),
				array(
					'error_message' => rawurlencode( 'Failed to decode authorization data.' ),
				)
			);
		}

		automator_add_option( self::OPTION_KEY, $message );

		$this->redirect(
			$this->get_settings_page_url(),
			array(
				'success' => 1,
			)
		);
	}

	/**
	 * Get credentials.
	 *
	 * @return false|mixed[] Returns false if there are no credentials. Otherwise, returns the credentials in array format.
	 */
	public function get_credentials() {
		return automator_get_option( self::OPTION_KEY, false );
	}

	/**
	 * Retrieves the settings page url.
	 *
	 * @return string
	 */
	public function get_settings_page_url() {

		return add_query_arg(
			array(
				'post_type'   => 'uo-recipe',
				'page'        => 'uncanny-automator-config',
				'tab'         => 'premium-integrations',
				'integration' => 'notion',
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Redirects to a safe url.
	 *
	 * @param string $url
	 * @param mixed[] $args
	 *
	 * @return never
	 */
	public function redirect( $url, $args ) {
		wp_safe_redirect( add_query_arg( $args, $url ) );
		die;
	}

	/**
	 * @return false|mixed[]
	 */
	public function get_user() {

		$credentials = $this->get_credentials();

		if ( false === $credentials ) {
			return false;
		}

		return $credentials;
	}

	/**
	 * Retrieves the disconnect URL.
	 *
	 * @return string
	 */
	public function get_disconnect_url() {

		return add_query_arg(
			array(
				'action' => 'notion_disconnect',
				'nonce'  => wp_create_nonce( 'notion_disconnect_nonce' ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * Disconnect handler.
	 *
	 * @return never
	 */
	public function disconnect_handler() {

		automator_delete_option( self::OPTION_KEY );

		$this->redirect(
			$this->get_settings_page_url(),
			array(
				'error_message' => rawurlencode( 'Failed to validate authorization tokens' ),
			)
		);
	}

	/**
	 * Delete handler.
	 *
	 * @return void
	 */
	public function delete_handler() {
		if ( ! wp_verify_nonce( automator_filter_input( 'nonce' ), 'notion_disconnect_nonce' ) ) {
			wp_die( 'Invalid nonce', 403 );
		}
	}

	/**
	 * List pages handler.
	 *
	 * @return void
	 */
	public function automator_notion_list_pages_handler() {

		Automator()->utilities->verify_nonce();

		$options = array();

		$body = array(
			'action' => 'list_pages',
		);

		try {
			$response = $this->api_request( $body );

			$results = (array) $response['data']['results'] ?? array();

			foreach ( $results as $result ) {
				$options[] = array(
					'value' => $result['id'],
					'text'  => $result['properties']['title']['title'][0]['text']['content'],
				);
			}
		} catch ( Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => sprintf( 'API Exception: (%2$s) %1$s', $e->getMessage(), $e->getCode() ),
				)
			);
		}

		wp_send_json(
			array(
				'success' => true,
				'options' => $options,
			)
		);
	}

	/**
	 * List databases handler.
	 *
	 * @return void
	 */
	public function automator_notion_list_databases_handler() {

		Automator()->utilities->verify_nonce();

		$options = array();

		$body = array(
			'action' => 'list_databases',
		);

		try {

			$response = $this->api_request( $body );
			$results  = isset( $response['data']['results'] ) ? (array) $response['data']['results'] : array();

			foreach ( $results as $result ) {
				$options[] = array(
					'value' => $result['id'],
					'text'  => $result['title'][0]['text']['content'] ?? '',
				);
			}
		} catch ( Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => sprintf( 'API Exception: (%2$s) %1$s', $e->getMessage(), $e->getCode() ),
				)
			);
		}

		wp_send_json(
			array(
				'success' => true,
				'options' => $options,
			)
		);
	}


	/**
	 * Get database columns.
	 *
	 * @return void
	 */
	public function automator_notion_get_database_columns_handler() {

		// Sanitization handler.
		Automator()->utilities->verify_nonce();

		$field_values = automator_filter_input_array( 'values', INPUT_POST );
		$group_id     = automator_filter_input( 'group_id', INPUT_POST );

		$db_id = $field_values[ $group_id ] ?? null;

		if ( empty( $db_id ) ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => _x( 'Error: The database ID field is mandatory and cannot be left blank.', 'notion', 'uncanny-automator' ),
				)
			);
		}

		$body = array(
			'action' => 'get_database',
			'db_id'  => $db_id,
		);

		try {

			$response   = $this->api_request( $body );
			$properties = (array) $response['data']['properties'] ?? array();

			foreach ( $properties as $property ) {

				// Disable options with unsupported type.
				if ( in_array( $property['type'], self::$update_column_fields_not_supported, true ) ) {
					continue;
				}

				if ( ! empty( $property['id'] ) && ! empty( $property['type'] ) && ! empty( $property['name'] ) ) {

					$field_string = self::get_field_string();

					$option_value = strtr(
						$field_string,
						array(
							'{{NAME}}' => $property['name'],
							'{{ID}}'   => $property['id'],
							'{{TYPE}}' => $property['type'],
						)
					);

					$options[] = array(
						'text'  => $property['name'],
						'value' => $option_value,
					);

				}
			}

			// .
		} catch ( Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => $e->getMessage(),
				)
			);
		}

		wp_send_json(
			array(
				'success' => true,
				'options' => $options,
			)
		);
	}

	/**
	 * Retrieves the database columns and properties and sends back new fields to UI.
	 *
	 * @return mixed
	 */
	public function automator_notion_get_database_handler() {

		// Sanitization handler.
		Automator()->utilities->verify_nonce();

		$field_values = automator_filter_input_array( 'values', INPUT_POST );
		$group_id     = automator_filter_input( 'group_id', INPUT_POST );

		$db_id = $field_values[ $group_id ] ?? null;

		if ( empty( $db_id ) ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => _x( 'Error: The database field is mandatory and cannot be left blank.', 'notion', 'uncanny-automator' ),
				)
			);
		}

		$body = array(
			'action' => 'get_database',
			'db_id'  => $db_id,
		);

		try {

			$response   = $this->api_request( $body );
			$properties = (array) $response['data']['properties'] ?? array();

			foreach ( $properties as $property ) {

				$fields_mapper = new Fields\Mapper();
				$fields_mapper->set_properties( $property );

				$field = $fields_mapper->get_corresponding_field();

				if ( ! empty( $field ) ) {
					$fields[] = $field;
				}
			}
		} catch ( Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => sprintf( 'API Exception: (%2$s) %1$s', $e->getMessage(), $e->getCode() ),
				)
			);
		}

		wp_send_json(
			array(
				'success'          => true,
				'field_properties' => array(
					'fields' => $fields,
				),
			)
		);
	}

	public function automator_notion_list_users() {

		$persons_cached = get_transient( self::PERSONS_TRANSIENT_KEY );

		if ( false !== $persons_cached ) {
			wp_send_json(
				array(
					'success' => true,
					'options' => $persons_cached,
				)
			);
		}

		$body = array(
			'action' => 'list_users',
		);

		$options = array();

		try {

			$response = $this->api_request( $body );
			$results  = $response['data']['results'] ?? array();

			foreach ( $results as $person ) {
				if ( 'person' === $person['type'] ) {
					$options[] = array(
						'text'  => sprintf( '%1$s (%2$s)', $person['name'], $person['person']['email'] ),
						'value' => $person['id'],
					);
				}
			}
		} catch ( Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => $e->getMessage(),
				)
			);
		}

		if ( is_array( $options ) && ! empty( $options ) ) {
			set_transient( self::PERSONS_TRANSIENT_KEY, $options, MINUTE_IN_SECONDS * 5 );
		}

		wp_send_json(
			array(
				'success' => true,
				'options' => $options,
			)
		);
	}

	/**
	 * api_request
	 *
	 * @param  mixed $body
	 * @param  mixed $action_data
	 * @return array
	 */
	public function api_request( $body, $action_data = null ) {

		$credentials = $this->get_credentials();

		$body['access_token'] = $credentials['access_token'] ?? '';

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => $action_data,
		);

		$response = Api_Server::api_call( $params );

		if ( 200 !== $response['statusCode'] ) {
			throw new Exception( esc_html( $response['data']['message'] ), absint( $response['statusCode'] ) );
		}

		return $response;
	}

	/**
	 * Create a payload from field data.
	 *
	 * @param  string $column_value JSON encoded string of column values.
	 * @param  string $field_column_value The key value pairs of the reapeter.
	 *
	 * @return string JSON encoded string of fields ID and values or false on failure.
	 *
	 * @throws Exception If the JSON is invalid.
	 */
	public function make_fields_payload( $field_column_value = array() ) {

		$column_value_decoded = json_decode( $field_column_value, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new Exception(
				sprintf(
				/* translators: %s: JSON error message */
					esc_html__( 'Invalid JSON detected: %s', 'uncanny-automator' ),
					esc_html( json_last_error_msg() )
				),
				400
			);
		}

		$fields_id_value = array();

		foreach ( $column_value_decoded[0] as $key => $decoded ) {

			// Skip _readable fields.
			if ( strpos( $key, '_readable' ) !== false ) {
				continue;
			}

			try {

				$split = self::extract_field_parameters( $key );

				list ( $notion, $field, $field_id, $type ) = $split;

				$fields_id_value[ $field_id ] = array(
					'type'  => $type,
					'value' => $decoded,
					'id'    => $field_id,
				);

			} catch ( Exception $e ) {
				automator_log( 'Notion: ' . $e->getMessage(), 'Field errors' );
			}
		}

		if ( empty( $fields_id_value ) ) {
			throw new Exception( 'Error: Fields are empty.', 400 );
		}

		return wp_json_encode( $fields_id_value );
	}

	/**
	 * Converts the given string date into ISO 8601.
	 *
	 * @param string $date_string
	 * @return false|string
	 */
	public function convert_to_iso_8601( $date_string ) {

		// Create a DateTime object from the given date string
		$date = new DateTime( $date_string );
		// Return the date in ISO 8601 format
		return $date->format( DateTime::ATOM ); // ATOM is an ISO 8601 compatible format
	}


	/**
	* Checks if a given string represents a valid date.
	*
	* This function attempts to parse the date string using the 'Y-m-d' format
	* ( YYYY - MM - DD) and performs additional checks to ensure the parsed date
	* is truly valid .
	*
	* @param string $date_string The date string to validate.
	* @return bool True if the string is a valid date, false otherwise.
	*/
	public function is_valid_date( $date_string ) {
		// Attempt to parse the date string using DateTime.
		$format = get_option( 'date_format' );
		$d      = DateTime::createFromFormat( $format, $date_string );

		// Check if the date is valid.
		// 1. The object should be created successfully.
		// 2. The formatted date should match the input date (this handles invalid dates like 2023-02-30).
		return $d && $d->format( $format ) === $date_string;
	}

	/**
	 * Retrieve the static string to be replaced by properties real values.
	 *
	 * @return string
	 */
	public static function get_field_string() {

		$string = 'NOTION'
			. self::FIELD_SEPARATOR
			. 'FIELD'
			. self::FIELD_SEPARATOR
			. '{{NAME}}'
			. self::FIELD_SEPARATOR
			. '{{ID}}'
			. self::FIELD_SEPARATOR
			. '{{TYPE}}';

		return $string;
	}

	/**
	 * Retrieve the static string to be replaced by properties real values.
	 *
	 * Used for option code.
	 *
	 * @return string
	 */
	public static function get_option_code_field_string() {

		$string = 'NOTION'
			. self::FIELD_SEPARATOR
			. 'FIELD'
			. self::FIELD_SEPARATOR
			. '{{ID}}'
			. self::FIELD_SEPARATOR
			. '{{TYPE}}';

		return $string;
	}

	/**
	* Split a string by double underscores, handling extra underscores.
	*
	* @param string $input The input string to be split.
	* @return array The resulting array after splitting.

	* @throws InvalidArgumentException If the input does not result in exactly four parts.
	*/
	public static function extract_field_parameters( $input ) {

		// Split the string using double underscores as the primary delimiter.
		$parts = explode( self::FIELD_SEPARATOR, $input );

		// Validate that the resulting array has exactly four parts.
		if ( count( $parts ) !== 4 ) {
			throw new \InvalidArgumentException( 'Input string must result in exactly four parts when split by the field separator.' );
		}

		// Remove leading underscores. The $parts[3] is equals to field_type.
		$parts[3] = ltrim( $parts[3], '_' );

		return $parts;
	}

	/**
	* Split a string by double underscores, handling extra underscores.
	*
	* @param string $input The input string to be split.
	* @return array The resulting array after splitting.

	* @throws InvalidArgumentException If the input does not result in exactly four parts.
	*/
	public static function extract_field_parameters_columns( $input ) {

		// Split the string using double underscores as the primary delimiter.
		$parts = explode( self::FIELD_SEPARATOR, $input );

		// Validate that the resulting array has exactly four parts.
		if ( count( $parts ) !== 5 ) {
			throw new \InvalidArgumentException( 'Input string must result in exactly 5 parts when split by the field separator.' );
		}

		// Remove leading underscores. The $parts[3] is equals to field_type.
		$parts[3] = ltrim( $parts[4], '_' );

		return $parts;
	}
}
