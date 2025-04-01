<?php
namespace Uncanny_Automator\Integrations\Facebook_Lead_Ads\Utilities;

use Exception;
use WP_Error;

/**
 * Class Connections_Manager
 *
 * Manages connections for Facebook Lead Ads integration.
 *
 * This class provides methods to establish, verify, and disconnect connections
 * for Facebook Lead Ads, ensuring simple and robust management of credentials.
 *
 * @package Uncanny_Automator\Integrations\Facebook_Lead_Ads\Utilities
 */
class Connections_Manager {

	/**
	 * Holds an instance of the Credentials_Manager.
	 *
	 * @var Credentials_Manager
	 */
	protected $credentials_manager;

	/**
	 * Holds an instance of the Page_Connection_Verifier.
	 *
	 * @var Page_Connection_Verifier
	 */
	protected $page_connection_verifier;

	/**
	 * Constructor.
	 *
	 * Initializes the Connections_Manager class.
	 *
	 * @param Credentials_Manager      $credentials_manager      An instance of the credentials manager.
	 * @param Page_Connection_Verifier $page_connection_verifier An instance of the page connection verifier.
	 *
	 * @return void
	 */
	public function __construct( Credentials_Manager $credentials_manager, Page_Connection_Verifier $page_connection_verifier ) {
		$this->credentials_manager      = $credentials_manager;
		$this->page_connection_verifier = $page_connection_verifier;
	}

	/**
	 * Checks if there is an active connection.
	 *
	 * @return bool True if connected, false otherwise.
	 */
	public function has_connection() {
		return $this->credentials_manager->has_pages_credentials()
			&& $this->credentials_manager->has_user_credentials();
	}

	/**
	 * Disconnects the current connection by deleting stored credentials.
	 *
	 * @return bool True if the credentials were successfully deleted, false otherwise.
	 */
	public function disconnect() {
		return $this->credentials_manager->delete_credentials();
	}

	/**
	 * Connects by setting credentials.
	 *
	 * @param array $args The credentials to set for the connection.
	 *
	 * @return bool True if the credentials were successfully set, false otherwise.
	 */
	public function connect( array $args ) {
		if ( empty( $args ) || ! is_array( $args ) ) {
			return false;
		}

		return $this->credentials_manager->set_credentials( $args );
	}

	/**
	 * Verifies the connection with a remote server.
	 *
	 * @param string $url The URL to verify the connection against.
	 *
	 * @return mixed The result of the verification or a WP_Error on failure.
	 */
	public function verify_connection( $url ) {
		if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error( 'invalid_url', esc_html_x( 'The provided URL is invalid.', 'Facebook Lead Ads', 'uncanny-automator' ) );
		}

		$body = $this->prepare_body(
			array(
				'site_url' => $url,
				'action'   => 'verify_connection',
				'nonce'    => wp_create_nonce( 'wp_rest' ),
			)
		);

		try {
			$result = Client::send_request( $body );
		} catch ( Exception $e ) {
			return new WP_Error( 'connection_error', $e->getMessage() );
		}

		return $result;
	}

	/**
	 * Verifies the connection to a specific page.
	 *
	 * @param int|string $page_id The ID of the page to verify.
	 * @param string     $force   Optional. Whether to force the verification. Default 'false'.
	 *
	 * @return WP_Error|string WP_Error on failure, or 'Ready' string on success.
	 */
	public function verify_page_connection( $page_id, $force = 'false' ) {
		if ( empty( $page_id ) ) {
			return new WP_Error( 'invalid_page_id', esc_html_x( 'The provided page ID is invalid.', 'Facebook Lead Ads', 'uncanny-automator' ) );
		}

		$status = $this->page_connection_verifier->verify_page_connection( $page_id, $force );

		if ( is_wp_error( $status ) ) {
			return new WP_Error( 'connection_error', $status->get_error_message() );
		}

		return $status;
	}

	/**
	 * Prepares the request body for sending data to the remote server.
	 *
	 * @param array $body The request body data.
	 *
	 * @return array The prepared body data.
	 */
	public function prepare_body( $body ) {
		if ( $this->site_has_basic_auth() ) {
			$body['basic_auth_username'] = AUTOMATOR_FACEBOOK_LEAD_ADS_BASIC_AUTH_USERNAME;
			$body['basic_auth_password'] = AUTOMATOR_FACEBOOK_LEAD_ADS_BASIC_AUTH_PASSWORD;
		}

		return $body;
	}

	/**
	 * Checks if the site has basic authentication enabled.
	 *
	 * @return bool True if basic authentication is enabled, false otherwise.
	 */
	public function site_has_basic_auth() {
		return defined( 'AUTOMATOR_FACEBOOK_LEAD_ADS_BASIC_AUTH_USERNAME' )
			&& defined( 'AUTOMATOR_FACEBOOK_LEAD_ADS_BASIC_AUTH_PASSWORD' );
	}
}
