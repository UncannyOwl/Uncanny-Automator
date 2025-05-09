<?php
namespace Uncanny_Automator\Integrations\Facebook_Lead_Ads\Utilities;

/**
 * Class Credentials_Manager
 *
 * Manages credentials for Facebook Lead Ads integration.
 *
 * @package Uncanny_Automator\Integrations\Facebook_Lead_Ads\Utilities
 */
class Credentials_Manager {

	/**
	 * Option key for storing Facebook Lead Ads credentials.
	 *
	 * @var string
	 */
	protected const OPTION_KEY = 'automator_facebook_lead_ads_credentials';

	/**
	 * Default credentials structure.
	 *
	 * @var array
	 */
	protected $default_credentials = array(
		'user_access_token' => '',
	);

	/**
	 * Set credentials in the database.
	 *
	 * Deletes existing credentials and replaces them with the new ones.
	 *
	 * @param array $args {
	 *     Array of credentials to set.
	 *
	 *     @type string $user_access_token User access token.
	 * }
	 * @return void
	 * @throws \InvalidArgumentException If required keys are missing in $args.
	 */
	public function set_credentials( array $args ) {

		if ( empty( $args['user_access_token'] ) ) {
			throw new \InvalidArgumentException( 'User access token is required.' );
		}

		// Delete old credentials before saving new ones.
		$this->delete_credentials();

		automator_add_option( self::OPTION_KEY, $args );
	}

	/**
	 * Delete credentials from the database.
	 *
	 * @return void
	 */
	public function delete_credentials() {
		automator_delete_option( self::OPTION_KEY );
		delete_transient( Page_Connection_Verifier::TRANSIENT_KEY );
	}

	/**
	 * Get credentials from the database.
	 *
	 * If credentials are missing or invalid, the default credentials are returned.
	 *
	 * @return array Array of stored credentials.
	 */
	public function get_credentials() {

		$credentials = automator_get_option( self::OPTION_KEY );

		return is_array( $credentials ) ? $credentials : $this->default_credentials;
	}

	/**
	 * Get the user access token.
	 *
	 * @return string The user access token, or an empty string if not set.
	 */
	public function get_user_access_token() {

		$credentials = $this->get_credentials();

		return $credentials['user_access_token'] ?? '';
	}

	/**
	 * @return array
	 */
	public function get_pages_credentials() {
		$credentials = $this->get_credentials();
		return (array) $credentials['pages_access_tokens'] ?? array();
	}
	/**
	 * Get pages ids.
	 *
	 * @return mixed
	 */
	public function get_pages_ids() {

		$credentials         = $this->get_credentials();
		$pages_access_tokens = (array) $credentials['pages_access_tokens'] ?? array();

		return array_map(
			function ( $item ) {
				return array( 'page_id' => $item['id'] ) ?? 0;
			},
			$pages_access_tokens
		);
	}

	/**
	 * Retrieves the access token for a specific page ID.
	 *
	 * @param int $page_id The ID of the page to search for.
	 *
	 * @return string|null The access token if found, or null if the page ID does not exist.
	 */
	public function get_page_access_token( $page_id ) {

		$credentials = $this->get_credentials();

		$pages = (array) $credentials['pages_access_tokens'] ?? array();

		foreach ( $pages as $page ) {
			if ( isset( $page['id'] ) && absint( $page['id'] ) === absint( $page_id ) ) {
				return $page['access_token'];
			}
		}

		return '';
	}


	/**
	 * Check if a specific credential key exists.
	 *
	 * @param string $key The credential key to check for.
	 * @return bool True if the key exists, false otherwise.
	 */
	public function has_credential_key( $key ) {

		$credentials = $this->get_credentials();

		return isset( $credentials[ $key ] );
	}

	/**
	 * Check if user credentials are set.
	 *
	 * Specifically checks for the presence of the 'user_access_token' key.
	 *
	 * @return bool True if user credentials exist, false otherwise.
	 */
	public function has_user_credentials() {
		return $this->has_credential_key( 'user_access_token' );
	}

	/**
	 * Check if pages credentials are set.
	 *
	 * Specifically checks for the presence of the 'pages_access_tokens' key.
	 *
	 * @return bool True if pages credentials exist, false otherwise.
	 */
	public function has_pages_credentials() {
		return $this->has_credential_key( 'pages_access_tokens' );
	}
}
