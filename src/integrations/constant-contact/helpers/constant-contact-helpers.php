<?php

namespace Uncanny_Automator\Integrations\Constant_Contact;

use Exception;
use Uncanny_Automator\Api_Server;
use Uncanny_Automator\Automator_Helpers_Recipe;

/**
 * Class Constant_Contact_Helpers
 *
 * @package Uncanny_Automator
 */
class Constant_Contact_Helpers {

	/**
	 * The helpers options object.
	 *
	 * @var string|object
	 */
	public $options = '';

	/**
	 * Settings tab id
	 *
	 * @var string|object
	 */
	public $settings_tab = 'constant-contact';

	/**
	 * The public API edge.
	 *
	 * @var string
	 */
	const API_ENDPOINT = 'v2/constant-contact';

	/**
	 * The nonce.
	 *
	 * @var string
	 */
	const NONCE = 'automator_constant_contact_api_authentication';

	/**
	 * @var string
	 */
	const OPTION_KEY = 'automator_contant_contact_integration_credentials';

	/**
	 * @var string
	 */
	const TRANSIENT_ACCOUNT_INFO = 'automator_constant_contact_account_info';

	/**
	 * @var string
	 */
	const TRANSIENT_ACCESS_TOKEN_REFRESH = 'automator_constant_contact_token_refresh';

	/**
	 * Retrieves the authorization URL.
	 *
	 * @return string
	 */
	public static function get_authorization_url() {

		$nonce = wp_create_nonce( self::NONCE );

		return add_query_arg(
			array(
				'action'  => 'authorization',
				'nonce'   => $nonce,
				'wp_site' => rawurlencode( admin_url( 'admin-ajax.php?action=automator_constant_contact_handle_credentials&nonce=' . $nonce ) ),
			),
			trailingslashit( AUTOMATOR_API_URL ) . self::API_ENDPOINT
		);

	}

	/**
	 * Fetches the user info.
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	public function fetch_user_info() {

		if ( false === $this->is_connected() ) {
			return;
		}

		$cached_user_info = get_transient( self::TRANSIENT_ACCOUNT_INFO );

		if ( false !== $cached_user_info ) {
			return $cached_user_info;
		}

		$credentials = $this->get_credentials();

		$body = array(
			'access_token' => $credentials['access_token'],
			'action'       => 'get_user_info',
		);

		try {

			$response = $this->api_request( $body, null );
			set_transient( self::TRANSIENT_ACCOUNT_INFO, $response['data'], DAY_IN_SECONDS );

		} catch ( \Exception $e ) {
			?>
			<uo-alert class="uap-spacing-bottom" heading="<?php echo esc_html_x( 'An error has occured while fetching the connected account information.', 'Constant Contact', 'uncanny-automator' ); ?>" type="warning">
				<?php echo esc_html( $e->getMessage() ); ?>
			</uo-alert>
			<?php
		}

	}

	/**
	 * Handles credentials storing.
	 *
	 * @return void
	 */
	public function handle_credentials_callback() {

		$message = automator_filter_input( 'automator_api_message' );
		$secret  = automator_filter_input( 'nonce' );

		$decoded = Automator_Helpers_Recipe::automator_api_decode_message( $message, $secret );

		$this->handle_invalid_access( $secret );

		if ( false !== $decoded ) {
			add_option( self::OPTION_KEY, $decoded );
			$this->redirect(
				array(
					'success' => 'yes',
				)
			);
		}

		$this->redirect(
			array(
				'error'             => automator_filter_input( 'error' ),
				'error_description' => automator_filter_input( 'error_description' ),
			)
		);
	}

