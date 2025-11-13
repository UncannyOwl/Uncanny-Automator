<?php
//phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound
namespace Uncanny_Automator\Integrations\Notion;

use Uncanny_Automator\App_Integrations\App_Helpers;
use DateTime;
use Exception;
use Uncanny_Automator\Integrations\Notion\Fields\Mapper;

/**
 * Class Notion_App_Helpers
 *
 * @package Uncanny_Automator
 *
 * @property Notion_API_Caller $api
 */
class Notion_App_Helpers extends App_Helpers {

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

	////////////////////////////////////////////////////////////
	// Abstract methods.
	////////////////////////////////////////////////////////////

	/**
	 * Set class properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		self::$update_column_fields_not_supported = array_merge(
			self::$update_column_fields_not_supported,
			Mapper::get_not_supported_fields()
		);
	}

	/**
	 * Get account info.
	 * - Override this method to return account info from credentials.
	 *
	 * @return array
	 */
	public function get_account_info() {
		$account = $this->get_credentials();
		return ! empty( $account ) && is_array( $account )
			? $account
			: array();
	}

	/**
	 * List pages handler.
	 *
	 * @return void
	 */
	public function automator_notion_list_pages_handler() {

		// Verify request.
		Automator()->utilities->verify_nonce();

		try {
			$options = $this->api->list_pages();
			wp_send_json(
				array(
					'success' => true,
					'options' => $options,
				)
			);
		} catch ( Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => sprintf( 'API Exception: (%2$s) %1$s', $e->getMessage(), $e->getCode() ),
				)
			);
		}
	}

	/**
	 * List databases handler.
	 *
	 * @return void
	 */
	public function automator_notion_list_databases_handler() {

		// Verify request.
		Automator()->utilities->verify_nonce();

		try {
			$options = $this->api->list_databases();
			wp_send_json(
				array(
					'success' => true,
					'options' => $options,
				)
			);
		} catch ( Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => sprintf( 'API Exception: (%2$s) %1$s', $e->getMessage(), $e->getCode() ),
				)
			);
		}
	}

	/**
	 * Get database columns.
	 *
	 * @return void
	 */
	public function automator_notion_get_database_columns_handler() {

		// Verify request.
		Automator()->utilities->verify_nonce();

		$field_values = automator_filter_input_array( 'values', INPUT_POST );
		$group_id     = automator_filter_input( 'group_id', INPUT_POST );
		$db_id        = $field_values[ $group_id ] ?? null;

		try {
			$options = $this->api->get_database_columns( $db_id );
			wp_send_json(
				array(
					'success' => true,
					'options' => $options,
				)
			);
		} catch ( Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Retrieves the database columns and properties and sends back new fields to UI.
	 *
	 * @return mixed
	 */
	public function automator_notion_get_database_handler() {

		// Verify request.
		Automator()->utilities->verify_nonce();

		$field_values = automator_filter_input_array( 'values', INPUT_POST );
		$group_id     = automator_filter_input( 'group_id', INPUT_POST );
		$db_id        = $field_values[ $group_id ] ?? null;

		try {
			$fields = $this->api->get_database_fields( $db_id );
			wp_send_json(
				array(
					'success'          => true,
					'field_properties' => array(
						'fields' => $fields,
					),
				)
			);
		} catch ( Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => sprintf( 'API Exception: (%2$s) %1$s', $e->getMessage(), $e->getCode() ),
				)
			);
		}
	}

	/**
	 * List users handler.
	 *
	 * @return void
	 */
	public function automator_notion_list_users() {

		// Verify request.
		Automator()->utilities->verify_nonce();

		$persons_cached = get_transient( self::PERSONS_TRANSIENT_KEY );

		if ( false !== $persons_cached ) {
			wp_send_json(
				array(
					'success' => true,
					'options' => $persons_cached,
				)
			);
		}

		try {
			$options = $this->api->list_users();

			if ( is_array( $options ) && ! empty( $options ) ) {
				set_transient( self::PERSONS_TRANSIENT_KEY, $options, MINUTE_IN_SECONDS * 5 );
			}

			wp_send_json(
				array(
					'success' => true,
					'options' => $options,
				)
			);
		} catch ( Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Create a payload from field data.
	 *
	 * @param int    $user_id The user ID.
	 * @param int    $recipe_id The recipe ID.
	 * @param array  $args The args.
	 * @param string $field_column_value The key value pairs of the repeater.
	 *
	 * @return string JSON encoded string of fields ID and values or false on failure.
	 * @throws Exception If the JSON is invalid.
	 */
	public function make_fields_payload( $user_id, $recipe_id, $args, $field_column_value = '' ) {

		// Handle empty input first.
		if ( empty( trim( $field_column_value ) ) ) {
			throw new Exception(
				esc_html_x( 'Empty field column values detected.', 'Notion', 'uncanny-automator' ),
				400
			);
		}

		// Check for JSON syntax errors.
		$column_value_decoded = json_decode( $field_column_value, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$error_message = sprintf(
				/* translators: %s: JSON error message */
				esc_html_x( 'Invalid JSON format in field column values: %s', 'Notion', 'uncanny-automator' ),
				esc_html( json_last_error_msg() )
			);
			// Already escaped in the sprintf.
			throw new Exception( $error_message, 400 ); //phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		// Convert to array and check if it's empty.
		$column_value_decoded = (array) $column_value_decoded;

		if ( empty( $column_value_decoded ) ) {
			throw new Exception(
				esc_html_x( 'Empty field column values detected.', 'Notion', 'uncanny-automator' ),
				400
			);
		}

		$fields_raw_values = $column_value_decoded[0] ?? array();
		$fields_id_value   = array();

		foreach ( (array) $fields_raw_values as $key => $value ) {

			// Skip _readable fields.
			if ( strpos( $key, '_readable' ) !== false ) {
				continue;
			}

			try {

				$split = self::extract_field_parameters( $key );
				// Extract the field ID and type.
				list ( $notion, $field, $field_id, $type ) = $split;

				$value = is_array( $value )
					// Skip array values (like people, multi_select) - they don't need parsing.
					? $value
					// Parse string and other scalar values.
					: Automator()->parse->text( $value, $recipe_id, $user_id, $args );

				// Convert date values to ISO 8601 format for Notion API.
				if ( 'date' === $type && ! empty( $value ) && ! is_array( $value ) ) {
					try {
						$value = $this->convert_to_iso_8601( $value );
					} catch ( Exception $e ) {
						throw new Exception(
							sprintf(
								// translators: %s: The invalid date string
								esc_html_x( 'Invalid date format: "%s". Please provide a valid date.', 'Notion', 'uncanny-automator' ),
								esc_html( $value )
							),
							400
						);
					}
				}

				$fields_id_value[ $field_id ] = array(
					'type'  => $type,
					'value' => $value,
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
	public function extract_field_parameters_columns( $input ) {

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

	/**
	 * Get the list of unsupported field types.
	 *
	 * @return array
	 */
	public function get_unsupported_field_types() {
		return self::$update_column_fields_not_supported;
	}
}
