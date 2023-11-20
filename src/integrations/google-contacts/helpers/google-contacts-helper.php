<?php
namespace Uncanny_Automator\Integrations\Google_Contacts;

use Exception;
use Uncanny_Automator\Api_Server;
use Uncanny_Automator\Automator_Helpers_Recipe;

class Google_Contacts_Helpers {

	/**
	 * @var string OPTION_KEY
	 */
	const OPTION_KEY = 'automator_google_contacts_credentials';

	/**
	 * @var string AUTH_NONCE_KEY
	 */
	const AUTH_NONCE_KEY = 'automator_api_google_contacts_authorize';

	/**
	 * @var string AUTH_TRANSIENT_KEY
	 */
	const AUTH_TRANSIENT_KEY = 'automator_api_google_contacts_authorize_nonce';

	/**
	 * @var string RESOURCE_OWNER_KEY
	 */
	const RESOURCE_OWNER_KEY = 'automator_api_google_contacts_resource_owner_transient';

	/**
	 * Deletes the client credentials in the DB.
	 *
	 * @return void
	 */
	public function disconnect() {

		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( automator_filter_input( 'nonce' ), 'google-contacts-disconnect' ) ) {
			wp_die( 'Invalid nonce or insufficient privilege' );
		}

		self::clear_connection();

		wp_safe_redirect( $this->get_settings_page_url() );

