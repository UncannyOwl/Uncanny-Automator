<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

use Uncanny_Automator\Api_Server;

/**
 * Class Open_AI_Helpers
 *
 * @package Uncanny_Automator
 */
class Open_AI_Helpers {

	/**
	 * The API endpoint address.
	 *
	 * @var API_ENDPOINT The endpoint adress.
	 */
	const API_ENDPOINT = 'v2/open-ai';

	const OPTION_KEY = 'automator_open_ai_secret';

	/**
	 * Loads settings tab.
	 */
	public function __construct( $load_hooks = true ) {

		if ( $load_hooks && is_admin() ) {
			add_action( 'wp_ajax_automator_openai_disconnect', array( $this, 'disconnect' ) );
		}

		if ( is_admin() ) {
			$this->load_settings();
		}

	}

	public function load_settings() {
		// Load the settings page.
		require_once __DIR__ . '/../settings/settings-open-ai.php';

		new Open_AI_Settings( $this );
	}

	/**
	 * Removes all option. Automatically disconnects the account.
	 */
	public function disconnect() {

		$this->verify_access( automator_filter_input( 'nonce' ), 'automator_openai_disconnect' );

		delete_option( self::OPTION_KEY );

		wp_safe_redirect( admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations&integration=open-ai' );

		exit;

	}

	/**
	 * Method api_request
	 *
	 * @param $params
	 *
	 * @return void
	 */
	public function api_request( $body, $action = null ) {

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => $action,
			'timeout'  => 60,
		);

		$response = Api_Server::api_call( $params );

		$this->check_for_errors( $response );

		return $response;

	}

	/**
	 * Handle common errors.
	 *
	 * @param array $response The response.
	 *
	 * @throws \Exception
	 *
	 * @return void
	 */
	public function check_for_errors( $response = array() ) {

		if ( 200 !== $response['statusCode'] ) {
			throw new \Exception( 'Request to OpenAI returned with status: ' . $response['statusCode'], $response['statusCode'] );
		}

	}

	/**
	 * Determine whether the user is connected or not.
	 *
	 * @return bool True if there is an option key. Otherwise, false.
	 */
	public function is_connected() {
		return ! empty( get_option( self::OPTION_KEY, false ) );
	}

	/**
	 * Verifies nonce validity and current user's ability to manage options.
	 *
	 * @return void
	 */
	public function verify_access( $nonce = '', $action = '' ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized', 403 );
		}

		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			wp_die( 'Forbidden', 401 );
		}

	}
}
