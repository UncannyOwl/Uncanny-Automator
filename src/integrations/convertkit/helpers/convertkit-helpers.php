<?php
namespace Uncanny_Automator;

/**
 * Contains the object for interacting with ConvertKit integrations
 *
 * @package Uncanny_Automator
 * @since 4.9
 */
class ConvertKit_Helpers {

	/**
	 * ConvertKit API Endpoint
	 *
	 * @var string The endpoint.
	 */
	const API_ENDPOINT = 'v2/convertkit';

	/**
	 * Constructor.
	 *
	 * @param boolean $load_hooks Pass true to load the hooks. Otherwise, pass false.
	 *
	 * @return void
	 */
	public function __construct( $load_hooks = true ) {

		if ( $load_hooks ) {

			// Disconnect handler.
			add_action( 'wp_ajax_automator_convertkit_disconnect', array( $this, 'disconnect' ) );

			// Forms wp-ajax dropdown handler.
			add_action( 'wp_ajax_automator_convertkit_forms_dropdown_handler', array( $this, 'handle_forms_dropdown' ) );

			// Sequence wp-ajax dropdown handler.
			add_action( 'wp_ajax_automator_convertkit_sequence_dropdown_handler', array( $this, 'handle_sequence_dropdown' ) );

			// List tags wp-ajax dropdown handler.
			add_action( 'wp_ajax_automator_convertkit_tags_dropdown_handler', array( $this, 'handle_tags_dropdown' ) );

		}

		require_once dirname( dirname( __FILE__ ) ) . '/settings/convertkit-settings.php';

		new ConvertKit_Settings( $this );

	}

	/**
	 * Method api_request
	 *
	 * @param array $body The request body.
	 * @param array $action_data The action data. Pass null if generic.
	 *
	 * @return void
	 */
	public function api_request( $body, $action_data = null ) {

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => $action_data,
			'timeout'  => 20,
		);

		$response = Api_Server::api_call( $params );

		$this->handle_errors( $response );

