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

	////////////////////////////////////////////////////////////
	// Remote-data REST handlers
	//
	// Reachable via POST /wp-json/uap/v2/remote-data/notion/{data},
	// where {data} matches the suffix on the method name. Dispatched
	// through Abstract_Helpers::process_remote_data_request().
	////////////////////////////////////////////////////////////

	/**
	 * Fetch all accessible Notion pages for the parent select on Create Page.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_pages( $request ): array {
		try {
			$options = $this->api->list_pages();
			return $this->remote_data_success( $options );
		} catch ( Exception $e ) {
			return $this->remote_data_error(
				sprintf( 'API Exception: (%2$s) %1$s', $e->getMessage(), $e->getCode() )
			);
		}
	}

	/**
	 * Fetch all databases the connected workspace has access to.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_databases( $request ): array {
		try {
			$options = $this->api->list_databases();
			return $this->remote_data_success( $options );
		} catch ( Exception $e ) {
			return $this->remote_data_error(
				sprintf( 'API Exception: (%2$s) %1$s', $e->getMessage(), $e->getCode() )
			);
		}
	}

	/**
	 * Fetch the column list for the COLUMN_SEARCH select on the update-row action.
	 *
	 * `group_id` carries the parent option code that triggered the cascade (e.g.
	 * NOTION_UPDATE_ROW_META) — the database UUID is read from values[group_id].
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_database_columns( $request ): array {
		$values   = $request->get_values();
		$group_id = $request->get_group_id();
		$db_id    = $values[ $group_id ] ?? null;

		try {
			$options = $this->api->get_database_columns( $db_id );
			return $this->remote_data_success( $options );
		} catch ( Exception $e ) {
			return $this->remote_data_error( $e->getMessage() );
		}
	}

	/**
	 * Build the dynamic field set for the FIELD_COLUMN_VALUE repeater based on
	 * the selected database's property schema. Response uses the
	 * `field_properties` envelope the legacy repeater consumer reads from.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_database_fields( $request ): array {
		$values   = $request->get_values();
		$group_id = $request->get_group_id();
		$db_id    = $values[ $group_id ] ?? null;

		try {
			$fields = $this->api->get_database_fields( $db_id );
			return $this->remote_data_success(
				array( 'fields' => $fields ),
				'field_properties'
			);
		} catch ( Exception $e ) {
			return $this->remote_data_error(
				sprintf( 'API Exception: (%2$s) %1$s', $e->getMessage(), $e->getCode() ),
				'field_properties'
			);
		}
	}

	/**
	 * Fetch the user list for the people select. Cached for 5 minutes — first
	 * request after the cache expires hits the Notion API; subsequent requests
	 * (within the window) return the cached options.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_users( $request ): array {
		$persons_cached = get_transient( self::PERSONS_TRANSIENT_KEY );

		if ( false !== $persons_cached ) {
			return $this->remote_data_success( $persons_cached );
		}

		try {
			$options = $this->api->list_users();

			if ( is_array( $options ) && ! empty( $options ) ) {
				set_transient( self::PERSONS_TRANSIENT_KEY, $options, MINUTE_IN_SECONDS * 5 );
			}

			return $this->remote_data_success( $options );
		} catch ( Exception $e ) {
			return $this->remote_data_error( $e->getMessage() );
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

			} catch ( \InvalidArgumentException $e ) {
				// Simplified key format from AI agent / Action_Executor.
				// Accepts "field_id:type" (e.g. "abc123:rich_text") or plain name (defaults to title).
				$parsed   = self::parse_simplified_key( $key );
				$field_id = $parsed['id'];
				$type     = $parsed['type'];
			}

			try {

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
	 * Parse a simplified field key from the AI agent / Action_Executor.
	 *
	 * Accepts two formats:
	 *   - "field_id:type" (e.g. "abc123:rich_text") — explicit ID and type.
	 *   - "Name" (plain string) — used as both the ID and name, defaults to "title" type.
	 *
	 * @since 7.2.0
	 *
	 * @param string $key The simplified key.
	 *
	 * @return array{id: string, type: string} Parsed field ID and type.
	 */
	public static function parse_simplified_key( $key ) {

		// Check for "id:type" notation (e.g. "abc123:rich_text").
		$last_colon = strrpos( $key, ':' );

		if ( false !== $last_colon && $last_colon > 0 ) {
			$potential_id   = substr( $key, 0, $last_colon );
			$potential_type = substr( $key, $last_colon + 1 );

			// Only treat as id:type if the type part is a known Notion property type.
			$known_types = array(
				'title',
				'rich_text',
				'number',
				'select',
				'multi_select',
				'date',
				'checkbox',
				'url',
				'email',
				'phone_number',
				'status',
			);

			if ( in_array( $potential_type, $known_types, true ) ) {
				return array(
					'id'   => $potential_id,
					'type' => $potential_type,
				);
			}
		}

		// Plain name — default to title type.
		return array(
			'id'   => $key,
			'type' => 'title',
		);
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
