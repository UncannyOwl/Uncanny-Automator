<?php

namespace Uncanny_Automator\Integrations\Facebook;

use Uncanny_Automator\Traits\Singleton;
use Exception;

/**
 * Facebook Bridge.
 *
 * Shared credential storage, API operations, and UI helpers for Facebook and Instagram integrations.
 * This class centralizes functionality that both integrations need, allowing Instagram to
 * trigger the same post-OAuth operations as Facebook without complex cross-integration dependencies.
 *
 * @package Uncanny_Automator\Integrations\Facebook
 */
class Facebook_Bridge {

	use Singleton;

	/**
	 * The option key for Facebook credentials.
	 * Uses framework-standard naming convention.
	 *
	 * @var string
	 */
	const CREDENTIALS_OPTION_KEY = 'automator_facebook_pages_credentials';

	/**
	 * The option key for Facebook pages data.
	 * Uses framework-standard naming convention.
	 *
	 * @var string
	 */
	const PAGES_OPTION_KEY = 'automator_facebook_pages_account';

	//////////////////////////////////////////////////////////////
	// Settings page helpers for both Facebook and Instagram.
	//////////////////////////////////////////////////////////////

	/**
	 * Get connect button label
	 *
	 * @return string
	 */
	public function get_connect_button_label() {
		return esc_html_x( 'Connect Facebook Pages account', 'Facebook', 'uncanny-automator' );
	}

	/**
	 * Get connected user info for display on the Settings page.
	 *
	 * @return array
	 */
	public function get_formatted_account_info() {
		$credentials = $this->get_facebook_credentials();

		$defaults = array(
			'picture' => '',
			'name'    => '',
			'user_id' => '',
		);

		$user = isset( $credentials['user-info'] ) ? $credentials['user-info'] : array();
		$user = wp_parse_args( $user, $defaults );

		// Build main info with name and icon
		$main_info = ! empty( $user['name'] )
			? $user['name'] . '<uo-icon integration="FACEBOOK"></uo-icon>'
			: '';

		// Format the additional info (user ID) with proper translation
		$additional_info = ! empty( $user['user_id'] )
			? sprintf(
				// translators: %1$d is the user's Facebook ID.
				esc_html_x( 'ID: %1$d', 'Facebook', 'uncanny-automator' ),
				absint( $user['user_id'] )
			)
			: '';

		return array(
			'avatar_type'  => 'image',
			'avatar_value' => $user['picture'] ?? '',
			'main_info'    => $main_info,
			'additional'   => $additional_info,
		);
	}

	///////////////////////////////////////////////////////////////////
	// Getters and setters for Facebook credentials and pages settings.
	///////////////////////////////////////////////////////////////////

	/**
	 * Get Facebook credentials.
	 *
	 * @return array
	 */
	public function get_facebook_credentials() {
		return (array) automator_get_option( self::CREDENTIALS_OPTION_KEY, array() );
	}

	/**
	 * User has connected to Facebook.
	 *
	 * @return bool
	 */
	public function user_has_connected_facebook() {
		return ! empty( $this->get_facebook_credentials() );
	}

	/**
	 * Update Facebook credentials.
	 *
	 * @param array $credentials The credentials.
	 * @return bool
	 */
	public function update_facebook_credentials( $credentials ) {
		return automator_update_option( self::CREDENTIALS_OPTION_KEY, $credentials );
	}

	/**
	 * Get Facebook pages settings.
	 *
	 * @return array
	 */
	public function get_facebook_pages_settings() {
		return (array) automator_get_option( self::PAGES_OPTION_KEY, array() );
	}

	/**
	 * User has connected to Facebook Pages.
	 *
	 * @return mixed
	 */
	public function user_has_connected_pages() {
		return ! empty( $this->get_facebook_pages_settings() );
	}

