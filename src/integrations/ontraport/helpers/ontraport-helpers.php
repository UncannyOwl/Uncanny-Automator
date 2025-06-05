<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Ontraport;

use Exception;
use Uncanny_Automator\Api_Server;
use Uncanny_Automator\Automator_Helpers_Recipe;
use WP_Error;

/**
 * Class Ontraport_Helpers
 *
 * @package Uncanny_Automator
 */
class Ontraport_Helpers {

	/**
	 * Settings tab id
	 *
	 * @var string|object
	 */
	public $settings_tab = 'ontraport';

	/**
	 * The public API edge.
	 *
	 * @var string
	 */
	const API_ENDPOINT = 'v2/ontraport';

	/**
	 * The nonce.
	 *
	 * @var string
	 */
	const NONCE = 'automator_ontraport_api_authentication';

	/**
	 * Credentials wp_options key.
	 *
	 * @var string
	 */
	const CREDENTIALS_KEY = 'automator_ontraport_is_connected';

	/**
	 * List campaigns handler.
	 *
	 * @return void
	 */
	public function list_campaigns_handler() {

		Automator()->utilities->verify_nonce();

		$options = array();
		$tags    = array();

		$is_success = false;

		try {

			$response = $this->api_request( 'get_tags', array(), null, true );
			$results  = $response['data']['data'] ?? array();

			foreach ( $results as $data ) {
				$tags[] = array(
					'text'  => $data['tag_name'] ?? '',
					'value' => $data['tag_id'] ?? '',
				);
			}

			$is_success = true;

		} catch ( Exception $e ) {
			$options['error'] = esc_html_x( 'API Exception: ', 'Ontraport', 'uncanny-automator' ) . $e->getMessage();
		}

		$options['success'] = $is_success;
		$options['options'] = $tags;

		wp_send_json( $options );
	}

	/**
	 * Handler for listing tags.
	 *
	 * @return void
	 */
	public function list_tags_handler() {

		Automator()->utilities->verify_nonce();

		$options = array();
		$tags    = array();

		$is_success = false;

		try {

			$response = $this->api_request( 'get_tags', array(), null, true );
			$results  = $response['data']['data'] ?? array();

			foreach ( $results as $data ) {
				$tags[] = array(
					'text'  => $data['tag_name'] ?? '',
					'value' => $data['tag_id'] ?? '',
				);
			}

			$is_success = true;

		} catch ( Exception $e ) {
			$options['error'] = esc_html_x( 'API Exception: ', 'Ontraport', 'uncanny-automator' ) . $e->getMessage();
		}

		$options['success'] = $is_success;
		$options['options'] = $tags;

		wp_send_json( $options );
	}

	/**
	 * Get settings page url.
	 *
	 * @return string
	 */
	public function get_settings_page_url() {
		return add_query_arg(
			array(
				'post_type'   => 'uo-recipe',
				'page'        => 'uncanny-automator-config',
				'tab'         => 'premium-integrations',
				'integration' => $this->settings_tab,
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Create and retrieve a disconnect url for Ontraport Integration.
	 *
	 * @return string The disconnect uri.
	 */
	public static function get_disconnect_url() {

		return add_query_arg(
			array(
				'action' => 'automator_ontraport_disconnect_account',
				'nonce'  => wp_create_nonce( self::NONCE ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * Check the credentials for Ontraport integration.
	 *
	 * @param string $key The API Key.
	 * @param string $id The App ID.
	 *
	 * @return WP_Error|null Returns a WP_Error object if there is an exception, otherwise returns null.
	 */
	public function check_credentials( $key, $id ) {

		$body = array(
			'key' => $key,
			'id'  => $id,
		);

		try {

			$response = $this->api_request( 'check_credentials', $body, null, false );
			$result   = strtolower( $response['data']['result'] );

			if ( false !== strpos( $result, 'do not authenticate' ) ) {
				throw new Exception( 'Please double-check your API Key and App ID', 400 );
			}
		} catch ( Exception $e ) {
			return new WP_Error( $e->getCode(), $e->getMessage() );
		}
	}

	/**
	 * Retrieve the credentials from the options table.
	 *
	 * @return array
	 */
	public static function get_credentials() {
		return automator_get_option( self::CREDENTIALS_KEY, false );
	}

	/**
	 * Integration status.
	 *
	 * @return string
	 */
	public function integration_status() {
		$is_connected = automator_get_option( self::CREDENTIALS_KEY, false );
		return false !== $is_connected && is_numeric( $is_connected );
	}

	/**
	 * Disconnect Ontraport integration.
	 *
	 * @return void
	 */
	public function disconnect() {

		// Nonce verification.
		if ( ! wp_verify_nonce( filter_input( INPUT_GET, 'nonce', FILTER_UNSAFE_RAW ), self::NONCE ) ) {
			wp_die( esc_html_x( 'Nonce Verification Failed', 'Ontraport', 'uncanny-automator' ) );
		}

		// Current user check.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html_x( 'Unauthorized', 'Ontraport', 'uncanny-automator' ) );
		}

		// Remove credentials.
		self::remove_credentials();

		// Redirect to settings page.
		wp_safe_redirect( $this->get_settings_page_url() );

		exit;
	}

	/**
	 * Remove credentials.
	 *
	 * @return void
	 */
	public static function remove_credentials() {
		automator_delete_option( self::CREDENTIALS_KEY );
		automator_delete_option( Ontraport_Settings::OPT_API_KEY );
		automator_delete_option( Ontraport_Settings::OPT_APP_ID_KEY );
	}

	/**
	 * Make API request.
	 *
	 * @param  string $action
	 * @param  mixed $body
	 * @param  mixed $action_data
	 * @param  bool $check_for_errors
	 * @return array
	 */
	public function api_request( $action, $body = null, $action_data = null, $check_for_errors = true ) {

		$body           = is_array( $body ) ? $body : array();
		$body['action'] = $action;

		$this->attach_credentials( $body );

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => $action_data,
		);

		$response = Api_Server::api_call( $params );

		if ( $check_for_errors ) {
			$this->check_for_errors( $response );
		}

		return $response;
	}

	/**
	 * Attach the credentials to the given variable by reference.
	 *
	 * @param array $body - Pass value by reference.
	 *
	 * @return array
	 */
	protected function attach_credentials( &$body ) {

		$body['key'] = automator_get_option( Ontraport_Settings::OPT_API_KEY, '' );
		$body['id']  = automator_get_option( Ontraport_Settings::OPT_APP_ID_KEY, '' );

		return $body;
	}

	/**
	 * Check response for errors.
	 *
	 * @param  mixed $response
	 *
	 * @return void
	 */
	public function check_for_errors( $response ) {

		$status = absint( $response['statusCode'] ?? 0 );

		if ( ! in_array( $status, array( 200, 201 ), true ) ) {

			$message = isset( $response['data']['message'] )
				? $response['data']['message']
				// translators: 1: Status code
				: sprintf( esc_html_x( 'Ontraport API error: Received unexpected status code %1$s.', 'Ontraport', 'uncanny-automator' ), $status );

			throw new \Exception( esc_html( $message ), absint( $status ) );

		}
	}

	/**
	 * @return string
	 */
	public static function get_authorization_url() {
		return add_query_arg(
			array(
				'action'     => 'authorize',
				'user_url'   => rawurlencode( get_bloginfo( 'url' ) ),
				'nonce'      => wp_create_nonce( self::NONCE ),
				'plugin_ver' => AUTOMATOR_PLUGIN_VERSION,
			),
			AUTOMATOR_API_URL . 'v2/ontraport'
		);
	}
}