		die;

	}

	/**
	 * Clears the connection data.
	 *
	 * @return true
	 */
	public static function clear_connection() {

		delete_option( self::OPTION_KEY );
		delete_transient( self::RESOURCE_OWNER_KEY );
		delete_transient( self::AUTH_TRANSIENT_KEY );

		return true;

	}

	/**
	 * Method automator_google_contacts_process_code_callback.
	 *
	 * A wp_ajax callback.
	 *
	 * @return void.
	 */
	public function automator_google_contacts_process_code_callback() {

		// Redirect if there are any errors.
		$this->auth_redirect_when_error(
			automator_filter_input( 'nonce' ),
			automator_filter_input( 'auth_error' )
		);

		// Persist connection if okay.
		$is_connected = $this->auth_persist_connection(
			automator_filter_input( 'automator_api_message' ),
			get_transient( self::AUTH_TRANSIENT_KEY )
		);

		if ( $is_connected ) {
			$this->redirect_with_success( 200 );
		}

		$this->redirect_with_error( 'generic_error' );

		wp_die();

	}

	/**
	 * Method auth_redirect_when_error
	 *
	 * Redirects if nonce is invalid, or if auth is successful.
	 *
	 * @param string $nonce The nonce.
	 * @param string $invoked_errors The displayed error message.
	 *
	 * @return void.
	 */
	protected function auth_redirect_when_error( $nonce = '', $invoked_errors = '' ) {

		// Check nonce.
		if ( ! wp_verify_nonce( $nonce, self::AUTH_NONCE_KEY ) ) {
			$this->redirect_with_error( 'invalid_nonce' );
		}

		// Redirect if there are any errors.
		if ( ! empty( $invoked_errors ) ) {
			$this->redirect_with_error( str_replace( ' ', '_', strtolower( rawurlencode( $invoked_errors ) ) ) );
		}

	}

	/**
	 * Method redirect_with_success.
	 *
	 * Redirects with a success message.
	 *
	 * @param string $success_message The success message.
	 *
	 * return void.
	 */
	public function redirect_with_success( $success_message = '' ) {

		wp_safe_redirect(
			$this->get_settings_page_url(
				array(
					'auth_success' => $success_message,
				)
			)
		);

		exit;
	}

	/**
	 * Method redirect_with_error.
	 *
	 * Redirects with an error message.
	 *
	 * @param string $error_message The error message.
	 *
	 * return void.
	 */
	public function redirect_with_error( $error_message = '' ) {

		wp_safe_redirect(
			$this->get_settings_page_url(
				array(
					'auth_error' => $error_message,
				)
			)
		);

		exit;

	}

	/**
	 * Method auth_persist_connection.
	 *
	 * Save the connection data to wp_options.
	 *
	 * @param string $api_message The secret message from the API.
	 * @param string $secret The secret token.
	 *
	 * @return boolean True if persists successfully. Otherwise, false.
	 */
	protected function auth_persist_connection( $api_message = '', $secret = '' ) {

		$tokens = Automator_Helpers_Recipe::automator_api_decode_message( $api_message, $secret );

		// Invalid message.
		if ( false !== $tokens ) {

			// Missing scopes.
			if ( $this->has_missing_scopes( $tokens ) ) {
				$this->redirect_with_error( esc_html__( 'missing_scope', 'uncanny-automator' ) );
			}

			self::clear_connection();

			add_option( self::OPTION_KEY, $tokens );

			return true;

		}

		return false;
	}

	/**
	 * Method has_missing_scopes.
	 *
	 * Checks if the user has missing scopes. Scopes are checked during OAuth consent screen.
	 *
	 * @param mixed $token The access token combination.
	 *
	 * @return boolean True if there are scopes missing. Otherwise, false.
	 */
	public function has_missing_scopes( $token ) {

		if ( ! isset( $token['scope'] ) || empty( $token['scope'] ) ) {
			return true;
		}

		$scopes = array(
			'https://www.googleapis.com/auth/contacts',
			'https://www.googleapis.com/auth/userinfo.profile',
			'https://www.googleapis.com/auth/userinfo.email',
		);

		$has_missing_scope = false;

		foreach ( $scopes as $scope ) {
			if ( false === strpos( $token['scope'], $scope ) ) {
				$has_missing_scope = true;
			}
		}

		return $has_missing_scope;

	}

	/**
	 * Method get_settings_page_url.
	 *
	 * Create and retrieve the settings page uri.
	 *
	 * @param array $params The url query parameters.
	 *
	 * @return string The Google Calendar settings page.
	 */
	public function get_settings_page_url( $params = array() ) {

		return add_query_arg(
			array_merge(
				array(
					'post_type'   => 'uo-recipe',
					'page'        => 'uncanny-automator-config',
					'tab'         => 'premium-integrations',
					'integration' => 'google-contacts',
				),
				$params
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Method get_disconnect_url.
	 *
	 * @return string Returns the wp_ajax disconnect url callback.
	 */
	public function get_disconnect_url() {

		return add_query_arg(
			array(
				'action' => 'automator_google_calendar_disconnect_user',
				'nonce'  => wp_create_nonce( 'automator-google-calendar-user-disconnect' ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * Method get_client.
	 *
	 * @return mixed The Google Client that was saved in wp_options (self::OPTION_KEY)
	 */
	public function get_client() {

		$creds = get_option( self::OPTION_KEY, array() );

		return $creds;

	}

	/**
	 *
	 * @return void|mixed[]
	 */
	public function request_resource_owner() {

		$client = $this->get_client();

		if ( empty( $client['scope'] ) ) {
			return;
		}

		$body = array(
			'action'       => 'user_info',
			'client'       => wp_json_encode( $client ),
			'access_token' => $this->get_client(),
		);

		return $this->api_call( $body, null );

	}

	/**
	 * Sends and HTTP Request to API Server.
	 *
	 * @param mixed[] $body
	 * @param mixed[] $action_data
	 *
	 * @throws \Exception
	 *
	 * @return mixed[]
	 */
	public function api_call( $body = array(), $action_data = null ) {

		$payload = array(
			'endpoint' => 'v2/google-contacts',
			'body'     => $body,
			'action'   => $action_data,
		);

		$response = Api_Server::api_call( $payload );

		if ( ! in_array( $response['statusCode'], array( 200, 201 ), true ) ) {
			throw new \Exception( wp_json_encode( $response ), $response['statusCode'] );
		}

		return $response;

	}

	/**
	 *
	 * @return void
	 */
	public function fetch_labels() {

		$options = array();

		try {

			$body = array(
				'action'       => 'list_labels',
				'access_token' => $this->get_client(),
			);

			$response = $this->api_call( $body );

			if ( isset( $response['data']['contactGroups'] ) && is_array( $response['data']['contactGroups'] ) ) {
				foreach ( $response['data']['contactGroups'] as $label ) {
					if ( 'USER_CONTACT_GROUP' === $label['groupType'] ) {
						$options[] = array(
							'text'  => $label['name'],
							'value' => $label['resourceName'],
						);
					}
				}
			}

			// Exception.
		} catch ( Exception $e ) {

			$response = array(
				'success' => false,
				'error'   => $e->getMessage(),
			);

			wp_send_json( $response );

		}

		$response = array(
			'success' => true,
			'options' => $options,
		);

		wp_send_json( $response );

	}

}