	/**
	 * Get a page by ID.
	 *
	 * @param string $page_id The page ID.
	 *
	 * @return mixed False if no page is found, the page data otherwise.
	 */
	public function get_facebook_page_by_id( $page_id ) {
		if ( empty( $page_id ) ) {
			return false;
		}

		$pages = $this->get_facebook_pages_settings();
		if ( ! empty( $pages ) ) {
			foreach ( $pages as $page ) {
				if ( (string) $page['value'] === (string) $page_id ) {
					return $page;
				}
			}
		}

		return false;
	}

	/**
	 * Update Facebook pages settings.
	 *
	 * @param array $settings The settings.
	 * @return bool
	 */
	public function update_facebook_pages_settings( $settings ) {
		return automator_update_option( self::PAGES_OPTION_KEY, $settings );
	}

	/**
	 * Get the pages option key.
	 *
	 * @return string
	 */
	public function get_pages_option_key() {
		return self::PAGES_OPTION_KEY;
	}

	/**
	 * Get the credentials option key.
	 *
	 * @return string
	 */
	public function get_credentials_option_key() {
		return self::CREDENTIALS_OPTION_KEY;
	}

	///////////////////////////////////////////////////////////////////
	// Shared API operations for Facebook and Instagram settings.
	///////////////////////////////////////////////////////////////////

