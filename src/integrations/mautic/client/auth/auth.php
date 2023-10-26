<?php
namespace Uncanny_Automator\Integrations\Mautic;

use Uncanny_Automator\Api_Server as Automator_Client;

/**
 *
 * Class Mautic_Client_Auth
 *
 * @package Uncanny_Automator\Integrations\Mautic
 */
class Mautic_Client_Auth {

	/**
	 * @var string
	 */
	protected $credentials_submitted = '';

	/**
	 * @var Automator_Client
	 */
	protected $client = null;

	/**
	 * @param Automator_Client $client
	 *
	 * @return void
	 */
	public function __construct( Automator_Client $client ) {

		$this->client = $client;

	}

	/**
	 * @return Automator_Client
	 */
	public function get_client() {
		return $this->client;
	}

	/**
	 * @param string $credentials_submitted
	 *
	 * @return self
	 */
	public function set_credentials_submitted( $credentials_submitted ) {

		$this->credentials_submitted = $credentials_submitted;

		return $this;

	}

	/**
	 * Validates the credentials. Invokes wp_die with 401 as status.
	 *
	 * @param string $sanitized_input
	 * @param string $option_name
	 * @param string $original_input
	 *
	 * @return string|false
	 */
	public function validate_credentials( $sanitized_input, $option_name, $original_input ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient previlege', 401 );
		}

		$cache_key   = $option_name . '_validated_api_key';
		$cache_group = 'mautic_credentials_field';

		// Ensures run once per run-time.
		if ( wp_cache_get( $cache_key, $cache_group ) ) {
			return $sanitized_input;
		}

		// Set the run-time cache before a request is run.
		wp_cache_set( $cache_key, true, $cache_group );

		try {

			$resource_owner = $this->set_credentials_submitted( $original_input )
				->get_resource_owner();

			update_option( 'automator_mautic_resource_owner', wp_json_encode( $resource_owner['data'] ), false );

			return $sanitized_input;

		} catch ( \Exception $e ) {

			add_settings_error(
				'automator_mautic_connection_alerts',
				_x( 'Credentials verification failed', 'Mautic', 'uncanny-automator' ),
				$e->getCode() . ': ' . $e->getMessage(),
				'error'
			);

			return false;

		}

	}

	/**
	 * @throws \Exception If credentials are empty.
	 *
	 * @return string
	 */
	public function get_credentials() {

		$credentials = get_option( 'automator_mautic_credentials', null );

		// Must be a JSON string.
		if ( ! is_string( $credentials ) ) {
			throw new \Exception( 'Invalid credentials format', 500 );
		}

		if ( empty( $credentials ) ) {
			throw new \Exception( 'Credentials empty', 500 );
		}

		return $credentials;

	}

	/**
	 * @return mixed[]
	 */
	public function get_resource_owner() {

		$body = array(
			'action'      => 'validate_credentials',
			'credentials' => $this->credentials_submitted,
		);

		$response = $this->api_call( $body );

		return $response;

	}

	/**
	 * Remove all related credentials.
	 *
	 * @return bool True, always.
	 */
	public function destroy_credentials() {

		delete_option( 'automator_mautic_base_url' );
		delete_option( 'automator_mautic_username' );
		delete_option( 'automator_mautic_password' );
		delete_option( 'automator_mautic_credentials' );
		delete_option( 'automator_mautic_resource_owner' );

		return true;

	}

	/**
	 * @param mixed[] $body
	 * @param mixed[] $action_data
	 *
	 * @return mixed[]
	 */
	public function api_call( $body = array(), $action_data = null ) {

		$payload = array(
			'endpoint' => 'v2/mautic',
			'body'     => $body,
			'action'   => $action_data,
		);

		$client = $this->get_client();

		$response = $client::api_call( $payload );

		if ( 200 !== $response['statusCode'] ) {
			if ( isset( $response['data']['errors'] ) ) {
				$error = wp_json_encode( $response['data']['errors'] );
				if ( false === $error ) {
					$error = 'An error with unknown format has been returned';
				}
				throw new \Exception( $error, $response['statusCode'] );
			}
		}

		return $response;

	}

}
