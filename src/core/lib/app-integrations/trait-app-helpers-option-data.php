<?php

namespace Uncanny_Automator\App_Integrations;

/**
 * Trait for App option data management.
 *
 * Provides standardized option key building, cached option storage with
 * timestamp-based expiration, and common AJAX response helpers for
 * serving option data to the recipe builder.
 *
 * @package Uncanny_Automator\App_Integrations
 *
 * @requires get_settings_id() string  The integration's settings ID (provided by App_Helpers).
 */
trait App_Helpers_Option_Data {

	/**
	 * Option prefix for this integration.
	 * Used to build standardized option keys and for bulk cleanup.
	 *
	 * @var string
	 */
	protected $option_prefix = '';

	/**
	 * Set the option prefix.
	 *
	 * @param string $option_prefix The option prefix.
	 *
	 * @return void
	 */
	protected function set_option_prefix( $option_prefix ) {
		$this->option_prefix = $option_prefix;
	}

	/**
	 * Get the option prefix.
	 *
	 * @return string The prefix (e.g. 'automator_mailchimp_').
	 */
	public function get_option_prefix() {
		return $this->option_prefix;
	}

	/**
	 * Get a standardized option key.
	 *
	 * Builds a full option key by combining the integration's option prefix with the given suffix.
	 * Use this method to ensure consistent option key naming across the integration.
	 *
	 * @param string $suffix The option key suffix (e.g. 'lists', 'tags', 'custom_fields').
	 *
	 * @return string The full option key (e.g. 'automator_mailchimp_lists').
	 */
	public function get_option_key( $suffix ) {
		return $this->option_prefix . $suffix;
	}

	/**
	 * Get App Option.
	 *
	 * Retrieves cached data with timestamp expiration checking.
	 *
	 * @param string $option_key    The full option key (use get_option_key() to build).
	 * @param int    $refresh_check Cache duration in seconds. Default DAY_IN_SECONDS.
	 *
	 * @return array{data: array, timestamp: int, refresh: bool} Cached data with refresh flag.
	 */
	public function get_app_option( $option_key, $refresh_check = DAY_IN_SECONDS ) {
		$data = automator_get_option(
			$option_key,
			array(
				'data'      => array(),
				'timestamp' => 0,
			)
		);

		$timestamp = $data['timestamp'] ?? 0;

		return array(
			'data'      => $data['data'] ?? array(),
			'timestamp' => $timestamp,
			'refresh'   => ( time() - $timestamp ) > $refresh_check,
		);
	}

	/**
	 * Save App Option.
	 *
	 * Saves data with current timestamp for cache expiration tracking.
	 *
	 * @param string $option_key The full option key (use get_option_key() to build).
	 * @param mixed  $data       The data to cache.
	 *
	 * @return void
	 */
	public function save_app_option( $option_key, $data ) {
		automator_update_option(
			$option_key,
			array(
				'data'      => $data,
				'timestamp' => time(),
			),
			false
		);
	}

	////////////////////////////////////////////////////////////
	// AJAX helpers
	////////////////////////////////////////////////////////////

	/**
	 * Check if the request is an AJAX refresh.
	 * - Used for handling AJAX requests from Recipe Builder when requesting data for options.
	 * - This is a common pattern when saving the option data to uap_options
	 * - When this refresh context is detected the user is attempting to retrieve updated data for the integration.
	 *
	 * @return bool
	 */
	public function is_ajax_refresh() {
		$context = automator_filter_has_var( 'context', INPUT_POST )
			? automator_filter_input( 'context', INPUT_POST )
			: '';
		return 'refresh-button' === $context;
	}

	/**
	 * Send a JSON error response.
	 *
	 * @param string $error The error message.
	 * @param string $key   The key for the empty data array. Default 'options'.
	 *
	 * @return void
	 */
	public function ajax_error( $error, $key = 'options' ) {
		wp_send_json(
			array(
				'success' => false,
				'error'   => $error,
				$key      => array(),
			)
		);
	}

	/**
	 * Send a JSON success response.
	 *
	 * @param array  $data The data to send.
	 * @param string $key  The key for the data array. Default 'options'.
	 *
	 * @return void
	 */
	public function ajax_success( $data, $key = 'options' ) {
		wp_send_json(
			array(
				'success' => true,
				$key      => $data,
			)
		);
	}
}