	/**
	 * Fetch user information from the API.
	 *
	 * @param object $api        The API caller instance.
	 * @param string $fb_user_id The Facebook user ID.
	 *
	 * @return array The user info array.
	 */
	private function fetch_user_info( $api, $fb_user_id ) {
		$body = array(
			'action'  => 'get_user',
			'user_id' => $fb_user_id,
		);

		try {
			$response = $api->api_request( $body, null, array( 'exclude_error_check' => true ) );
			$data     = $response['data'] ?? array();

			return array(
				'user_id' => $data['id'] ?? '',
				'name'    => $data['name'] ?? '',
				'picture' => $data['picture']['data']['url'] ?? '',
			);
		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * Fetch and store linked pages after OAuth connection.
	 *
	 * Called by both Facebook and Instagram settings after successful OAuth.
	 * Fetches pages from the API and stores them locally.
	 *
	 * @param object $api The API caller instance.
	 *
	 * @return array The pages array.
	 * @throws Exception If pages cannot be fetched.
	 */
	public function fetch_and_store_linked_pages( $api ) {
		$pages = $this->fetch_linked_pages( $api );

		if ( ! empty( $pages ) ) {
			$this->update_facebook_pages_settings( $pages );
		}

		return $pages;
	}

	/**
	 * Fetch linked pages from Facebook API.
	 *
	 * @param object $api The API caller instance.
	 *
	 * @return array The pages array.
	 * @throws Exception If pages cannot be fetched.
	 */
	private function fetch_linked_pages( $api ) {
		$response = $api->api_request( 'list-user-pages' );

		if ( 200 !== $response['statusCode'] ) {
			throw new Exception(
				esc_html_x( 'Invalid status code.', 'Facebook', 'uncanny-automator' ),
				absint( $response['statusCode'] )
			);
		}

		// Handle response that may contain objects (stdClass) at any level.
		$response_data = is_object( $response['data'] ) ? (array) $response['data'] : $response['data'];
		$data          = $response_data['data'] ?? array();

		if ( empty( $data ) ) {
			throw new Exception(
				esc_html_x(
					'No Facebook Pages were found linked to this account. Please click the button below to re-authenticate and ensure the correct pages and permissions are selected.',
					'Facebook',
					'uncanny-automator'
				)
			);
		}

		// API returns sanitized data: id, name.
		$pages = array();
		foreach ( $data as $page ) {
			$page    = is_object( $page ) ? (array) $page : $page;
			$pages[] = array(
				'value' => $page['id'],
				'text'  => $page['name'],
			);
		}

		return $pages;
	}

	/**
	 * Disconnect from Facebook/Instagram.
	 *
	 * Calls the API to remove vault entry and cleans up legacy data.
	 * Used by both Facebook and Instagram disconnect handlers.
	 *
	 * @param object $api The API caller instance.
	 *
	 * @return void
	 */
	public function disconnect( $api ) {
		// Call API to remove vault entry.
		try {
			$api->api_request( array( 'action' => 'disconnect' ) );
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Catch and continue.
		}

		// Clean up any legacy options.
		$this->maybe_delete_legacy_options();
	}

	/**
	 * Delete legacy option names if they exist.
	 *
	 * Ensures sensitive data from old option names is cleaned up
	 * after successful authorization or disconnect
	 * This is just a fallback incase migration issues occur.
	 *
	 * @return void
	 */
	private function maybe_delete_legacy_options() {
		automator_delete_option( '_uncannyowl_facebook_settings' );
		automator_delete_option( '_uncannyowl_facebook_pages_settings' );
	}

	///////////////////////////////////////////////////////////////////
	// Credential validation helpers.
	///////////////////////////////////////////////////////////////////

	/**
	 * Validate OAuth credentials from callback.
	 *
	 * Used by both Facebook and Instagram settings to validate credentials
	 * received from OAuth callback before storage.
	 *
	 * @param array $credentials The credentials from OAuth response.
	 *
	 * @return array The validated credentials.
	 * @throws Exception If credentials are invalid.
	 */
	public function validate_oauth_credentials( $credentials ) {
		if ( empty( $credentials['vault_signature'] ?? '' ) ) {
			throw new Exception(
				esc_html_x( 'Missing credentials', 'Facebook', 'uncanny-automator' )
			);
		}

		if ( empty( $credentials['fb_user_id'] ?? '' ) ) {
			throw new Exception(
				esc_html_x( 'Missing Facebook user ID in OAuth response.', 'Facebook', 'uncanny-automator' )
			);
		}

		return $credentials;
	}

	/**
	 * Authorize account after OAuth - fetch user info and linked pages.
	 *
	 * Called after credentials are stored. Fetches user info and linked pages
	 * so they're available before settings page renders.
	 *
	 * @param object $api The API caller instance.
	 *
	 * @return void
	 */
	public function authorize_account( $api ) {
		$credentials = $this->get_facebook_credentials();

		if ( empty( $credentials['fb_user_id'] ) ) {
			return;
		}

		// Fetch user info and update credentials.
		$user_info = $this->fetch_user_info( $api, $credentials['fb_user_id'] );

		if ( ! empty( $user_info ) ) {
			$credentials['user-info'] = $user_info;
			$this->update_facebook_credentials( $credentials );
		}

		// Fetch and store linked pages.
		try {
			$this->fetch_and_store_linked_pages( $api );
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Catch and continue.
		}

		// Clean up any legacy options.
		$this->maybe_delete_legacy_options();
	}

	/**
	 * Check if credentials are in vault format.
	 *
	 * @param array $credentials The credentials to check.
	 *
	 * @return bool True if vault format, false otherwise.
	 */
	public function is_vault_credentials( $credentials ) {
		return ! empty( $credentials['vault_signature'] ?? '' ) && ! empty( $credentials['fb_user_id'] ?? '' );
	}

	/**
	 * Prepare vault credentials for API request.
	 *
	 * @param array $credentials The vault credentials.
	 *
	 * @return string JSON encoded credentials.
	 * @throws Exception If credentials are not in vault format.
	 */
	public function prepare_vault_credentials( $credentials ) {
		if ( ! $this->is_vault_credentials( $credentials ) ) {
			throw new Exception(
				esc_html_x( 'Facebook is not connected. Please connect your account in Automator settings.', 'Facebook', 'uncanny-automator' ),
				403
			);
		}

		return wp_json_encode(
			array(
				'vault_signature' => $credentials['vault_signature'],
				'fb_user_id'      => $credentials['fb_user_id'],
			)
		);
	}
}
