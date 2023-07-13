<?php

namespace Uncanny_Automator;

use Firebase\JWT\JWT;

global $zoom_webinar_token_renew;

/**
 * Class Zoom_Webinar_Helpers
 *
 * @package Uncanny_Automator
 */
class Zoom_Webinar_Helpers {

	/**
	 * The API endpoint address.
	 *
	 * @var API_ENDPOINT The endpoint adress.
	 */
	const API_ENDPOINT = 'v2/zoom';

	/**
	 * @var Zoom_Webinar_Helpers
	 */
	public $options;

	/**
	 * @var Zoom_Webinar_Helpers
	 */
	public $pro;

	/**
	 * @var Zoom_Webinar_Helpers
	 */
	public $setting_tab;

	/**
	 * @var bool
	 */
	public $load_options;

	private $default_questions;
	private $tab_url;
	private $automator_api;

	/**
	 * Zoom_Webinar_Helpers constructor.
	 */
	public function __construct() {

		// Selectively load options
		if ( method_exists( '\Uncanny_Automator\Automator_Helpers_Recipe', 'maybe_load_trigger_options' ) ) {
			$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
		} else {
			$this->load_options = true;
		}

		$this->automator_api = AUTOMATOR_API_URL . 'v2/zoom';

		//add_action( 'update_option_uap_automator_zoom_webinar_api_settings_timestamp', array( $this, 'settings_updated' ) );
		//add_action( 'add_option_uap_automator_zoom_webinar_api_settings_timestamp', array( $this, 'settings_updated' ) );

		// Disconnect wp-ajax action.
		add_action( 'wp_ajax_uap_automator_zoom_webinar_api_disconnect', array( $this, 'disconnect' ), 10 );

		add_action( 'wp_ajax_uap_zoom_api_get_webinar_questions', array( $this, 'api_get_webinar_questions' ) );

		add_action( 'wp_ajax_uap_zoom_api_get_webinars', array( $this, 'ajax_get_webinars' ), 10 );
		add_action( 'wp_ajax_uap_zoom_api_get_webinar_occurrences', array( $this, 'ajax_get_webinar_occurrences' ), 10 );

		add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );

		$this->load_settings();

