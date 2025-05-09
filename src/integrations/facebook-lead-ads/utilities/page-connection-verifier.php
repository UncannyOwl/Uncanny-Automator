<?php
namespace Uncanny_Automator\Integrations\Facebook_Lead_Ads\Utilities;

use Exception;
use Uncanny_Automator\Integrations\Facebook_Lead_Ads\Utilities\Credentials_Manager;
use Uncanny_Automator\Integrations\Facebook_Lead_Ads\Utilities\Client;
use WP_Error;

/**
 * Class Page_Connection_Verifier.
 *
 * Verifies the connection status of Facebook pages and manages caching of the status.
 *
 * @package Uncanny_Automator\Integrations\Facebook_Lead_Ads\Utilities
 */
class Page_Connection_Verifier {

	/**
	 * Transient key for caching statuses.
	 *
	 * @var string
	 */
	const TRANSIENT_KEY = 'automator_fbla_verify_page_connection_statuses';

	/**
	 * Credentials Manager instance.
	 *
	 * @var Credentials_Manager
	 */
	private $credentials_manager;

	/**
	 * Constructor.
	 *
	 * Initializes the class with a Credentials Manager instance.
	 *
	 * @param Credentials_Manager $credentials_manager An instance to manage credentials.
	 */
	public function __construct( Credentials_Manager $credentials_manager ) {
		$this->credentials_manager = $credentials_manager;
	}

	/**
	 * Verify the connection status of a page.
	 *
	 * Checks the connection status of a Facebook page, leveraging cache if available.
	 *
	 * @param int|string $page_id Page ID.
	 * @param bool       $force   Whether to force refresh the status.
	 * @return string|WP_Error Status message or WP_Error on failure.
	 */
	public function verify_page_connection( $page_id, $force ) {
		// Get cached statuses.
		$cached_statuses = $this->get_cached_statuses();

		// Return cached status if available and force is not set.
		if ( isset( $cached_statuses[ $page_id ] ) && ! $force ) {
			return $this->format_status_message( $cached_statuses[ $page_id ] );
		}

		// Fetch the status from the external service.
		$status = $this->fetch_status_from_service( $page_id );

		// Cache the status.
		$this->cache_status( $page_id, $status );

		return $this->format_status_message( $status );
	}

	/**
	 * Get cached statuses from the transient.
	 *
	 * Retrieves cached statuses, returning an empty array if none exist.
	 *
	 * @return array Cached statuses.
	 */
	private function get_cached_statuses() {
		$cached_statuses = get_transient( self::TRANSIENT_KEY );
		return is_array( $cached_statuses ) ? $cached_statuses : array();
	}

	/**
	 * Cache the status of a specific page.
	 *
	 * Stores the connection status of a page in a transient cache.
	 *
	 * @param int|string    $page_id Page ID.
	 * @param string|WP_Error $status Status to cache.
	 */
	private function cache_status( $page_id, $status ) {
		$cached_statuses             = $this->get_cached_statuses();
		$cached_statuses[ $page_id ] = array(
			'status'       => $status,
			'last_checked' => time(),
		);

		// Save the updated cache with a 1-hour expiration.
		set_transient( self::TRANSIENT_KEY, $cached_statuses, HOUR_IN_SECONDS );
	}

	/**
	 * Fetch the connection status from the external service.
	 *
	 * Sends a request to the external service to retrieve the connection status of a page.
	 *
	 * @param int|string $page_id Page ID.
	 * @return string|WP_Error Status message or WP_Error on failure.
	 */
	private function fetch_status_from_service( $page_id ) {
		// Get the access token.
		$access_token = $this->credentials_manager->get_page_access_token( $page_id );

		if ( empty( $access_token ) ) {
			return new WP_Error( 'connection_error', esc_html_x( 'No access token found for the provided page ID.', 'Facebook Lead Ads', 'uncanny-automator' ) );
		}

		// Prepare the request body.
		$body = $this->prepare_request_body( $page_id, $access_token );

		try {
			// Send the request to the external service.
			$result = Client::send_request( $body );

			// Return success message.
			return esc_html_x( 'Ready', 'Facebook Lead Ads', 'uncanny-automator' );
		} catch ( Exception $e ) {
			// Return error as WP_Error.
			return new WP_Error( 'connection_error', $e->getMessage() );
		}
	}

	/**
	 * Prepare the request body for the external service.
	 *
	 * Generates the required data structure for the API request.
	 *
	 * @param int|string $page_id Page ID.
	 * @param string     $access_token Page access token.
	 * @return array Prepared request body.
	 */
	private function prepare_request_body( $page_id, $access_token ) {
		return array(
			'action'            => 'verify_page_connection',
			'page_id'           => $page_id,
			'page_access_token' => $access_token,
		);
	}

	/**
	 * Format the status message with the time last checked.
	 *
	 * Constructs a human-readable message based on the cached status data.
	 *
	 * @param array|string|WP_Error $status_data Cached status data.
	 * @return string|WP_Error Formatted status message or WP_Error on failure.
	 */
	private function format_status_message( $status_data ) {
		if ( is_wp_error( $status_data ) ) {
			return $status_data;
		}

		if ( is_array( $status_data ) ) {
			$status       = $status_data['status'];
			$last_checked = $status_data['last_checked'];

			// Calculate time difference.
			$time_diff = human_time_diff( $last_checked, time() );

			if ( ! is_string( $status ) ) {
				return new WP_Error( 'status_error', wp_json_encode( $status ) );
			}

			// Return formatted message.
			return sprintf( '%s (last checked: %s ago)', $status, $time_diff );
		}

		// If no timestamp is found, return the raw status.
		return $status_data;
	}
}
