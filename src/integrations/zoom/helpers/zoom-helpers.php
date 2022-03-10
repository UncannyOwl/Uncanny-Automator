<?php

namespace Uncanny_Automator;

use Firebase\JWT\JWT;

/**
 * Class Zoom_Helpers
 *
 * @package Uncanny_Automator
 */
class Zoom_Helpers {

	/**
	 * @var Zoom_Helpers
	 */
	public $options;
	/**
	 * @var Zoom_Helpers
	 */
	public $pro;
	/**
	 * @var Zoom_Helpers
	 */
	public $setting_tab;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Zoom_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );

		$this->automator_api = AUTOMATOR_API_URL . 'v2/zoom';

		add_action( 'update_option_uap_automator_zoom_api_consumer_key', array( $this, 'zoom_settings_updated' ), 10, 3 );
		add_action( 'update_option_uap_automator_zoom_api_consumer_secret', array( $this, 'zoom_settings_updated' ), 10, 3 );

		// Disconnect wp-ajax action.
		add_action( 'wp_ajax_uap_automator_zoom_api_disconnect', array( $this, 'disconnect' ), 10 );

		$this->load_settings();

	}

	public function load_settings() {
		$this->setting_tab   = 'zoom-api';
		$this->tab_url = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations&integration=' . $this->setting_tab;
		include_once __DIR__ . '/../settings/settings-zoom.php';
		new Zoom_Settings( $this );
	}

	/**
	 * @param Zoom_Helpers $options
	 */
	public function setOptions( Zoom_Helpers $options ) { //phpcs:ignore
		$this->options = $options;
	}

	/**
	 * @param Zoom_Pro_Helpers $pro
	 */
	public function setPro( \Uncanny_Automator_Pro\Zoom_Pro_Helpers $pro ) { // phpcs:ignore
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function get_meetings( $label = null, $option_code = 'ZOOMMEETINGS', $args = array() ) {

		if ( ! $label ) {
			$label = __( 'Meeting', 'uncanny-automator' );
		}

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => __( 'Any Meeting', 'uncanny-automator' ),
			)
		);

		$token        = key_exists( 'token', $args ) ? $args['token'] : true;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = array();

		$client = $this->get_client();

		if ( ! $client || empty( $client['access_token'] ) ) {
			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		// API register call
		$response = wp_remote_post(
			$this->automator_api,
			array(
				'body' =>
					array(
						'action'       => 'get_meetings',
						'access_token' => $client['access_token'],
						'page_number'  => 1,
						'page_size'    => 1000,
						'type'         => 'upcoming',
					),
			)
		);

		if ( ! is_wp_error( $response ) ) {
			$response_code = wp_remote_retrieve_response_code( $response );

			// prepare meeting lists
			if ( $response_code === 200 ) {

				$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

				if ( count( $response_body['data']['meetings'] ) > 0 ) {

					foreach ( $response_body['data']['meetings'] as $meeting ) {
						$options[] = array(
							'value' => $meeting['id'],
							'text'  => $meeting['topic'],
						);
					}
				}
			}
		}

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
		);

		return apply_filters( 'uap_option_zoom_get_meetings', $option );
	}

	/**
	 * For registering user to meeting action method.
	 *
	 * @param string $user_id
	 * @param string $meeting_key
	 *
	 * @return array
	 */
	public function register_user( $user_id, $meeting_key ) {

		$user = get_userdata( $user_id );

		if ( is_wp_error( $user ) ) {
			return array(
				'result'  => false,
				'message' => __( 'Zoom user not found.', 'uncanny-automator' ),
			);
		}

		$customer_first_name = $user->first_name;
		$customer_last_name  = $user->last_name;
		$customer_email      = $user->user_email;

		if ( ! empty( $customer_email ) ) {
			$customer_email_parts = explode( '@', $customer_email );
			$customer_first_name  = empty( $customer_first_name ) ? $customer_email_parts[0] : $customer_first_name;
			$customer_last_name   = empty( $customer_last_name ) ? $customer_email_parts[0] : $customer_last_name;
		}

		$client = $this->get_client();

		if ( ! $client || empty( $client['access_token'] ) ) {
			return array(
				'result'  => false,
				'message' => __( 'Zoom credentials have expired.', 'uncanny-automator' ),
			);
		}

		$response = wp_remote_post(
			$this->automator_api,
			array(
				'body' =>
					array(
						'action'       => 'register_meeting_user',
						'access_token' => $client['access_token'],
						'meeting_key'  => $meeting_key,
						'first_name'   => $customer_first_name,
						'last_name'    => $customer_last_name,
						'email'        => $customer_email,
					),
			)
		);

		if ( ! is_wp_error( $response ) ) {

			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( 201 === wp_remote_retrieve_response_code( $response ) ) {

				if ( isset( $body['data']['join_url'] ) ) {
					return array(
						'result'  => true,
						'message' => __( 'Successfully registered', 'uncanny-automator' ),
					);
				}
			} else {

				$error = '';

				if ( isset( $body['data']['message'] ) ) {
					$error = $body['data']['message'];
				} elseif ( isset( $body['error']['description'] ) ) {
					$error = $body['error']['description'];
				}

				return array(
					'result'  => false,
					'message' => __( $error, 'uncanny-automator' ),
				);
			}
		}

		return array(
			'result'  => false,
			'message' => __( 'WordPress was not able to communicate with Zoom API.', 'uncanny-automator' ),
		);
	}

	/**
	 * For registering a user to meeting action method.
	 *
	 * @param $user
	 * @param $meeting_key
	 *
	 * @return array
	 */
	public function register_userless( $user, $meeting_key ) {

		$customer_email       = $user['EMAIL'];
		$customer_email_parts = explode( '@', $customer_email );

		$customer_first_name = empty( $user['FIRSTNAME'] ) ? $customer_email_parts[0] : $user['FIRSTNAME'];
		$customer_last_name  = empty( $user['LASTNAME'] ) ? $customer_email_parts[0] : $user['LASTNAME'];

		$client = $this->get_client();

		if ( ! $client || empty( $client['access_token'] ) ) {
			return array(
				'result'  => false,
				'message' => __( 'Zoom credentials have expired.', 'uncanny-automator' ),
			);
		}

		$response = wp_remote_post(
			$this->automator_api,
			array(
				'body' =>
					array(
						'action'       => 'register_meeting_user',
						'access_token' => $client['access_token'],
						'meeting_key'  => $meeting_key,
						'first_name'   => $customer_first_name,
						'last_name'    => $customer_last_name,
						'email'        => $customer_email,
					),
			)
		);

		if ( ! is_wp_error( $response ) ) {

			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( 201 === wp_remote_retrieve_response_code( $response ) ) {

				if ( isset( $body['data']['join_url'] ) ) {

					return array(
						'result'  => true,
						'message' => __( 'Successfully registered', 'uncanny-automator' ),
					);
				}
			} else {

				$error = '';

				if ( isset( $body['data']['message'] ) ) {
					$error = $body['data']['message'];
				} elseif ( isset( $body['error']['description'] ) ) {
					$error = $body['error']['description'];
				}

				return array(
					'result'  => false,
					'message' => __( $error, 'uncanny-automator' ),
				);
			}
		}

		return array(
			'result'  => false,
			'message' => __( 'WordPress was not able to communicate with Zoom API.', 'uncanny-automator' ),
		);
	}

	/**
	 * For un-registering user to meeting action method.
	 *
	 * @param string $user_id
	 * @param string $meeting_key
	 *
	 * @return array
	 */
	public function unregister_user( $email, $meeting_key ) {

		$client = $this->get_client();

		if ( ! $client || empty( $client['access_token'] ) ) {
			return array(
				'result'  => false,
				'message' => __( 'Zoom credentails have expired.', 'uncanny-automator' ),
			);
		}

		$response = wp_remote_post(
			$this->automator_api,
			array(
				'body' =>
					array(
						'action'       => 'unregister_meeting_user',
						'access_token' => $client['access_token'],
						'meeting_key'  => $meeting_key,
						'email'        => $email,
					),
			)
		);

		if ( ! is_wp_error( $response ) ) {

			if ( 201 === wp_remote_retrieve_response_code( $response ) || 204 === wp_remote_retrieve_response_code( $response ) ) {

				return array(
					'result'  => true,
					'message' => __( 'Successfully unregistered', 'uncanny-automator' ),
				);

			} else {

				$body = json_decode( wp_remote_retrieve_body( $response ), true );

				$error = '';

				if ( isset( $body['data']['message'] ) ) {
					$error = $body['data']['message'];
				} elseif ( isset( $body['error']['description'] ) ) {
					$error = $body['error']['description'];
				}

				return array(
					'result'  => false,
					'message' => __( $error, 'uncanny-automator' ),
				);
			}
		}

		return array(
			'result'  => false,
			'message' => __( 'WordPress was not able to communicate with Zoom API.', 'uncanny-automator' ),
		);
	}

	public function disconnect_url() {
		return add_query_arg(
			array(
				'action' => 'uap_automator_zoom_api_disconnect',
				'nonce'  => wp_create_nonce( 'uap_automator_zoom_api_disconnect' ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * Returns the zoom user from transient or from zoom api.
	 *
	 * @return mixed The zoom user if tokens are available. Otherwise, false.
	 */
	public function api_get_user_info() {

		$saved_user_info = get_transient( 'uap_automator_zoom_api_user_info' );

		if ( false !== $saved_user_info ) {
			return $saved_user_info;
		}

		$client = $this->get_client();

		if ( ! $client || empty( $client['access_token'] ) ) {
			return false;
		}

		$response = wp_remote_post(
			$this->automator_api,
			array(
				'body' =>
					array(
						'action'       => 'get_user',
						'access_token' => $client['access_token'],
					),
			)
		);

		if ( ! is_wp_error( $response ) ) {

			$status_code = wp_remote_retrieve_response_code( $response );

			if ( 200 === $status_code ) {
				$response_body = json_decode( wp_remote_retrieve_body( $response ) );
				set_transient( 'uap_automator_zoom_api_user_info', $response_body->data, WEEK_IN_SECONDS );

				return $response_body->data;
			}
		}

		return false;

	}


	/**
	 * get_client
	 *
	 * @return void|bool
	 */
	public function get_client() {

		$client = get_option( '_uncannyowl_zoom_settings', false );

		if ( ! $client || empty( $client['access_token'] ) ) {
			return $this->refresh_token();
		}

		if ( empty( $client['expires'] ) || $client['expires'] < time() ) {
			return $this->refresh_token();
		}

		return $client;

	}

	/**
	 * refresh_token
	 *
	 * @param array $client
	 *
	 * @return void
	 */
	public function refresh_token() {

		$client = array();

		// Get the API key and secret
		$consumer_key    = trim( get_option( 'uap_automator_zoom_api_consumer_key', '' ) );
		$consumer_secret = trim( get_option( 'uap_automator_zoom_api_consumer_secret', '' ) );

		if ( empty( $consumer_key ) || empty( $consumer_secret ) ) {
			return false;
		}

		// Set the token expiration to 1 minute as recommended in the docuemntation
		$client['expires'] = time() + 60;

		$payload = array(
			'iss' => $consumer_key,
			'exp' => $client['expires'],
		);

		// Generate the access token using the JWT library
		$token = JWT::encode( $payload, $consumer_secret );

		$client['access_token']  = $token;
		$client['refresh_token'] = $token;

		// Cache it in settings
		update_option( '_uncannyowl_zoom_settings', $client );

		return $client;
	}

	/**
	 * Disconnect the user from the Zoom API.
	 *
	 * @return void.
	 */
	public function disconnect() {

		if ( wp_verify_nonce( filter_input( INPUT_GET, 'nonce', FILTER_DEFAULT ), 'uap_automator_zoom_api_disconnect' ) ) {

			delete_option( 'uap_automator_zoom_api_consumer_key' );
			delete_option( 'uap_automator_zoom_api_consumer_secret' );

			delete_option( '_uncannyowl_zoom_settings_version' );
			delete_option( '_uncannyowl_zoom_settings' );

			delete_transient( 'uap_automator_zoom_api_user_info' );

		}

		wp_safe_redirect( $this->tab_url );

		exit;

	}

	public function zoom_settings_updated( $old_value, $value, $option ) {
		delete_option( '_uncannyowl_zoom_settings' );
		delete_transient( 'uap_automator_zoom_api_user_info' );
	}
}

