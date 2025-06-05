<?php

namespace Uncanny_Automator;

class Automator_Options {

	// Make sure all objects share the same cached options.
	public static $cached_options = null;

	/**
	 * Automator_Options constructor.
	 */
	public function __construct() {
		if ( null === self::$cached_options ) {
			$this->autoload_options();
		}
	}

	/**
	 * Retrieves all options from the uap_options table, with optional cache refresh.
	 *
	 * @return void
	 */
	public function autoload_options() {
		global $wpdb;
		$all_options_db = $wpdb->get_results(
			"SELECT `option_name`, `option_value`, `type` FROM {$wpdb->uap_options} WHERE autoload = 'yes'" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded
		);

		$all_options = array();

		foreach ( (array) $all_options_db as $o ) {
			$all_options[ $o->option_name ]           = $o->option_value;
			$all_options[ $o->option_name . '_type' ] = $o->type;
		}

		// Store the result in the static cache.
		self::$cached_options = $all_options;
	}

	/**
	 * Retrieves an option value, checking cache, custom table, and WP options table.
	 *
	 * @param string $option Option name.
	 * @param mixed $default_value Default value if not found.
	 * @param bool $force Force cache refresh (unused).
	 *
	 * @return mixed Option value or default.
	 */
	public function get_option( $option, $default_value = false, $force = false ) {

		// Trim the option.
		$option = trim( $option );

		// Bail if the option is not scalar or empty.
		if ( ! is_scalar( $option ) || empty( $option ) ) {
			return false;
		}

		// If there is a cached value, return it.
		if ( isset( self::$cached_options[ $option ] ) ) {
			$value = self::$cached_options[ $option ];
			return $this->output_option_value( $value, $option, $default_value );
		}

		// Check if there is a value in the automator options table.
		$automator_db_value = $this->get_automator_db_option( $option, $default_value );

		if ( null !== $automator_db_value ) {
			return $this->output_option_value( $automator_db_value, $option, $default_value );
		}

		// Check if there is a value in the WordPress options table.
		$wp_db_value = $this->get_wp_db_option( $option );

		if ( null !== $wp_db_value ) {
			$this->add_option( $option, $wp_db_value, true, false );
			return $this->output_option_value( $wp_db_value, $option, $default_value );
		}

		return $default_value;
	}

