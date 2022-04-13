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
	 * The API endpoint address.
	 *
	 * @var API_ENDPOINT The endpoint adress.
	 */
	const API_ENDPOINT = 'v2/zoom';

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

		add_action( 'update_option_uap_automator_zoom_api_settings_timestamp', array( $this, 'settings_updated' ) );
		add_action( 'add_option_uap_automator_zoom_api_settings_timestamp', array( $this, 'settings_updated' ) );

		// Disconnect wp-ajax action.
		add_action( 'wp_ajax_uap_automator_zoom_api_disconnect', array( $this, 'disconnect' ), 10 );

		add_action( 'wp_ajax_uap_zoom_api_get_meeting_questions', array( $this, 'api_get_meeting_questions' ) );

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
			'role_in_purchase_process'
		);
	}
	
	/**
	 * load_settings
	 *
	 * @return void
	 */
	public function load_settings() {
		$this->setting_tab   = 'zoom-api';
		$this->tab_url = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations&integration=' . $this->setting_tab;
		include_once __DIR__ . '/../settings/settings-zoom.php';
		new Zoom_Settings( $this );
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

		if ( 'uo-recipe' != get_current_screen()->post_type ) {
			return;
		}

		$script_uri = plugin_dir_url( __FILE__ ) . '../scripts/zoom-meetings.js';

		wp_enqueue_script( 'zoom-meetings', $script_uri, array ( 'jquery' ), InitializePlugin::PLUGIN_VERSION, true );
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

		$body = array(
			'action'       => 'get_meetings',
			'page_number'  => 1,
			'page_size'    => 1000,
			'type'         => 'upcoming',
		);

		try {

			$response = $this->api_request( $body );

			if ( 200 !== $response['statusCode'] ) {
				throw new \Exception( __( 'Could not fetch meetngs from Zoom', 'uncanny-automator' ), $response['statusCode'] );
			}

			if ( empty( $response['data']['meetings'] ) || count( $response['data']['meetings'] ) < 1 ) {
				throw new \Exception( __( 'No meetings were found in your account', 'uncanny-automator' ) );
			}

			foreach ( $response['data']['meetings'] as $meeting ) {
				$options[] = array(
					'value' => $meeting['id'],
					'text'  => $meeting['topic'],
				);
			}
			
		} catch ( \Exception $e ) {
			$options[] = array(
				'value' => '',
				'text'  => $e->getMessage()
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

		return apply_filters( 'uap_option_zoom_get_meetings', $option );
	}
	
	/**
	 * get_meeting_questions_repeater
	 *
	 * @param  mixed $label
	 * @param  mixed $option_code
	 * @param  mixed $args
	 * @return void
	 */
	public function get_meeting_questions_repeater() {

		return array(
			'option_code'       => 'MEETINGQUESTIONS',
			'input_type'        => 'repeater',
			'label'             => __( 'Meeting questions', 'uncanny-automator' ),
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
	 * api_get_meeting_questions
	 *
	 * @return void
	 */
	public function api_get_meeting_questions() {

		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();

		$meeting_id = automator_filter_input( 'meeting_id', INPUT_POST );

		try {

			$body = array(
				'action' => 'get_meeting_questions',
				'meeting_id' => $meeting_id
			);

			$response = $this->api_request( $body );

			if ( 200 !== $response['statusCode'] ) {
				throw new \Exception( __( 'Could not fetch meeting questions from Zoom', 'uncanny-automator' ), $response['statusCode'] );
			}

			wp_send_json_success( $response['data'], $response['statusCode'] );

		} catch ( \Exception $e ) {
			$error = new \WP_Error( $e->getCode(), $e->getMessage() );
			wp_send_json_error( $error );
		}
		
		die();
	}
	
	/**
	 * add_to_meeting
	 *
	 * @param  mixed $user
	 * @param  mixed $meeting_key
	 * @param  mixed $action_data
	 * @return void
	 */
	public function add_to_meeting( $user, $meeting_key, $action_data ) {

		if ( empty( $user['email'] ) || false === is_email( $user['email'] ) ) {
			throw new \Exception(  __( 'Email address is missing or invalid.', 'uncanny-automator' ) );
		}

		if ( empty( $user['first_name'] ) ) {
			throw new \Exception( __( 'First name is missing', 'uncanny-automator' ) );
		}

		if ( empty( $meeting_key ) ) {
			throw new \Exception( __( 'Meeting key is missing', 'uncanny-automator' ) );
		}

		$body = array(
			'action'       => 'register_meeting_user',
			'meeting_key'  => $meeting_key
		);

		$body = array_merge( $body, $user );

		$response = $this->api_request( $body, $action_data );

		if ( 201 !== $response['statusCode'] ) {
			throw new \Exception( __( 'User could not be added to the meeting', 'uncanny-automator' ) );
		}

		return $response;
	}

	/**
	 * For un-registering user to meeting action method.
	 *
	 * @param string $user_id
	 * @param string $meeting_key
	 *
	 * @return array
	 */
	public function unregister_user( $email, $meeting_key, $action_data ) {

		if ( empty( $email ) || ! is_email( $email ) ) {
			throw new \Exception(  __( 'Email address is missing or invalid.', 'uncanny-automator' ) );
		}

		$body = array(
			'action'       => 'unregister_meeting_user',
			'meeting_key'  => $meeting_key,
			'email'        => $email,
		);
	
		$response = $this->api_request( $body, $action_data );

		if ( 201 !== $response['statusCode'] && 204 !== $response['statusCode'] ) {
			throw new \Exception( __( 'Could not unregister the user', 'uncanny-automator' ) );
		}
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

		$body = array(
			'action' => 'get_user',
		);

		$response = $this->api_request( $body );

		if ( 200 !== $response['statusCode'] ) {
			throw new \Exception( __( 'Could not fetch user info', 'uncanny-automator' ) );
		}

		$user_info = $response['data'];

		update_option( 'uap_zoom_api_connected_user', $user_info );

		return $user_info;
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

		$client = array();

		// Get the API key and secret
		$consumer_key    = trim( get_option( 'uap_automator_zoom_api_consumer_key', '' ) );
		$consumer_secret = trim( get_option( 'uap_automator_zoom_api_consumer_secret', '' ) );

		if ( empty( $consumer_key ) || empty( $consumer_secret ) ) {
			throw new \Exception( __( 'Zoom is not connected', 'uncanny-automator' ) );
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

			delete_option( 'uap_zoom_api_connected_user' );

			delete_transient( 'uap_automator_zoom_api_user_info' );

		}

		wp_safe_redirect( $this->tab_url );

		exit;

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
			'body' => $body,
			'action' => $action_data
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

			$question_name = $question['QUESTION_NAME'];
			$question_value = Automator()->parse->text( $question['QUESTION_VALUE'], $recipe_id, $user_id, $args );

			if ( in_array( $question_name, $this->default_questions ) ) {  	// If it is one of the default questions
				$user[$question_name] = $question_value;
			} else { 															// If it's a custom question
				$question_data = array();
				$question_data['title'] = $question_name;
				$question_data['value'] = $question_value;
				$user['custom_questions'][] = $question_data;
			}
		}

		return $user;
	}
	

	/**
	 * settings_updated
	 *
	 * @return void
	 */
	public function settings_updated() {

		$redirect_url = $this->tab_url;
	
		delete_option( '_uncannyowl_zoom_settings' );

		$result = 1;

		try {
			$this->api_get_user_info();
		} catch ( \Exception $e ) { 
			delete_option( 'uap_zoom_api_connected_user' );
			$result = $e->getMessage();
		}

		$redirect_url .= '&connect=' . $result;
		
		wp_safe_redirect( $redirect_url );

		exit;
	}

	/**
	 * get_user
	 *
	 * @return void
	 */
	public function get_user() {
		$users_option_exist = get_option( 'uap_zoom_api_connected_user', 'no' );

		if ( 'no' !== $users_option_exist ) {
			return $users_option_exist;
		}

		try {
			$user = $this->api_get_user_info(); 
		} catch ( \Exception $e ) {
			$user = false;
		}

		return $user;
	}
}

