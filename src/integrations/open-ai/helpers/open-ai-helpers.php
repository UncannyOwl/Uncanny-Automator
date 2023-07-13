<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

use Uncanny_Automator\Api_Server;
use Uncanny_Automator\OpenAI\HTTP_Client;

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

	const HAS_GPT4_ACCESS_TRANSIENT_KEY = 'automator_openai_has_gpt4_access';

	/**
	 * Loads settings tab.
	 */
	public function __construct( $load_hooks = true ) {

		if ( $load_hooks && is_admin() ) {
			add_action( 'wp_ajax_automator_openai_disconnect', array( $this, 'disconnect' ) );
			add_action( 'wp_ajax_automator_openai_recheck_gpt4_access', array( $this, 'recheck_gpt4_access' ) );
			add_action( 'wp_ajax_automator_openai_get_models', array( $this, 'get_models' ) );
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

		delete_transient( self::HAS_GPT4_ACCESS_TRANSIENT_KEY );

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
		return ! empty( automator_get_option( self::OPTION_KEY, false ) );
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

	/**
	 * Retrieves available OpenAI models.
	 *
	 * @return void
	 */
	public function get_models() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient privilege', 403 );
		}

		require_once trailingslashit( ( dirname( __DIR__ ) ) ) . '/client/http-client.php';

		$client = new HTTP_Client( Api_Server::get_instance() );

		$client->set_endpoint( 'v1/models' );
		$client->set_api_key( (string) automator_get_option( 'automator_open_ai_secret', '' ) );

		try {

			$client->send_request( 'GET' );
			$response = $client->get_response();
			$models   = array_column( (array) $response['data'], 'id' );

			$gpt_models = array(
				'gpt-3.5-turbo',
				'gpt-3.5-turbo-0301',
				'gpt-4',
				'gpt-4-32k',
			);

			$available_models = array_intersect( $models, $gpt_models );

			asort( $available_models );

			$options_available_models = array();

			foreach ( $available_models as $available_model ) {
				$options_available_models[] = array(
					'value' => $available_model,
					'text'  => $available_model,
				);
			}

			echo wp_json_encode(
				array(
					'success' => true,
					'options' => $options_available_models,
				)
			);

		} catch ( \Exception $e ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'error'   => $e->getMessage(),
				)
			);
		}

		die();

	}

	/**
	 * Determine if the connected user has access to OpenAI's GPT-4 model.
	 *
	 * @return bool True if has access. Otherwise, false.
	 */
	public function has_gpt4_access() {

		return 'yes' === $this->determine_gpt4_access();

	}

	/**
	 * Recheck the GPT-4 access by clearing the transient.
	 *
	 * @return void
	 */
	public function recheck_gpt4_access() {

		if ( ! wp_verify_nonce( automator_filter_input( 'nonce' ), 'automator_openai_gpt4_check_access_clear' ) ) {
			wp_die( 'Invalid nonce', 401 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient privilege', 403 );
		}

		delete_transient( self::HAS_GPT4_ACCESS_TRANSIENT_KEY );

		wp_safe_redirect( admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations&integration=open-ai' ) );

		die;

	}

	/**
	 * Determine if the connected API key has access to GPT-4.
	 *
	 * @return string Returns 'yes' if the connected api key has access to GPT-4 API. Returns 'no' otherwise.
	 */
	public function determine_gpt4_access() {

		if ( ! $this->is_connected() ) {
			return false;
		}

		$transient_has_gpt4_access = get_transient( self::HAS_GPT4_ACCESS_TRANSIENT_KEY );

		if ( false !== $transient_has_gpt4_access ) {
			return $transient_has_gpt4_access;
		}

		require_once trailingslashit( ( dirname( __DIR__ ) ) ) . '/client/http-client.php';

		$client = new HTTP_Client( Api_Server::get_instance() );

		$client->set_endpoint( 'v1/models/gpt-4' );
		$client->set_api_key( (string) automator_get_option( 'automator_open_ai_secret', '' ) );

		$has_gpt4_access = 'no';

		try {

			$client->send_request( 'GET' );
			$response        = $client->get_response();
			$has_gpt4_access = ! empty( $response ) ? 'yes' : 'no';

		} catch ( \Exception $e ) {
			$has_gpt4_access = 'no';
		}

		// Persist the transient with no expiry to avoid multiple HTTP requests.
		// Disconnect and reconnect if access to GPT-4 API was granted, or recheck access in settings page.
		set_transient( self::HAS_GPT4_ACCESS_TRANSIENT_KEY, $has_gpt4_access, 0 );

		return $has_gpt4_access;

	}

	/**
	 * Build body payload for OpenAI specific actions.
	 *
	 * @param string $prompt
	 * @param string $default_model
	 * @param string $id
	 */
	public function build_body_payload( $prompt, $default_model, $id ) {

		$body = array(
			'model'    => apply_filters( $id . '_model', $default_model, $prompt ),
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
		);

		return apply_filters( $id . '_body', $body );

	}

	/**
	 * Build a generic chat completions client object.
	 *
	 * @param mixed[] $body
	 *
	 * @return HTTP_Client The client.
	 */
	public function build_chat_completions_client( $body ) {

		require_once dirname( __DIR__ ) . '/client/http-client.php';

		$client = new HTTP_Client( Api_Server::get_instance() );
		$client->set_endpoint( 'v1/chat/completions' );
		$client->set_api_key( (string) automator_get_option( 'automator_open_ai_secret', '' ) );
		$client->set_request_body( $body );

		return $client;

	}

	/**
	 * Process generic OpenAI chat completion actions.
	 *
	 * @param string $prompt
	 * @param string $model
	 * @param string $action_code
	 *
	 * @throws Exception
	 *
	 * @return string The response text.
	 */
	public function process_openai_chat_completions( $prompt, $model, $action_code ) {

		$body = $this->build_body_payload( $prompt, $model, strtolower( $action_code ) );

		$client = $this->build_chat_completions_client( $body );

		// Send the request to OpenAI.
		$client->send_request();

		// Retrieve the response text.
		$response_text = $client->get_first_response_string( $client->get_response() );

		return $response_text;

	}

}
