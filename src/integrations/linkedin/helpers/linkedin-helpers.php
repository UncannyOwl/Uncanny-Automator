<?php
namespace Uncanny_Automator;

class Linkedin_Helpers {

	const API_ENDPOINT = 'v2/linkedin';

	const LINKEDIN_CLIENT = 'automator_linkedin_client';

	const LINKEDIN_CONNECTED_USER = 'automator_linkedin_connected_user';

	const N_DAYS_REFRESH_TOKEN_EXPIRE_NOTICE = 30;

	public function __construct( $hooks_loaded = true ) {

		if ( $hooks_loaded ) {

			// Capture OAuthentication credentials.
			add_action(
				'wp_ajax_automator_linkedin_capture_tokens',
				function() {
					$this->capture_tokens();
				}
			);

			add_action(
				'wp_ajax_automator_linkedin_disconnect',
				array( $this, 'disconnect' )
			);

			add_action(
				'wp_ajax_automator_linkedin_get_pages',
				array( $this, 'get_pages' )
			);

			// Add refresh token notice.
			add_action(
				'admin_init',
				array( $this, 'check_refresh_token_expiration' )
			);

		}

		require_once __DIR__ . '/../settings/settings-linkedin.php';

		new LinkedIn_Settings( $this );

	}

	private function capture_tokens() {

		$nonce = automator_filter_input( 'nonce' );

		$this->verify_access( $nonce );

		$tokens = Automator_Helpers_Recipe::automator_api_decode_message(
			automator_filter_input( 'automator_api_message' ),
			$nonce
		);

		if ( empty( $tokens ) ) {

			$this->redirect(
				$this->get_settings_url(),
				array(
					'status' => 'error',
					'code'   => 403,
				)
			);

		}

		// Manually set the access token and refresh token expiration date.
		$tokens['expires_on']               = strtotime( current_time( 'mysql' ) ) + $tokens['expires_in'];
		$tokens['refresh_token_expires_on'] = strtotime( current_time( 'mysql' ) ) + $tokens['refresh_token_expires_in'];

		update_option( self::LINKEDIN_CLIENT, $tokens, false );

		$this->set_connected_user( $this->get_client() );

		$this->redirect(
			$this->get_settings_url(),
			array(
				'status' => 'success',
				'code'   => 200,
			)
		);

	}

	public function get_client() {

		return automator_get_option( self::LINKEDIN_CLIENT );

	}

	public function get_pages() {

		Automator()->utilities->ajax_auth_check();

		$transient_data = get_transient( 'automator_linkedin_pages' );

		if ( false !== $transient_data ) {
			wp_send_json( $transient_data );
		}

		$pages = array();

		try {

			$body = array(
				'action'       => 'get_pages',
				'access_token' => $this->get_client()['access_token'],
			);

			$response = $this->api_call( $body, null );

			foreach ( (array) $response['data']['elements'] as $element ) {

				$pages[] = array(
					'text'  => $element['organization~']['localizedName'],
					'value' => $element['organization'],
				);

			}
		} catch ( \Exception $e ) {

			automator_log( $e->getMessage(), 'Linkedin_Helpers::get_pages', true, 'linkedin' );

			wp_send_json(
				array(
					array(
						'text'  => $e->getMessage(),
						'value' => $e->getCode(),
					),
				)
			);

		}

		if ( ! empty( $pages ) ) {

			set_transient( 'automator_linkedin_pages', $pages, MINUTE_IN_SECONDS * 5 );

		}

		if ( empty( $pages ) ) {

			wp_send_json(
				array(
					array(
						'text'  => 'Unable to find any LinkedIn page with administrative access. Please try again later.',
						'value' => 404,
					),
				)
			);

		}

		wp_send_json( $pages );

	}

	public function set_connected_user( $client ) {

		try {

			if ( ! empty( $this->get_connected_user()['id'] ) ) {
				return;
			}

			$body = array(
				'access_token' => $client['access_token'],
				'action'       => 'get_user',
			);

			$response = $this->api_call( $body, null );

			update_option( self::LINKEDIN_CONNECTED_USER, $response['data'], true );

		} catch ( \Exception $e ) {

			$this->redirect(
				$this->get_settings_url(),
				array(
					'status'  => 'error',
					'code'    => $e->getCode(),
					'message' => rawurlencode( $e->getMessage() ),
				)
			);

		}

	}

	public function get_connected_user() {

		$in_record = automator_get_option( self::LINKEDIN_CONNECTED_USER, array() );

		// Add some default so we don't have to check.
		$defaults = array(
			'localizedLastName'  => '',
			'localizedFirstName' => '',
			'id'                 => '',
		);

		return wp_parse_args( $in_record, $defaults );

	}

	public function redirect( $redirect_url = '', $args = array() ) {

		wp_safe_redirect( add_query_arg( $args, $redirect_url ) );

		exit;

	}

	public function get_settings_url() {

		return add_query_arg(
			array(
				'post_type'   => 'uo-recipe',
				'page'        => 'uncanny-automator-config',
				'tab'         => 'premium-integrations',
				'integration' => 'linkedin',
			),
			admin_url( 'edit.php' )
		);

	}

	public function get_authentication_url() {

		$nonce = wp_create_nonce( 'automator_linkedin_auth_nonce' );

		return add_query_arg(
			array(
				'action'   => 'authorization_request',
				'user_url' => rawurlencode( admin_url( 'admin-ajax.php?action=automator_linkedin_capture_tokens&nonce=' . $nonce ) ),
				'nonce'    => $nonce,
			),
			AUTOMATOR_API_URL . 'v2/linkedin'
		);

	}