	/**
	 * Retrieves an option from the custom table, and caches its value and type.
	 *
	 * @param string $option Option name.
	 * @param mixed $default_value Default value if not found.
	 *
	 * @return mixed|null Option value or null if not found.
	 */
	public function get_automator_db_option( $option, $default_value ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL
		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded
				"SELECT `option_value`, `type` FROM {$wpdb->uap_options} WHERE `option_name` = %s LIMIT 1",
				$option
			)
		);

		if ( ! is_object( $row ) ) {
			return null;
		}

		$this->cache_value( $option, $row->option_value );
		$this->cache_value( "{$option}_type", $row->type );

		return $row->option_value;
	}

	/**
	 * Retrieves an option from the WordPress options table.
	 *
	 * @param string $option Option name.
	 *
	 * @return mixed|null Option value or null if not found.
	 */
	public function get_wp_db_option( $option ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL
		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				$option
			)
		);

		// If the value is found in the database, return it.
		if ( ! is_object( $row ) ) {
			return null;
		}

		return $row->option_value;
	}

	/**
	 * Caches a value in the static options cache.
	 *
	 * @param string $option Option name.
	 * @param mixed $value Value to cache.
	 *
	 * @return void
	 */
	private function cache_value( $option, $value ) {
		self::$cached_options[ $option ] = $value;
	}

	/**
	 * Formats and filters an option value for output.
	 *
	 * @param mixed $value Raw value.
	 * @param string $option Option name.
	 * @param mixed $default_value Default value if not found.
	 *
	 * @return mixed Filtered and formatted value.
	 */
	public function output_option_value( $value, $option, $default_value ) {
		$formatted_value = $this->format_option_value( $option, $value, $default_value );
		return apply_filters( "automator_option_{$option}", $formatted_value, $option );
	}

	/**
	 * Adds or updates an option in the uap_options table.
	 *
	 * @param string $option Name of the option.
	 * @param mixed $value Value of the option.
	 * @param bool $autoload Whether to autoload the option or not.
	 * @param bool $run_actions Whether to run do_action hooks or not.
	 *
	 * @return void
	 */
	public function add_option( $option, $value, $autoload = true, $run_actions = true ) {
		global $wpdb;

		if ( ! is_scalar( $option ) || empty( trim( $option ) ) ) {
			return;
		}

		// Determine the original type.
		$type = gettype( $value );

		// Convert booleans to special strings for storage.
		if ( is_bool( $value ) ) {
			$value = $value ? '__true__' : '__false__';
		}

		if ( null === $value ) {
			$value = '__null__';
		}

		$option           = trim( $option );
		$serialized_value = is_scalar( $value ) ? $value : maybe_serialize( $value );
		$autoload_flag    = $autoload ? 'yes' : 'no';

		// Fire actions before adding or updating the option.
		if ( $run_actions ) {
			do_action( 'automator_add_option', $option, $value );
		}

		// Use INSERT ... ON DUPLICATE KEY UPDATE for a single upsert operation, now including type column.
		// phpcs:ignore WordPress.DB.PreparedSQL
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded
				"INSERT INTO {$wpdb->uap_options} (`option_name`, `option_value`, `autoload`, `type`)
                VALUES (%s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE `option_value` = VALUES(`option_value`), `autoload` = VALUES(`autoload`), `type` = VALUES(`type`)",
				$option,
				$serialized_value,
				$autoload_flag,
				$type
			)
		);

		self::$cached_options[ $option ]          = $value;
		self::$cached_options[ "{$option}_type" ] = $type;

		// Fire post-add/update actions.
		if ( $run_actions ) {
			do_action( "automator_add_option_{$option}", $option, $value );
			do_action( 'automator_option_added', $option, $value );
		}
	}

	/**
	 * Deletes an option from the uap_options table and cache.
	 *
	 * @param string $option Option name.
	 *
	 * @return bool True if deleted, false otherwise.
	 */
	public function delete_option( $option ) {
		global $wpdb;

		// Delete the option from the database
		$deleted = $wpdb->delete(
			$wpdb->uap_options,
			array( 'option_name' => $option ),
			array( '%s' )
		);

		$wpdb->delete(
			$wpdb->uap_options,
			array( 'option_name' => $option . '_type' ),
			array( '%s' )
		);

		// Fallback to deleting the option from the database
		delete_option( $option );

		unset( self::$cached_options[ $option ] );
		unset( self::$cached_options[ $option . '_type' ] );

		do_action( 'automator_option_deleted', $option );

		return ( false !== $deleted );
	}

	/**
	 * Updates or adds an option in the uap_options table using upsert.
	 *
	 * @param string $option Name of the option.
	 * @param mixed $value Value of the option.
	 * @param bool $autoload Whether to autoload the option or not.
	 *
	 * @return bool True if the operation was successful, false otherwise.
	 */
	public function update_option( $option, $value, $autoload = true ) {
		global $wpdb;

		if ( ! is_scalar( $option ) || empty( trim( $option ) ) ) {
			return false;
		}

		// Determine the original type.
		$type = gettype( $value );

		$option           = trim( $option );
		$serialized_value = is_scalar( $value ) ? $value : maybe_serialize( $value );
		$autoload_flag    = $autoload ? 'yes' : 'no';

		// Perform the upsert operation, now including type column.
		// phpcs:ignore WordPress.DB.PreparedSQL
		$result = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded
				"INSERT INTO {$wpdb->uap_options} (option_name, option_value, autoload, type)
                VALUES (%s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = VALUES(autoload), type = VALUES(type)",
				$option,
				$serialized_value,
				$autoload_flag,
				$type
			)
		);

		self::$cached_options[ $option ]          = $value;
		self::$cached_options[ "{$option}_type" ] = $type;

		// Fire post-update actions.
		do_action( "automator_update_option_{$option}", $option, $value );
		do_action( 'automator_updated_option', $option, $value );

		return ( false !== $result );
	}

	/**
	 * Validates, sanitizes, and determines the correct value to return.
	 *
	 * @param string $option The option name.
	 * @param mixed $value The value retrieved from cache or DB.
	 * @param mixed $default_value The default value to use if needed.
	 *
	 * @return mixed The final sanitized value.
	 */
	public function format_option_value( $option, $value, $default_value ) {

		// Unserialize the value if needed.
		$value = maybe_unserialize( $value );

		// Return false if the value is false.
		if ( '__false__' === $value || ( '' === $value && false === $default_value ) ) {
			return false;
		}

		// Return true if the value is true.
		if ( '__true__' === $value || ( '' === $value && true === $default_value ) ) {
			return true;
		}

		// Return null if the value is null.
		if ( '__null__' === $value || ( '' === $value && null === $default_value ) ) {
			return $default_value;
		}

		// Return '' if the value is truly empty.
		if ( '' === $value ) {
			return $value;
		}

		$original_type = isset( self::$cached_options[ "{$option}_type" ] ) ? self::$cached_options[ "{$option}_type" ] : null;

		// Use the original type to restore the value's type.
		switch ( $original_type ) {
			case 'integer':
				return (int) $value;
			case 'double':
				return (float) $value;
			case 'boolean':
				return (bool) $value;
			case 'NULL':
				return null;
			default:
				return $value;  // Return as-is for strings and other types.
		}
	}

	/**
	 * Retrieves the results of a query from the database.
	 *
	 * @param string $query The SQL query to execute.
	 *
	 * @return array The results of the query.
	 */
	private function db_get_results( $query ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL
		$suppress = $wpdb->suppress_errors();

		$results = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$wpdb->suppress_errors( $suppress );

		return $results;
	}

	/**
	 * Retrieves a single row from the database.
	 *
	 * @param string $query The SQL query to execute.
	 *
	 * @return object|null The row object or null if not found.
	 */
	private function db_get_row( $query ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL
		$row = $wpdb->get_row( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return $row;
	}

	/**
	 * Executes a query on the database.
	 *
	 * @param string $query The SQL query to execute.
	 *
	 * @return mixed The result of the query.
	 */
	private function db_query( $query ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL
		$result = $wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return $result;
	}
}