	/**
	 * Fetches the custom fields list.
	 */
	public function contact_contact_fields_get() {

		$rows = array();

		$credentials = $this->get_credentials();

		$access_token = $credentials['access_token'];

		try {

			$body = array(
				'access_token' => $access_token,
				'action'       => 'contact_fields_get',
			);

			$response = $this->api_request( $body, null );

			if ( isset( $response['data']['custom_fields'] ) && is_array( $response['data']['custom_fields'] ) ) {
				foreach ( $response['data']['custom_fields'] as $field ) {
					$rows[] = array(
						'CUSTOM_FIELD_ID'    => $field['custom_field_id'],
						'CUSTOM_FIELD_NAME'  => $field['name'],
						'CUSTOM_FIELD_VALUE' => '',
					);
				}
			}
			// Otherwise, send an error.
		} catch ( Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'message' => $e->getMessage(),
				)
			);
		}

		$response = array(
			'success' => true,
			'rows'    => $rows,
		);

		wp_send_json( $response );

	}

	/**
	 * Callback method to the rest "wp_ajax_automator_constant_contact_tag_get" action hook.
	 *
	 * @return void
	 */
	public function tag_list() {

		$options = array();

		$credentials = $this->get_credentials();

		$access_token = $credentials['access_token'];

		try {

			$body = array(
				'access_token' => $access_token,
				'action'       => 'list_tags',
			);

			$response = $this->api_request( $body, null );

			if ( isset( $response['data']['tags'] ) && is_array( $response['data']['tags'] ) ) {
				foreach ( $response['data']['tags'] as $list ) {
					$options[] = array(
						'value' => $list['tag_id'],
						'text'  => $list['name'],
					);
				}
			}
			// Otherwise, send an error.
		} catch ( Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'message' => $e->getMessage(),
				)
			);
		}

		$response = array(
			'success' => true,
			'options' => $options,
		);

		wp_send_json( $response );

	}

	/**
	 * Fetches memberships lists.
	 *
	 * @return void
	 */
	public function list_memberships_get() {

		$options = array();

		$credentials = $this->get_credentials();

		$access_token = $credentials['access_token'];

		try {

			$body = array(
				'access_token' => $access_token,
				'action'       => 'list_memberships_get',
			);

			$response = $this->api_request( $body, null );

			if ( isset( $response['data']['lists'] ) && is_array( $response['data']['lists'] ) ) {
				foreach ( $response['data']['lists'] as $list ) {
					$options[] = array(
						'value' => $list['list_id'],
						'text'  => $list['name'],
					);
				}
			}
			// Otherwise, send an error.
		} catch ( Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'message' => $e->getMessage(),
				)
			);
		}

		$response = array(
			'success' => true,
			'options' => $options,
		);

		wp_send_json( $response );
	}

	/**
	 * Handles invalid access.
	 *
	 * Redirects on error. Invokes wp_die.
	 *
	 * @param string $nonce The nonce value.
	 *
	 * @return void
	 */
	public function handle_invalid_access( $nonce ) {

		if ( ! wp_verify_nonce( $nonce, self::NONCE ) || ! current_user_can( 'manage_options' ) ) {
			$this->redirect(
				array(
					'error'             => 'Unauthorized.',
					'error_description' => 'Invalid nonce.',
				)
			);
		}

	}

	/**
	 * Invokes wp_die statement.
	 *
	 * @param array $query_params
	 *
	 * @return void
	 */
	public function redirect( $query_params = array() ) {

		wp_safe_redirect(
			add_query_arg( $query_params, $this->get_settings_page_url() ),
			302
		);

		die;

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
	 * Integration status.
	 *
	 * @return string
	 */
	public function integration_status() {

		$credentials = get_option( self::OPTION_KEY, false );

		return ! empty( $credentials ) ? 'success' : '';

	}

	/**
	 * Determine whether the user is connected or not.
	 *
	 * @return bool
	 */
	public function is_connected() {

		return '' !== $this->integration_status();

	}

	/**
	 * Create and retrieve a disconnect url for Constant Contact Integration.
	 *
	 * @return string The disconnect uri.
	 */
	public function get_disconnect_url() {

		return add_query_arg(
			array(
				'action' => 'automator_constant_contact_handle_disconnect',
				'nonce'  => wp_create_nonce( self::NONCE ),
			),
			admin_url( 'admin-ajax.php' )
		);

	}

	/**
	 * Disconnect Constant Contact integration.
	 *
	 * @return void
	 */
	public function handle_disconnect() {

		$this->handle_invalid_access( automator_filter_input( 'nonce' ) );

		$this->disconnect();

		$this->redirect(
			array(
				'disconnected' => 'yes',
			)
		);

	}

	/**
	 * Remove credentials.
	 *
	 * @return void
	 */
	public function disconnect() {
		delete_option( self::OPTION_KEY );
	}

	/**
	 * Make API request.
	 *
	 * @param string $action
	 * @param array{access_token:string,...:mixed}  $body
	 * @param mixed  $action_data
	 * @param bool   $check_for_errors
	 *
	 * @return array
	 */
	public function api_request( $body = array(), $action_data = null ) {

		$this->attempt_access_token_refresh( $body['access_token'] );

		$body = is_array( $body ) ? $body : array();

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => $action_data,
		);

		$response = Api_Server::api_call( $params );

		$this->check_for_errors( $response );

		return $response;

	}

	/**
	 * Attempts to refresh the access token if its already expired.
	 *
	 * @return false|string|void The new access token when refreshed. Otherwise, returns false. Redirects if there is a client connection.
	 */
	public function attempt_access_token_refresh() {

		// Constant contact access token expiration is 24 hours.
		// Use transients to simplify the process.
		$has_refresh_access_token = get_transient( self::TRANSIENT_ACCESS_TOKEN_REFRESH );

		if ( false === $has_refresh_access_token ) {

			$credentials = $this->get_credentials();

			$body = array(
				'access_token'  => $credentials['access_token'],
				'refresh_token' => $credentials['refresh_token'],
				'action'        => 'refresh_access_token',
			);

			$params = array(
				'endpoint' => self::API_ENDPOINT,
				'body'     => $body,
				'action'   => null,
			);

			try {

				$response = Api_Server::api_call( $params );

				if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
					update_option( self::OPTION_KEY, $response['data'], true );
					set_transient( self::TRANSIENT_ACCESS_TOKEN_REFRESH, true, DAY_IN_SECONDS );
				}
			} catch ( \Exception $e ) {

				// Show an error message directly for easier debugging.
				if ( 'automator_constant_contact_settings_before' === current_action() ) {
					echo '<uo-alert type="error" class="uap-spacing-bottom" heading="Error exception.">' . esc_html( $e->getMessage() ) . '</uo-alert>';
				}

				automator_log( $e->getMessage(), 'An exception has occured', true, 'constant-contact-attempt-refresh-token' );

			}
		}

		return false;

	}

	/**
	 * Retrieves the credentials from the database.
	 *
	 * @return mixed[array{token_type:string,expires_in:int,access_token:string,scope:string,refresh_token:string}] array|false
	 */
	public function get_credentials() {

		$defaults = array(
			'token_type'    => '',
			'expires_in'    => 0,
			'access_token'  => '',
			'scope'         => '',
			'refresh_token' => '',
		);

		return wp_parse_args(
			(array) get_option( self::OPTION_KEY ),
			$defaults
		);

	}

	/**
	 * Check response for errors.
	 *
	 * @param mixed $response
	 *
	 * @return void
	 */
	public function check_for_errors( $response ) {

		if ( ! empty( $response['data']['error_key'] ) ) {
			$message = sprintf( '[%s] &mdash; %s', $response['data']['error_key'], $response['data']['error_message'] );
			throw new \Exception( $message, $response['statusCode'] );
		}

		if ( ! in_array( $response['statusCode'], array( 200, 201, 204 ), true ) ) {

			$error_message = sprintf(
				'Constant Contant has responded with status code %d' . PHP_EOL . '%s',
				$response['statusCode'],
				wp_json_encode( $response['data'] )
			);

			throw new \Exception( $error_message );

		}

	}

}
