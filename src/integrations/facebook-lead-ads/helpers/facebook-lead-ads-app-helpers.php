<?php

namespace Uncanny_Automator\Integrations\Facebook_Lead_Ads;

use Exception;
use Uncanny_Automator\App_Integrations\App_Helpers;
use WP_Error;

/**
 * Facebook Lead Ads App Helpers
 *
 * Extends the App_Helpers framework class to provide Facebook Lead Ads specific functionality.
 * Consolidates credential management, connection verification, and AJAX handlers.
 *
 * @package Uncanny_Automator\Integrations\Facebook_Lead_Ads
 *
 * @property Facebook_Lead_Ads_Api_Caller $api
 * @property Facebook_Lead_Ads_Webhooks $webhooks
 */
class Facebook_Lead_Ads_App_Helpers extends App_Helpers {

	/**
	 * Transient key for caching page connection statuses.
	 *
	 * @var string
	 */
	const PAGE_STATUS_TRANSIENT_KEY = 'automator_fbla_verify_page_connection_statuses';

	/**
	 * Trigger meta key.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'FB_LEAD_ADS_META';

	/**
	 * Post meta key for storing form fields.
	 *
	 * @var string
	 */
	const FORM_FIELDS_META_KEY = 'meta_form_fields';

	////////////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Validate credentials.
	 * Ensures both user and page credentials are present.
	 *
	 * @param mixed $credentials The credentials.
	 * @param array $args        Optional arguments.
	 *
	 * @return array
	 */
	public function validate_credentials( $credentials, $args = array() ) {
		if ( ! is_array( $credentials ) ) {
			return array( 'user_access_token' => '' );
		}

		return $credentials;
	}

	/**
	 * Get account info from credentials.
	 *
	 * @return array
	 */
	public function get_account_info() {
		$credentials = $this->get_credentials();

		return $credentials['user'] ?? array();
	}

	////////////////////////////////////////////////////////////
	// Credential convenience methods
	////////////////////////////////////////////////////////////

	/**
	 * Check if the integration has a valid connection.
	 *
	 * @return bool
	 */
	public function has_connection() {
		$credentials = $this->get_credentials();

		return $this->has_user_credentials( $credentials )
			&& $this->has_pages_credentials( $credentials );
	}

	/**
	 * Check if user credentials exist.
	 *
	 * @param array|null $credentials Optional credentials array.
	 *
	 * @return bool
	 */
	public function has_user_credentials( $credentials = null ) {
		$credentials = $credentials ?? $this->get_credentials();

		return ! empty( $credentials['user_access_token'] );
	}

	/**
	 * Check if pages credentials exist.
	 *
	 * @param array|null $credentials Optional credentials array.
	 *
	 * @return bool
	 */
	public function has_pages_credentials( $credentials = null ) {
		$credentials = $credentials ?? $this->get_credentials();

		return ! empty( $credentials['pages_access_tokens'] );
	}

	/**
	 * Get pages credentials array.
	 *
	 * @return array
	 */
	public function get_pages_credentials() {
		$credentials = $this->get_credentials();

		return (array) ( $credentials['pages_access_tokens'] ?? array() );
	}

	////////////////////////////////////////////////////////////
	// Page status verification
	////////////////////////////////////////////////////////////

	/**
	 * Verify the connection status of a specific page.
	 *
	 * @param int|string $page_id The Facebook page ID.
	 * @param bool       $force   Whether to bypass cache.
	 *
	 * @return string|WP_Error
	 */
	public function verify_page_connection( $page_id, $force = false ) {
		if ( empty( $page_id ) ) {
			return new WP_Error(
				'invalid_page_id',
				esc_html_x( 'The provided page ID is invalid.', 'Facebook Lead Ads', 'uncanny-automator' )
			);
		}

		// Check cache first.
		$cached_statuses = $this->get_cached_page_statuses();

		if ( isset( $cached_statuses[ $page_id ] ) && ! $force ) {
			return $this->format_page_status( $cached_statuses[ $page_id ] );
		}

		// Fetch fresh status from API.
		$status = $this->api->verify_page_status( $page_id );

		// Cache the status.
		$this->cache_page_status( $page_id, $status );

		return $this->format_page_status( $status );
	}

	/**
	 * Get cached page status without triggering a fetch.
	 *
	 * @param int|string $page_id The Facebook page ID.
	 *
	 * @return string|null The cached status string, or null if not cached.
	 */
	public function get_cached_page_status( $page_id ) {
		if ( empty( $page_id ) ) {
			return null;
		}

		$cached_statuses = $this->get_cached_page_statuses();
		if ( ! isset( $cached_statuses[ $page_id ] ) ) {
			return null;
		}

		$formatted = $this->format_page_status( $cached_statuses[ $page_id ] );

		// Return null for errors so caller can distinguish "no cache" from "cached error".
		return is_wp_error( $formatted ) ? $formatted->get_error_message() : $formatted;
	}

	/**
	 * Get cached page statuses.
	 *
	 * @return array
	 */
	private function get_cached_page_statuses() {
		$cached = get_transient( self::PAGE_STATUS_TRANSIENT_KEY );

		return is_array( $cached ) ? $cached : array();
	}