		$this->default_questions = array(
			'address',
			'city',
			'state',
			'zip',
			'country',
			'phone',
			'comments',
			'industry',
			'job_title',
			'no_of_employees',
			'org',
			'purchasing_time_frame',
			'role_in_purchase_process',
		);

	}

	/**
	 * load_settings
	 *
	 * @return void
	 */
	public function load_settings() {
		$this->setting_tab = 'zoom-webinar-api';
		$this->tab_url     = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations&integration=' . $this->setting_tab;
		include_once __DIR__ . '/../settings/settings-zoom-webinar.php';
		new Zoom_Webinar_Settings( $this );
	}

	/**
	 * load_scripts
	 *
	 * @param  mixed $hook
	 * @return void
	 */
	public function load_scripts( $hook ) {

		if ( 'post.php' !== $hook ) {
			return;
		}

		if ( 'uo-recipe' !== get_current_screen()->post_type ) {
			return;
		}

		$script_uri = plugin_dir_url( __FILE__ ) . '../scripts/zoom-webinars.js';

		wp_enqueue_script( 'zoom-webinars', $script_uri, array( 'jquery' ), InitializePlugin::PLUGIN_VERSION, true );
	}

	/**
	 * @param Zoom_Webinar_Helpers $options
	 */
	public function setOptions( Zoom_Webinar_Helpers $options ) { //phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->options = $options;
	}

	/**
	 * @param Zoom_Webinar_Pro_Helpers $pro
	 */
	public function setPro( \Uncanny_Automator_Pro\Zoom_Webinar_Pro_Helpers $pro ) { //phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->pro = $pro;
	}


	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function get_webinars_field( $label = null, $option_code = 'ZOOMWEBINAR', $args = array() ) {

		if ( ! $label ) {
			$label = __( 'Webinar', 'uncanny-automator' );
		}

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => __( 'Any Webinar', 'uncanny-automator' ),
			)
		);

		$token        = key_exists( 'token', $args ) ? $args['token'] : true;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = array();

		$body = array(
			'action'      => 'get_webinars',
			'page_number' => 1,
			'page_size'   => 1000,
			'type'        => 'upcoming',
		);

		try {

			$response = $this->api_request( $body );

			if ( empty( $response['data']['webinars'] ) || count( $response['data']['webinars'] ) < 1 ) {
				throw new \Exception( __( 'No webinars were found in your account', 'uncanny-automator' ) );
			}

			foreach ( $response['data']['webinars'] as $webinar ) {
				$options[] = array(
					'value' => $webinar['id'],
					'text'  => $webinar['topic'],
				);
			}
		} catch ( \Exception $e ) {
			$options[] = array(
				'value' => '',
				'text'  => $e->getMessage(),
			);
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

		return apply_filters( 'uap_option_zoom_get_webinars', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function get_account_user_options() {

		try {

			$options = array();

			$body = array(
				'action' => 'get_account_users',
			);

			$response = $this->api_request( $body );

			if ( 200 !== $response['statusCode'] ) {
				throw new \Exception( __( 'Could not fetch users from Zoom', 'uncanny-automator' ), $response['statusCode'] );
			}

			if ( empty( $response['data']['users'] ) || count( $response['data']['users'] ) < 1 ) {
				throw new \Exception( __( 'No users were found in your account', 'uncanny-automator' ) );
			}

			foreach ( $response['data']['users'] as $user ) {
				$options[] = array(
					'value' => $user['email'],
					'text'  => $user['first_name'] . ' ' . $user['last_name'],
				);
			}
		} catch ( \Exception $e ) {
			$options[] = array(
				'value' => '',
				'text'  => $e->getMessage(),
			);
		}

		return apply_filters( 'uap_option_zoom_get_account_users', $options );
	}

	/**
	 * ajax_get_webinars
	 *
	 * @return void
	 */
	public function ajax_get_webinars() {

		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();

		try {

			$options = array();

			$body = array(
				'action'      => 'get_webinars',
				'user'        => automator_filter_input( 'value', INPUT_POST ),
				'page_number' => 1,
				'page_size'   => 1000,
				'type'        => 'upcoming',
			);

			$response = $this->api_request( $body );

			if ( 200 !== $response['statusCode'] ) {
				throw new \Exception( __( 'Could not fetch user webinars from Zoom', 'uncanny-automator' ), $response['statusCode'] );
			}

			if ( empty( $response['data']['webinars'] ) ) {
				throw new \Exception( __( 'User webinars were not found', 'uncanny-automator' ), $response['statusCode'] );
			}

			foreach ( $response['data']['webinars'] as $webinar ) {

				if ( ! empty( $webinar['topic'] ) ) {

					// The Zoom API response lists one item for each webinar occurrence.
					// To prevent duplicates in the dropdown, we store the webinar ID as the $options array key,
					// But later we remove those keys as they are not needed.
					$options[ $webinar['id'] ] = array(
						'text'  => $webinar['topic'],
						'value' => $webinar['id'],
					);

				}
			}
		} catch ( \Exception $e ) {
			$options[] = array(
				'text'  => $e->getMessage(),
				'value' => '',
			);
		}

		// Remove webinar ID keys (see the previous comment)
		$options = array_values( $options );

		wp_send_json( $options );

		die();
	}

	/**
	 * add_to_webinar
	 *
	 * @param  mixed $user
	 * @param  mixed $webinar_key
	 * @param  mixed $action_data
	 * @return void
	 */
	public function add_to_webinar( $user, $webinar_key, $webinar_occurrences, $action_data ) {

		if ( empty( $user['email'] ) || false === is_email( $user['email'] ) ) {
			throw new \Exception( __( 'Email address is missing or invalid.', 'uncanny-automator' ) );
		}

		if ( empty( $user['first_name'] ) ) {
			throw new \Exception( __( 'First name is missing', 'uncanny-automator' ) );
		}

		if ( empty( $webinar_key ) ) {
			throw new \Exception( __( 'Webinar key is missing', 'uncanny-automator' ) );
		}

		$body = array(
			'action'      => 'register_webinar_user',
			'webinar_key' => $webinar_key,
		);

		if ( ! empty( $webinar_occurrences ) ) {
			$body['occurrences'] = implode( ',', $webinar_occurrences );
		}

		$body = array_merge( $body, $user );

		$response = $this->api_request( $body, $action_data );

		if ( 201 !== $response['statusCode'] ) {
			throw new \Exception( __( 'User could not be added to the webinar', 'uncanny-automator' ) );
		}

		return $response;
	}

	/**
	 * For un-registering user from a webinar action method.
	 *
	 * @param string $user_id
	 * @param string $webinar_key
	 *
	 * @return array
	 */
	public function unregister_user( $email, $webinar_key, $action_data ) {

		if ( empty( $email ) || ! is_email( $email ) ) {
			throw new \Exception( __( 'Email address is missing or invalid.', 'uncanny-automator' ) );
		}

		$body = array(
			'action'      => 'unregister_webinar_user',
			'webinar_key' => $webinar_key,
			'email'       => $email,
		);

		$response = $this->api_request( $body, $action_data );

		if ( 201 !== $response['statusCode'] && 204 !== $response['statusCode'] ) {
			throw new \Exception( __( 'Could not unregister the user', 'uncanny-automator' ) );
		}
	}

	/**
	 * Returns the zoom user from transient or from zoom api.
	 *
	 * @return mixed The zoom user if tokens are available. Otherwise, false.
	 */
	public function api_get_user_info() {

		$body = array(
			'action' => 'get_user',
		);

		$response = $this->api_request( $body );

		if ( 200 !== $response['statusCode'] ) {
			throw new \Exception( __( 'Could not fetch user info', 'uncanny-automator' ) );
		}

		$user_info = $response['data'];

		update_option( 'uap_zoom_webinar_api_connected_user', $user_info );

		return $user_info;
	}

	/**
	 * get_client
	 *
	 * @return void
	 */
	public function get_client() {

		$client = automator_get_option( '_uncannyowl_zoom_webinar_settings', false );

		if ( ! $client || empty( $client['access_token'] ) ) {
			return $this->refresh_token();
		}

		// Refresh token 5 seconds before it expires
		if ( empty( $client['expires'] ) || $client['expires'] - 5 < time() ) {
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

		if ( '3' === get_option( 'uap_automator_zoom_webinar_api_settings_version', false ) ) {
			return $this->refresh_s2s_token();
		}

		$client = array();

		// Get the API key and secret
		$consumer_key    = trim( get_option( 'uap_automator_zoom_webinar_api_consumer_key', '' ) );
		$consumer_secret = trim( get_option( 'uap_automator_zoom_webinar_api_consumer_secret', '' ) );

		if ( empty( $consumer_key ) || empty( $consumer_secret ) ) {
			throw new \Exception( __( 'Zoom Webinars is not connected', 'uncanny-automator' ) );
		}

		// Set the token expiration to 1 minute as recommended in the docuemntation
		$client['expires'] = time() + 60;

		$payload = array(
			'iss' => $consumer_key,
			'exp' => $client['expires'],
		);

		// Generate the access token using the JWT library
		$token = JWT::encode( $payload, $consumer_secret, 'HS256' );

		$client['access_token']  = $token;
		$client['refresh_token'] = $token;

		// Cache it in settings
		update_option( '_uncannyowl_zoom_webinar_settings', $client );

		return $client;
	}

	/**
	 * refresh_s2s_token
	 *
	 * @return void|bool
	 */
	public function refresh_s2s_token() {

		$client = array();

		// Get the API key and secret
		$account_id    = trim( get_option( 'uap_automator_zoom_webinar_api_account_id', '' ) );
		$client_id     = trim( get_option( 'uap_automator_zoom_webinar_api_client_id', '' ) );
		$client_secret = trim( get_option( 'uap_automator_zoom_webinar_api_client_secret', '' ) );

		if ( empty( $account_id ) || empty( $client_id ) || empty( $client_secret ) ) {
			throw new \Exception( __( 'Zoom Webinars credentials are missing', 'uncanny-automator' ) );
		}

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => array(
				'action'        => 'get_token',
				'account_id'    => $account_id,
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
			),
		);

		$response = Api_Server::api_call( $params );

		$this->check_for_errors( $response );

		if ( 200 !== $response['statusCode'] ) {
			throw new \Exception( __( 'Could not fetch the token. Please check the credentials.', 'uncanny-automator' ) );
		}

		$client['access_token'] = $response['data']['access_token'];
		$client['expires']      = $response['data']['expires_in'];

		// Cache it in settings
		update_option( '_uncannyowl_zoom_webinar_settings', $client );

		return $client;

	}

	/**
	 * Disconnect the user from the Zoom API.
	 *
	 * @return void.
	 */
	public function disconnect() {

		if ( wp_verify_nonce( filter_input( INPUT_GET, 'nonce', FILTER_DEFAULT ), 'uap_automator_zoom_webinar_api_disconnect' ) ) {

			delete_option( 'uap_automator_zoom_webinar_api_consumer_key' );
			delete_option( 'uap_automator_zoom_webinar_api_consumer_secret' );

			delete_option( '_uncannyowl_zoom_webinar_settings_version' );
			delete_option( '_uncannyowl_zoom_webinar_settings' );

			delete_option( 'uap_zoom_webinar_api_connected_user' );

			delete_transient( '_uncannyowl_zoom_webinar_settings' );
			delete_transient( 'uap_automator_zoom_webinar_api_user_info' );

			delete_option( 'uap_automator_zoom_webinar_api_account_id' );
			delete_option( 'uap_automator_zoom_webinar_api_client_id' );
			delete_option( 'uap_automator_zoom_webinar_api_client_secret' );
			delete_option( 'uap_automator_zoom_webinar_api_settings_version' );
			delete_option( 'uap_automator_zoom_webinar_api_settings_timestamp' );

		}

		wp_safe_redirect( $this->tab_url );

		exit;

	}

	/**
	 * disconnect_url
	 *
	 * @return void
	 */
	public function disconnect_url() {
		return add_query_arg(
			array(
				'action' => 'uap_automator_zoom_webinar_api_disconnect',
				'nonce'  => wp_create_nonce( 'uap_automator_zoom_webinar_api_disconnect' ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * Method api_request
	 *
	 * @param $params
	 *
	 * @return void
	 */
	public function api_request( $body, $action_data = null ) {

		$client = $this->get_client();

		$body['access_token'] = $client['access_token'];

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
	 * check_for_errors
	 *
	 * @return void
	 */
	public function check_for_errors( $response ) {

		$error = '';

		if ( isset( $response['data']['message'] ) ) {
			$error = $response['data']['message'];
		}

		if ( ! empty( $error ) ) {
			throw new \Exception( $error, $response['statusCode'] );
		}

	}

	/**
	 * get_webinar_questions_repeater
	 *
	 * @param  mixed $label
	 * @param  mixed $option_code
	 * @param  mixed $args
	 * @return void
	 */
	public function get_webinar_questions_repeater() {

		return array(
			'option_code'       => 'WEBINARQUESTIONS',
			'input_type'        => 'repeater',
			'label'             => __( 'Webinar questions', 'uncanny-automator' ),
			/* translators: 1. Button */
			'description'       => '',
			'required'          => false,
			'default_value'     => array(
				array(
					'QUESTION_NAME'  => '',
					'QUESTION_VALUE' => '',
				),
			),
			'fields'            => array(
				array(
					'option_code' => 'QUESTION_NAME',
					'label'       => __( 'Question', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => false,
					'read_only'   => true,
					'options'     => array(),
				),
				Automator()->helpers->recipe->field->text_field( 'QUESTION_VALUE', __( 'Value', 'uncanny-automator' ), true, 'text', '', false ),
			),
			'add_row_button'    => __( 'Add pair', 'uncanny-automator' ),
			'remove_row_button' => __( 'Remove pair', 'uncanny-automator' ),
			'hide_actions'      => true,
		);
	}

	/**
	 * api_get_webinar_questions
	 *
	 * @return void
	 */
	public function api_get_webinar_questions() {

		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();

		$webinar_id = automator_filter_input( 'webinar_id', INPUT_POST );

		try {

			$body = array(
				'action'     => 'get_webinar_questions',
				'webinar_id' => $webinar_id,
			);

			$response = $this->api_request( $body );

			if ( 200 !== $response['statusCode'] ) {
				throw new \Exception( __( 'Could not fetch webinar questions from Zoom', 'uncanny-automator' ), $response['statusCode'] );
			}

			wp_send_json_success( $response['data'], $response['statusCode'] );

		} catch ( \Exception $e ) {
			$error = new \WP_Error( $e->getCode(), $e->getMessage() );
			wp_send_json_error( $error );
		}

		die();
	}

	/**
	 * add_custom_questions
	 *
	 * @param  mixed $user
	 * @param  mixed $questions
	 * @return void
	 */
	public function add_custom_questions( $user, $questions, $recipe_id, $user_id, $args ) {

		$questions = json_decode( $questions, true );

		foreach ( $questions as $question ) {

			if ( empty( $question['QUESTION_VALUE'] ) ) {
				continue;
			}

			$question_name  = $question['QUESTION_NAME'];
			$question_value = Automator()->parse->text( $question['QUESTION_VALUE'], $recipe_id, $user_id, $args );

			if ( in_array( $question_name, $this->default_questions, true ) ) {   // If it is one of the default questions
				$user[ $question_name ] = $question_value;
			} else {                                                            // If it's a custom question
				$question_data              = array();
				$question_data['title']     = $question_name;
				$question_data['value']     = $question_value;
				$user['custom_questions'][] = $question_data;
			}
		}

		return $user;
	}

	public function jwt_mode() {
		return '3' !== get_option( 'uap_automator_zoom_webinar_api_settings_version', false );
	}

	/**
	 * ajax_get_webinar_occurrences
	 *
	 * @return void
	 */
	public function ajax_get_webinar_occurrences() {

		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();

		try {

			$options = array();

			$body = array(
				'action'     => 'get_webinar',
				'webinar_id' => automator_filter_input( 'value', INPUT_POST ),
			);

			$response = $this->api_request( $body );

			if ( 200 !== $response['statusCode'] ) {
				throw new \Exception( __( 'Could not fetch webinar occurrences from Zoom', 'uncanny-automator' ), $response['statusCode'] );
			}

			if ( ! empty( $response['data']['occurrences'] ) ) {
				foreach ( $response['data']['occurrences'] as $occurrence ) {
					$options[] = array(
						'text'  => $this->convert_datetime( $occurrence['start_time'] ),
						'value' => $occurrence['occurrence_id'],
					);
				}
			} else {
				$options[] = array(
					'text'  => $response['data']['start_time'],
					'value' => '',
				);
			}
		} catch ( \Exception $e ) {
			$options[] = array(
				'text'  => $e->getMessage(),
				'value' => '',
			);
		}

		wp_send_json( $options );

		die();
	}

	public function convert_datetime( $str ) {

		$timezone    = wp_timezone();
		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );

		$date = new \DateTime( $str );
		$date->setTimezone( $timezone );

		return $date->format( $time_format . ', ' . $date_format );
	}
}
