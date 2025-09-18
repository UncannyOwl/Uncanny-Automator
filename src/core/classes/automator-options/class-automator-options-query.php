<?php

namespace Uncanny_Automator;

use wpdb;

/**
 * Handles database queries for the Automator options system.
 *
 * This class provides a reusable interface for querying both the custom
 * uap_options table and WordPress wp_options table with proper precedence.
 *
 * CRITICAL CONTRACT:
 * - All READ methods return RAW database values (possibly serialized)
 * - All WRITE methods expect PRE-SERIALIZED values
 * - The formatting layer depends on this contract - DO NOT return decoded values
 *
 * @requires Database schema: UNIQUE index on uap_options(option_name) for ON DUPLICATE KEY UPDATE operations
 */
final class Automator_Options_Query {

	/**
	 * The WordPress database object.
	 *
	 * @var wpdb
	 */
	private $db;

	/**
	 * Constructor.
	 *
	 * @param wpdb|null $db Optional database instance.
	 */
	public function __construct( ?wpdb $db = null ) {
		if ( is_null( $db ) ) {
			global $wpdb;
			$this->db = $wpdb;
		} else {
			$this->db = $db;
		}
	}
	/**
	 * Get db.
	 *
	 * @return mixed
	 */
	public function get_db() {
		return $this->db;
	}

	/**
	 * Get a single option value with UAP precedence over WordPress.
	 *
	 * ⚠️  CRITICAL: Returns RAW database value (possibly serialized).
	 * ⚠️  If you change this to return decoded values, you MUST remove
	 *     format_value() calls in the caching layer or double-decode will occur.
	 *
	 * @param string $option_name The option name to retrieve.
	 *
	 * @return string|null The RAW option value or null if not found.
	 */
	public function get_option_value( string $option_name ) {
		return $this->db->get_var(
			$this->db->prepare(
				"SELECT COALESCE(
					(SELECT option_value FROM {$this->db->prefix}uap_options WHERE option_name = %s),
					(SELECT option_value FROM {$this->db->prefix}options WHERE option_name = %s)
				) as option_value",
				$option_name,
				$option_name
			)
		);
	}

	/**
	 * Get all autoloaded options from UAP table.
	 *
	 * @return array Array of option data with option_name and option_value keys.
	 */
	public function get_autoloaded_uap_options() {
		return (array) $this->db->get_results(
			"SELECT option_name, option_value FROM {$this->db->prefix}uap_options WHERE autoload = 'yes'",
			ARRAY_A
		);
	}

	/**
	 * Get specific autoloaded WordPress options.
	 *
	 * @param array $option_names Array of option names to retrieve.
	 *
	 * @return array Array of option data with option_name and option_value keys.
	 */
	public function get_autoloaded_wp_options( array $option_names ) {
		if ( empty( $option_names ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $option_names ), '%s' ) );

		return (array) $this->db->get_results(
			$this->db->prepare(
				"SELECT option_name, option_value 
				 FROM {$this->db->prefix}options 
				 WHERE autoload = 'yes' AND option_name IN ($placeholders)",
				...$option_names
			),
			ARRAY_A
		);
	}

	/**
	 * Check if an option exists in either table.
	 *
	 * @param string $option_name The option name to check.
	 *
	 * @return bool True if option exists, false otherwise.
	 */
	public function option_exists( string $option_name ) {
		$count = $this->db->get_var(
			$this->db->prepare(
				"SELECT 1 FROM (
					SELECT 1 FROM {$this->db->prefix}uap_options WHERE option_name = %s
					UNION
					SELECT 1 FROM {$this->db->prefix}options WHERE option_name = %s
				) as combined LIMIT 1",
				$option_name,
				$option_name
			)
		);

		return '1' === $count;
	}

	/**
	 * Insert a new option into UAP table.
	 *
	 * @param string $option_name The option name.
	 * @param string $option_value The serialized option value.
	 * @param bool $autoload Whether to autoload the option.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function insert_option( string $option_name, string $option_value, bool $autoload = false ) {
		$autoload_flag = $autoload ? 'yes' : 'no';

		$result = $this->db->query(
			$this->db->prepare(
				"INSERT INTO {$this->db->prefix}uap_options (option_name, option_value, autoload) VALUES (%s, %s, %s)",
				$option_name,
				$option_value,
				$autoload_flag
			)
		);

		return false !== $result;
	}

	/**
	 * Update an option in UAP table (upsert).
	 *
	 * Uses INSERT ... ON DUPLICATE KEY UPDATE which requires UNIQUE(option_name).
	 *
	 * @param string $option_name The option name.
	 * @param string $option_value The serialized option value.
	 * @param bool $autoload Whether to autoload the option.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function upsert_option( string $option_name, string $option_value, bool $autoload = false ) {
		$autoload_flag = $autoload ? 'yes' : 'no';

		$result = $this->db->query(
			$this->db->prepare(
				"INSERT INTO {$this->db->prefix}uap_options (option_name, option_value, autoload) 
				 VALUES (%s, %s, %s) 
				 ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = VALUES(autoload)",
				$option_name,
				$option_value,
				$autoload_flag
			)
		);

		return false !== $result;
	}

	/**
	 * Delete an option from UAP table.
	 *
	 * @param string $option_name The option name.
	 *
	 * @return bool True if deleted, false otherwise.
	 */
	public function delete_option( string $option_name ) {

		$result_uap = $this->db->delete(
			$this->db->prefix . 'uap_options',
			array( 'option_name' => $option_name ),
			array( '%s' )
		);

		// Also delete from wp_options.
		$result_wp = $this->db->delete(
			$this->db->prefix . 'options',
			array( 'option_name' => $option_name ),
			array( '%s' )
		);

		return ( false !== $result_uap && $result_uap > 0 ) || ( false !== $result_wp && $result_wp > 0 );
	}
}