	/**
	 * Cache a page status.
	 *
	 * @param int|string      $page_id The page ID.
	 * @param string|WP_Error $status  The status.
	 *
	 * @return void
	 */
	private function cache_page_status( $page_id, $status ) {
		$cached             = $this->get_cached_page_statuses();
		$cached[ $page_id ] = array(
			'status'       => $status,
			'last_checked' => time(),
		);

		set_transient( self::PAGE_STATUS_TRANSIENT_KEY, $cached, HOUR_IN_SECONDS );
	}

	/**
	 * Format a page status for display.
	 *
	 * @param array|string|WP_Error $status_data The status data.
	 *
	 * @return string|WP_Error
	 */
	private function format_page_status( $status_data ) {
		if ( is_wp_error( $status_data ) ) {
			return $status_data;
		}

		if ( is_array( $status_data ) ) {
			$status       = $status_data['status'];
			$last_checked = $status_data['last_checked'];

			if ( is_wp_error( $status ) ) {
				return $status;
			}

			if ( ! is_string( $status ) ) {
				return new WP_Error( 'status_error', wp_json_encode( $status ) );
			}

			$time_diff = human_time_diff( $last_checked, time() );

			return sprintf( '%s (last checked: %s ago)', $status, $time_diff );
		}

		return $status_data;
	}

	////////////////////////////////////////////////////////////
	// AJAX handlers
	////////////////////////////////////////////////////////////

	/**
	 * AJAX handler for forms dropdown.
	 *
	 * @return void
	 */
	public function forms_handler_ajax() {
		Automator()->utilities->verify_nonce();

		$field_values = automator_filter_input_array( 'values', INPUT_POST );
		$page_id      = absint( $field_values[ self::TRIGGER_META ] ?? 0 );

		if ( empty( $page_id ) ) {
			$this->ajax_error( esc_html_x( 'Please select a page first.', 'Facebook Lead Ads', 'uncanny-automator' ) );
		}

		$forms = $this->api->get_forms( $page_id );

		if ( is_wp_error( $forms ) ) {
			$this->ajax_error(
				sprintf(
					// translators: Error message
					esc_html_x( 'An error has occurred while fetching the forms: %s', 'Facebook Lead Ads', 'uncanny-automator' ),
					$forms->get_error_message()
				)
			);
		}

		$forms   = (array) ( $forms['data']['data'] ?? array() );
		$options = array();

		foreach ( $forms as $form ) {
			$options[] = array(
				'text'  => $form['name'],
				'value' => $form['id'],
			);
		}

		$this->ajax_success( $options );
	}

	////////////////////////////////////////////////////////////
	// Option helpers
	////////////////////////////////////////////////////////////

	/**
	 * Get pages as options for select fields.
	 *
	 * @return array Array of options with text and value keys.
	 */
	public function get_pages_options() {
		if ( ! $this->has_pages_credentials() ) {
			return array();
		}

		$pages   = $this->get_pages_credentials();
		$options = array();

		foreach ( $pages as $page ) {
			if ( isset( $page['name'], $page['id'] ) ) {
				$options[] = array(
					'text'  => $page['name'],
					'value' => $page['id'],
				);
			}
		}

		return $options;
	}

	////////////////////////////////////////////////////////////
	// Token & lead data helpers
	////////////////////////////////////////////////////////////

	/**
	 * Analyze and store form field tokens when a form is selected.
	 *
	 * Note: Nonce validation is handled upstream by the REST API endpoint that
	 * fires this hook. The automator_recipe_before_update action only fires
	 * during authenticated REST requests with valid nonces.
	 *
	 * @param \WP_Post         $wp_post The post object.
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return void
	 */
	public function analyze_tokens( $wp_post, $request ) {
		$option_code = automator_filter_input( 'optionCode', INPUT_POST );

		if ( self::TRIGGER_META !== $option_code ) {
			return;
		}

		$input_values = automator_filter_input_array( 'optionValue', INPUT_POST );
		$form_id      = absint( $input_values['FORMS'] ?? 0 );
		$page_id      = absint( $input_values[ self::TRIGGER_META ] ?? 0 );

		if ( empty( $form_id ) || empty( $page_id ) ) {
			return;
		}

		$form_fields = $this->api->get_form_fields( $page_id, $form_id );
		if ( is_wp_error( $form_fields ) ) {
			automator_log( $form_fields->get_error_message(), self::class, true );
			return;
		}

		$fields = $form_fields['data']['questions'] ?? array();
		update_post_meta( $wp_post->ID, self::FORM_FIELDS_META_KEY, $fields );
	}

	/**
	 * Map lead data to their corresponding field keys.
	 *
	 * @param array $lead_data The lead data from Facebook.
	 *
	 * @return array
	 */
	public function map_lead_data( array $lead_data ) {
		$output = array();

		if ( ! isset( $lead_data['field_data'] ) || ! is_array( $lead_data['field_data'] ) ) {
			return $output;
		}

		foreach ( $lead_data['field_data'] as $field ) {
			$key   = $field['name'] ?? '';
			$value = $field['values'] ?? '';

			if ( is_array( $value ) ) {
				$sanitized_values = array_map( 'htmlentities', $value );
				$output[ $key ]   = implode( ', ', $sanitized_values );
				continue;
			}

			$output[ $key ] = htmlentities( (string) $value );
		}

		return $output;
	}
}