	public function verify_access( $nonce = '' ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->redirect(
				$this->get_settings_url(),
				array(
					'status' => 'error_not_admin',
					'code'   => 403,
				)
			);
		}

		if ( ! wp_verify_nonce( $nonce, 'automator_linkedin_auth_nonce' ) ) {
			$this->redirect(
				$this->get_settings_url(),
				array(
					'status' => 'error_nonce_invalid',
					'code'   => 403,
				)
			);
		}

	}

	public function disconnect() {

		$this->verify_access( automator_filter_input( 'nonce' ) );

		delete_option( self::LINKEDIN_CLIENT );

		delete_option( self::LINKEDIN_CONNECTED_USER );

		delete_transient( 'automator_linkedin_pages' );

		$this->redirect(
			$this->get_settings_url(),
			array(
				'status' => 'disconnected',
				'code'   => 200,
			)
		);

	}

	public function get_disconnect_url() {

		return add_query_arg(
			array(
				'action' => 'automator_linkedin_disconnect',
				'nonce'  => wp_create_nonce( 'automator_linkedin_auth_nonce' ),
			),
			admin_url( 'admin-ajax.php' )
		);

	}

	/**
	 * Method api_call
	 *
	 * @param  array $body The request body form-data.
	 * @param  array $action The Automator Action parameters.
	 *
	 * @return string Json encoded response from API.
	 */
	public function api_call( $body, $action = null ) {

		$this->refresh_access_tokens();

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => $action,
			'timeout'  => 45,
		);

		$response = Api_Server::api_call( $params );

		$this->maybe_throw_exception( $response );

		return $response;

	}

	public function refresh_access_tokens() {

		$n_days = $this->get_access_token_remaining_days();

		if ( $n_days <= 30 ) {
			$this->fetch_access_tokens();
		}

	}

	public function get_access_token_remaining_days() {

		$client = automator_get_option( self::LINKEDIN_CLIENT, false );

		if ( empty( $client['expires_on'] ) ) {
			return;
		}

		$seconds_passed = $client['expires_on'] - strtotime( current_time( 'mysql' ) );

		$days_passed = floor( $seconds_passed / ( 60 * 60 * 24 ) );

		return $days_passed;

	}

	public function fetch_access_tokens() {

		$body = array(
			'refresh_token' => $this->get_client()['refresh_token'],
			'action'        => 'refresh_token',
		);

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'timeout'  => 10,
		);

		try {

			$response = Api_Server::api_call( $params );

			if (
				// Validate everything before putting into the data.
				! empty( $response['data']['access_token'] ) &&
				! empty( $response['data']['refresh_token'] ) &&
				! empty( $response['data']['expires_in'] ) &&
				! empty( $response['data']['refresh_token_expires_in'] )
			) {

				// Manually set the access token and refresh token expiration date.
				$response['data']['expires_on']               = strtotime( current_time( 'mysql' ) ) + $response['data']['expires_in'];
				$response['data']['refresh_token_expires_on'] = strtotime( current_time( 'mysql' ) ) + $response['data']['refresh_token_expires_in'];

				update_option( self::LINKEDIN_CLIENT, $response['data'], true );

			}
		} catch ( \Exception $e ) {

			automator_log( 'Error refreshing access tokens: ' . $e->getMessage(), 'LinkedIn refresh token', true );

		}

	}

	public function maybe_throw_exception( $response ) {

		// Okay status. Return.
		if ( 200 === $response['statusCode'] || 201 === $response['statusCode'] ) {
			return;
		}

		$error_message = 'API Error: ' . wp_json_encode( $response['data'] );

		throw new \Exception( $error_message, $response['statusCode'] );

	}

	/**
	 * Refresh token get number of days.
	 *
	 * @return int The number of days until the refresh token expires.
	 */
	public function get_refresh_token_remaining_days() {

		$seconds_passed = absint( $this->get_client()['refresh_token_expires_on'] ) - strtotime( current_time( 'mysql' ) );

		$days_remaining = floor( $seconds_passed / ( 60 * 60 * 24 ) );

		return apply_filters( 'automator_linkedin_get_refresh_token_remaining_days', $days_remaining, $this );

	}

	public function check_refresh_token_expiration() {

		if ( empty( $this->get_client() ) ) {
			return;
		}

		$has_linkedin_live_actions = ! empty( Automator()->utilities->fetch_live_integration_actions( 'LINKEDIN' ) );

		// Also check if there is a live action.
		if ( $this->is_refresh_token_expiring() && $has_linkedin_live_actions ) {

			add_action( 'admin_notices', array( $this, 'admin_notice_show_reminder' ) );

		}

	}

	public function is_refresh_token_expiring() {

		return $this->get_refresh_token_remaining_days() <= self::N_DAYS_REFRESH_TOKEN_EXPIRE_NOTICE;

	}

	public function admin_notice_show_reminder() {

		$days = $this->get_refresh_token_remaining_days();

		printf(
			'<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a> %4$s</p></div>',
			esc_html(
				sprintf(
					/* Translators: Admin notice */
					_n(
						'Your LinkedIn access and refresh tokens will expire in %s day.',
						'Your LinkedIn access and refresh tokens will expire in %s days.',
						$days,
						'uncanny-automator'
					),
					number_format_i18n( $days )
				)
			),
			esc_url( $this->get_settings_url() ),
			esc_html__( 'Click here to reauthorize', 'uncanny-automator' ),
			esc_html__( 'to continue using your LinkedIn account in your recipes.', 'uncanny-automator' )
		);

	}

}