		return $response;

	}

	/**
	 * Handles common errors.
	 *
	 * @param array $response The response from the API.
	 *
	 * @throws \Exception.
	 */
	public function handle_errors( $response ) {

		if ( 200 !== $response['statusCode'] ) {

			if ( isset( $response['data']['error'] ) && isset( $response['data']['message'] ) ) {
				throw new \Exception(
					'ConvertKit API has responded with an error message: ' . implode( ' - ', array_values( $response['data'] ) ),
					$response['statusCode']
				);
			}

			// Throw generic error instead.
			throw new \Exception(
				'ConvertKit API has responded with unknow error. Result: ' . wp_json_encode( $response['data'] ),
				$response['statusCode']
			);

		}

	}

	/**
	 * Handles the dropdown option for the forms.
	 *
	 * @return void
	 */
	public function handle_forms_dropdown() {

		Automator()->utilities->ajax_auth_check();

		$option_values = array();

		try {

			$body = array(
				'action'       => 'list_forms',
				'access_token' => get_option( ConvertKit_Settings::OPTIONS_API_KEY, null ),
			);

			$response = $this->api_request( $body );

			if ( ! empty( $response['data']['forms'] ) ) {
				foreach ( $response['data']['forms'] as $form ) {
					$option_values[] = array(
						'value' => $form['id'],
						'text'  => $form['name'],
					);
				}
			}
		} catch ( \Exception $e ) {

			$option_values[] = array(
				'value' => 'Error: ' . $e->getCode(),
				'text'  => $e->getMessage(),
			);

		}

		wp_send_json( $option_values );

	}

	/**
	 * Handles the dropdown option for the sequence.
	 *
	 * @return void
	 */
	public function handle_sequence_dropdown() {

		Automator()->utilities->ajax_auth_check();

		$option_values = array();

		try {

			$body = array(
				'action'       => 'list_sequence',
				'access_token' => get_option( ConvertKit_Settings::OPTIONS_API_KEY, null ),
			);

			$response = $this->api_request( $body );

			if ( ! empty( $response['data']['courses'] ) ) {
				foreach ( $response['data']['courses'] as $sequence ) {
					$option_values[] = array(
						'value' => $sequence['id'],
						'text'  => $sequence['name'],
					);
				}
			}
		} catch ( \Exception $e ) {

			$option_values[] = array(
				'value' => 'Error: ' . $e->getCode(),
				'text'  => $e->getMessage(),
			);

		}

		wp_send_json( $option_values );

	}

	/**
	 * Handles the dropdown option for listing tags.
	 *
	 * @return void
	 */
	public function handle_tags_dropdown() {

		Automator()->utilities->ajax_auth_check();

		$option_values = array();

		try {

			$body = array(
				'action'       => 'list_tags',
				'access_token' => get_option( ConvertKit_Settings::OPTIONS_API_KEY, null ),
			);

			$response = $this->api_request( $body );

			if ( ! empty( $response['data']['tags'] ) ) {
				foreach ( $response['data']['tags'] as $tag ) {
					$option_values[] = array(
						'value' => $tag['id'],
						'text'  => $tag['name'],
					);
				}
			}
		} catch ( \Exception $e ) {

			$option_values[] = array(
				'value' => 'Error: ' . $e->getCode(),
				'text'  => $e->getMessage(),
			);

		}

		wp_send_json( $option_values );

	}

	/**
	 * Validates API Key.
	 *
	 * @param string $api_key
	 *
	 * @throws Exception
	 *
	 * @return array The response body.
	 */
	public function verify_api_key( $api_key ) {

		$response = wp_remote_get( 'https://api.convertkit.com/v3/forms?api_key=' . $api_key, array() );

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code ) {

			throw new \Exception( implode( ': ', array_values( $body ) ), $status_code );

		}

		return $body;

	}

	/**
	 * Validates API Secret.
	 *
	 * @param string $secret
	 *
	 * @throws Exception
	 *
	 * @return array The response body.
	 */
	public function verify_api_secret( $secret ) {

		$response = wp_remote_get( 'https://api.convertkit.com/v3/account?api_secret=' . $secret, array() );

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code ) {

			throw new \Exception( implode( ': ', array_values( $body ) ), $status_code );

		}

		return $body;

	}

	/**
	 * get_client
	 *
	 */
	public function get_client() {

		return get_option( 'automator_convertkit_client', null );

	}

	/**
	 * Retrieves a the disconnect URL.
	 *
	 * @return string The disconnect URL.
	 */
	public function get_disconnect_url() {

		return add_query_arg(
			array(
				'action' => 'automator_convertkit_disconnect',
				'nonce'  => wp_create_nonce( 'automator_convertkit_disconnect' ),
			),
			admin_url( 'admin-ajax.php' )
		);

	}

	/**
	 * Disconnects the connected user,
	 *
	 * Redirects the user.
	 *
	 * @return void
	 */
	public function disconnect() {

		if ( ! wp_verify_nonce( automator_filter_input( 'nonce' ), 'automator_convertkit_disconnect' ) ) {
			wp_die( 'Invalid nonce.', 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden.', 403 );
		}

		// Remove API KEY.
		delete_option( ConvertKit_Settings::OPTIONS_API_KEY );

		// Remove API SECRET.
		delete_option( ConvertKit_Settings::OPTIONS_API_SECRET );

		// Remove business account ID
		delete_option( ConvertKit_Settings::OPTIONS_CLIENT );

		wp_safe_redirect(
			add_query_arg(
				array( 'disconnected' => 'yes' ),
				$this->get_settings_url()
			)
		);

		die;

	}

	/**
	 * Retrieve the settings url.
	 *
	 * @return string The settings URL.
	 */
	public function get_settings_url() {

		return add_query_arg(
			array(
				'post_type'   => 'uo-recipe',
				'page'        => 'uncanny-automator-config',
				'tab'         => 'premium-integrations',
				'integration' => 'convertkit',
			),
			admin_url( 'edit.php' )
		);

	}


	/**
	 * Formats the time to WordPress' readable format with respect to timezone.
	 *
	 * @param string $datetime The datetime to format.
	 *
	 * @return string The time formatted. Returns empty string for invalid dates.
	 */
	public function get_formatted_time( $datetime ) {

		$date = new \DateTime( $datetime );

		$date->setTimezone( new \DateTimeZone( Automator()->get_timezone_string() ) );

		if ( false === $date ) {
			return '';
		}

		return $date->format( sprintf( '%s %s', get_option( 'date_format', 'F j, Y' ), get_option( 'time_format', 'g:i a' ) ) );

	}


}
