<?php
namespace Uncanny_Automator\Integrations\Mautic;

use Uncanny_Automator\Api_Server;

class Mautic_Helpers {

	/**
	 * Creates settings page.
	 *
	 * @return void
	 */
	public function __construct() {

		// The `current_screen` is not available in the options.php so we check if its a settings page submissions.
		if ( ! empty( filter_input( INPUT_POST, 'automator_mautic_credentials' ) ) && is_admin() && current_user_can( 'manage_options' ) ) {
			$this->create_settings();
		}

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
			'endpoint' => 'v2/mautic',
			'body'     => $body,
			'action'   => $action_data,
		);

		$response = Api_Server::api_call( $payload );

		if ( 200 !== $response['statusCode'] ) {
			if ( isset( $response['data']['errors'] ) ) {
				$error = wp_json_encode( $response['data']['errors'] );
				if ( false === $error ) {
					$error = 'API has returned an error with unknown format';
				}
				throw new \Exception( $error, $response['statusCode'] );
			}
		}

		return $response;

	}

	/**
	 * Fetches all segments.
	 *
	 * @return void
	 */
	public function segments_fetch() {

		$segments = array();

		try {

			$client_auth = new Mautic_Client_Auth( Api_Server::get_instance() );

			$response = (array) $this->api_call(
				array(
					'action'      => 'segment_list',
					'credentials' => $client_auth->get_credentials(),
				),
				null
			);

			$response_data = isset( $response['data'] ) ? (array) $response['data'] : array();

			if ( ! isset( $response_data['lists'] ) ) {
				throw new \Exception( 'Invalid response format', 421 );
			}

			$lists = (array) $response_data['lists'];

			foreach ( $lists as $list ) {
				if ( ! is_array( $list ) || ! isset( $list['id'] ) || ! isset( $list['name'] ) ) {
					continue;
				}
				$segments[] = array(
					'value' => $list['id'],
					'text'  => $list['name'],
				);
			}
			// Pushed errors below.
		} catch ( \Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => $e->getMessage(),
				)
			);
		}

		$segments = array(
			'success' => true,
			'options' => $segments,
		);

		wp_send_json( $segments );

	}

	/**
	 * Renders the contact fields. Prints json_encoded result and dies.
	 *
	 * @return void
	 */
	public function render_contact_fields() {

		try {

			$client_auth = new Mautic_Client_Auth( Api_Server::get_instance() );

			$body = array(
				'action'      => 'contact_fields',
				'credentials' => $client_auth->get_credentials(),
			);

			$rows = array();

			$response = (array) $this->api_call( $body, null );

			$response_data = isset( $response['data'] ) ? (array) $response['data'] : array();

			if ( ! isset( $response_data['fields'] ) ) {
				throw new \Exception( 'Invalid response format', 421 );
			}

			$fields = (array) $response_data['fields'];

			foreach ( $fields as $field ) {
				$field = (array) $field;
				if ( 'email' === $field['alias'] ) {
					continue; // Skip email.
				}
				$rows[] = array(
					'ALIAS' => $field['alias'],
					'VALUE' => $field['defaultValue'],
				);
			}
		} catch ( \Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => $e->getMessage(),
				)
			);
		}

		$fields = array(
			'success' => true,
			'rows'    => $rows,
		);

		wp_send_json( $fields );

	}

	/**
	 * @param \WP_Screen $current_screen
	 *
	 * @return void
	 */
	public function register_settings( $current_screen ) {

		if ( ! is_admin() || 'uo-recipe_page_uncanny-automator-config' !== $current_screen->id ) {
			return;
		}

		$this->create_settings();

	}

	/**
	 * Redirects the user on disconnect with exit call.
	 *
	 * @return void
	 */
	public function disconnect_client() {

		if ( ! wp_verify_nonce( automator_filter_input( 'nonce' ), 'mautic-disconnect' ) ) {
			wp_die( 'Access forbidden. Invalid nonce', 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient access.', 401 );
		}

		( new Mautic_Client_Auth( Api_Server::get_instance() ) )->destroy_credentials();

		$referer = wp_get_referer();

		if ( false === $referer ) {
			$referer = admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations' );
		}

		wp_safe_redirect( $referer );

		exit;

	}

	/**
	 * @return Mautic_Settings
	 */
	protected function create_settings() {

		$client_auth = new Mautic_Client_Auth( Api_Server::get_instance() );
		$settings    = new Mautic_Settings( $this );

		$settings->set_client_auth( $client_auth );

		return $settings;

	}


}
