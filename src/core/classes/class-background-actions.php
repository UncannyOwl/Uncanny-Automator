<?php

namespace Uncanny_Automator;

/**
 * Class Background_Actions
 * @package Uncanny_Automator
 */
class Background_Actions {

	/**
	 * @var
	 */
	public $action;
	/**
	 * @var
	 */
	public $action_code;

	/**
	 * @var
	 */
	public $last_response;

	/**
	 *
	 */
	const IS_USED_FOR_ACTION_TOKEN = 'is_used_for_action_token';
	/**
	 *
	 */
	const ENDPOINT = '/async_action/';
	/**
	 *
	 */
	const OPTION_NAME = 'uncanny_automator_background_actions';

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {

		add_action( 'rest_api_init', array( $this, 'register_rest_endpoint' ) );

		//The priority is important here. We need to make sure we run this filter after scheduling the actions
		add_filter( 'automator_before_action_executed', array( $this, 'maybe_send_to_background' ), 200 );

		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'automator_settings_advanced_tab_view', array( $this, 'settings_output' ) );

		add_action( 'automator_activation_before', array( $this, 'add_option' ) );
		add_action( 'automator_daily_healthcheck', array( $this, 'add_option' ) );
		add_action( 'automator_daily_healthcheck', array( self::class, 'renew_license_check' ) );

		add_filter( 'perfmatters_rest_api_exceptions', array( $this, 'add_rest_api_exception' ) );
	}

	/**
	 * add_rest_api_exception for perfmatters.
	 *
	 * @param mixed $exceptions
	 *
	 * @return void
	 */
	public function add_rest_api_exception( $exceptions ) {
		$exceptions[] = 'uap';

		return $exceptions;
	}

	/**
	 * Renews the license check automatically by deleting the stored transient
	 * that tells Automator that the connected license is invalid.
	 *
	 * Made static so that its portable anywhere in case its needed to renew the license check.
	 *
	 * @since 5.2
	 *
	 * @return void
	 */
	public static function renew_license_check() {
		// Make sure the transients are renewed.
		delete_transient( Api_Server::TRANSIENT_LICENSE_CHECK_FAILED );
		delete_transient( 'automator_api_license' );
		// Initiate the license check.
		return Api_Server::get_license();
	}

	/**
	 * add_option
	 *
	 * @return void
	 */
	public function add_option() {

		$current_option  = get_option( self::OPTION_NAME, 'option_does_not_exist' );
		$bg_actions_work = $this->test_endpoint( '1' );

		if ( 'option_does_not_exist' === $current_option ) {
			add_option( self::OPTION_NAME, $bg_actions_work );

			return;
		}

		if ( '1' === $current_option && '0' === $bg_actions_work ) {
			update_option( self::OPTION_NAME, '0' );
		}
	}

	/**
	 * register_rest_endpoint
	 *
	 * @return void
	 */
	public function register_rest_endpoint() {

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			self::ENDPOINT,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'background_action_rest' ),
				'permission_callback' => array( $this, 'validate_rest_call' ),
			)
		);
	}

	/**
	 * maybe_send_to_background
	 *
	 * This function will check if the action should sent to background.
	 *
	 * @param array $action
	 *
	 * @return array
	 */
	public function maybe_send_to_background( $action ) {

		$this->action      = $action;
		$this->action_code = $this->get_action_code( $action );

		try {

			$this
				->is_not_doing_cron()
				->not_scheduled()
				->can_process_further()
				->should_process_in_background()
				->action_tokens_not_used_in_other_actions()
				->send_to_background();

		} catch ( \Exception $e ) {
			automator_log( $e->getMessage() );
		}

		return $this->action;
	}

	/**
	 * not_scheduled
	 *
	 * Will throw an exception if the action is scheduled or delayed.
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function not_scheduled() {

		if ( ! empty( $this->action['action_data']['meta']['async_mode'] ) ) {
			throw new \Exception( __( 'This action is scheduled or delayed', 'uncanny-automator' ) );
		}

		return $this;
	}

	/**
	 * can_process_further
	 *
	 * Will throw an exception if the action was prevented from processing further earlier.
	 *
	 * @return $this
	 */
	public function can_process_further() {

		if ( isset( $this->action['process_further'] ) && false === $this->action['process_further'] ) {
			throw new \Exception( __( 'This action was prevented from processing earlier', 'uncanny-automator' ) );
		}

		return $this;
	}

	/**
	 * should_process_in_background
	 *
	 * @return $this
	 */
	public function should_process_in_background() {

		$process_in_bg = $this->bg_actions_enabled() && $this->is_bg_action();

		$process_in_bg = apply_filters( 'automator_action_should_process_in_background', $process_in_bg, $this->action );

		if ( ! $process_in_bg ) {
			throw new \Exception( __( 'This action is not set to run in background', 'uncanny-automator' ) );
		}

		return $this;
	}


	/**
	 * Because of ticket #42462. When a user submits a post with a term and tax,
	 * it creates a cron schedule to run the trigger, because of it, the cron fires
	 * the action, the action then runs twice
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function is_not_doing_cron() {
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			throw new \Exception( __( 'WP cron is running', 'uncanny-automator' ) );
		}

		return $this;
	}

	/**
	 * @return $this
	 * @throws \Exception
	 */
	public function action_tokens_not_used_in_other_actions() {
		$action_id             = $this->action['action_data']['ID'];
		$action_tokens_used_in = get_post_meta( $action_id, self::IS_USED_FOR_ACTION_TOKEN, true );
		if ( ! empty( $action_tokens_used_in ) && is_numeric( $action_tokens_used_in ) && 0 !== absint( $action_tokens_used_in ) ) {
			/* translators: Action ID */
			throw new \Exception( sprintf( __( "Action's token used in ID: %d action", 'uncanny-automator' ), $action_tokens_used_in ) );
		}

		return $this;
	}

	/**
	 * bg_actions_enabled
	 *
	 * @return bool
	 */
	public function bg_actions_enabled() {
		$value = get_option( self::OPTION_NAME, '1' );

		return '1' === $value;
	}

	/**
	 * @return mixed|null
	 */
	public function is_bg_action() {

		$bg_action = Automator()->get->value_from_action_meta( $this->action_code, 'background_processing' );

		$bg_action = apply_filters( 'automator_is_background_action', $bg_action, $this->action );

		return $bg_action;
	}

	/**
	 * send_to_background
	 *
	 * @return $this
	 */
	public function send_to_background( $blocking = false ) {

		$url = get_rest_url() . AUTOMATOR_REST_API_END_POINT . self::ENDPOINT;

		$request = array(
			'body' => $this->action,
		);

		if ( false === $blocking ) {
			$request['timeout']  = 0.01;
			$request['blocking'] = false;
		}

		// Call the endpoint to make sure that the process runs at the background.
		// Store the response for unit tests simplification.
		$this->last_response = $this->remote_post( $url, $request );

		$this->action['process_further'] = false;

		return $this;
	}

	/**
	 * remote_post
	 *
	 * @param string $url
	 * @param mixed $request
	 *
	 * @return mixed
	 */
	public function remote_post( $url, $request ) {
		return wp_remote_post( $url, $request );
	}

	/**
	 * test_endpoint
	 *
	 * Disable background actions if the endpoint is not reachable.
	 *
	 * @return string
	 */
	public function test_endpoint( $value ) {

		if ( empty( $value ) ) {
			return '0';
		}

		$this->action = array(
			'process_further' => false,
			'action_data'     => array(
				'meta' => array(
					'integration' => 'WP',
					'code'        => 'REST_API_TEST',
				),
			),
		);

		$this->send_to_background( true );

		$error = $this->rest_api_error();

		if ( empty( $error ) ) {
			return '1';
		}

		if ( function_exists( 'add_settings_error' ) ) {
			add_settings_error( self::OPTION_NAME, self::OPTION_NAME, $error, 'error' );
		}

		return '0';
	}

	/**
	 * rest_api_error
	 *
	 * @return string
	 */
	public function rest_api_error() {

		if ( is_wp_error( $this->last_response ) ) {
			return $this->last_response->get_error_message();
		}

		if ( 200 === wp_remote_retrieve_response_code( $this->last_response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $this->last_response ), true );

		if ( ! empty( $body['message'] ) ) {
			return $body['message'];
		}

		return __( 'Unknown REST API error', 'uncanny-automator' );
	}

	/**
	 * validate_rest_call
	 *
	 * @param mixed $request
	 *
	 * @return bool
	 */
	public function validate_rest_call( $request ) {

		$action = $request->get_body_params();

		if ( empty( $action['action_data']['meta']['integration'] ) || empty( $this->get_action_code( $action ) ) ) {
			return false;
		}

		return true;
	}

	/**
	 * background_action_rest
	 *
	 * @return void
	 */
	public function background_action_rest( $request ) {

		$action = $request->get_body_params();

		$action = apply_filters( 'automator_before_background_action_executed', $action );

		if ( isset( $action['process_further'] ) && false === boolval( $action['process_further'] ) ) {

			automator_log( 'Action was skipped by automator_before_background_action_executed filter.' );

			return;
		}

		$this->run_action( $action );
	}

	/**
	 * run_action
	 *
	 * This function  will run the actions at the background.
	 *
	 * @param array $action
	 *
	 * @return void
	 */
	public function run_action( $action ) {

		$action_code = $this->get_action_code( $action );

		$action_execution_function = Automator()->get->action_execution_function_from_action_code( $action_code );

		if ( isset( $action['process_further'] ) ) {
			unset( $action['process_further'] );
		}

		try {

			call_user_func_array( $action_execution_function, $action );
			do_action( 'automator_bg_action_after_run', $action );

		} catch ( \Error $e ) {
			$this->complete_with_error( $action, $e->getMessage() );
		} catch ( \Exception $e ) {
			$this->complete_with_error( $action, $e->getMessage() );
		}
	}

	/**
	 * complete_with_error
	 *
	 * @param mixed $action
	 * @param mixed $error
	 *
	 * @return void
	 */
	public function complete_with_error( $action, $error = '' ) {

		$recipe_id = $action['recipe_id'];
		$user_id   = $action['user_id'];

		$action['action_data']['complete_with_errors'] = true;

		Automator()->complete->action( $user_id, $action['action_data'], $recipe_id, $error );
	}

	/**
	 * get_action_code
	 *
	 * @param mixed $action
	 *
	 * @return mixed
	 */
	public function get_action_code( $action ) {
		return empty( $action['action_data']['meta']['code'] ) ? null : $action['action_data']['meta']['code'];
	}

	/**
	 * register_setting
	 *
	 * @return void
	 */
	public function register_setting() {

		$args = array(
			'type'              => 'boolean',
			'sanitize_callback' => array( $this, 'test_endpoint' ),
		);

		register_setting( 'uncanny_automator_advanced', self::OPTION_NAME, $args );
	}

	/**
	 * maybe_show_error
	 *
	 * @return void
	 */
	public function maybe_show_error() {

		$error_message = '';

		$errors = (array) get_settings_errors( self::OPTION_NAME );

		if ( empty( $errors ) ) {
			return;
		}

		$error         = array_shift( $errors );
		$error_message = $error['message'];

		?>

		<uo-alert class="uap-spacing-top" type="error"
				  heading="<?php esc_html_e( 'Background actions have been automatically disabled because an error was detected:', 'uncanny-automator' ); ?>">

			<?php echo esc_html( $error_message ); ?>

		</uo-alert>

		<?php

	}

	/**
	 * settings_output
	 *
	 * Outputs the background action settings.
	 *
	 * @param mixed $action
	 *
	 * @return void
	 */
	public function settings_output( $settings_group ) {

		$bg_actions_enabled = $this->bg_actions_enabled();

		?>

		<?php $this->maybe_show_error(); ?>

		<div class="uap-field uap-spacing-top--small">

			<uo-switch
				id="<?php echo esc_attr( self::OPTION_NAME ); ?>"
				<?php echo $bg_actions_enabled ? 'checked' : ''; ?>

				status-label="<?php esc_attr_e( 'Enabled', 'uncanny-automator' ); ?>,<?php esc_attr_e( 'Disabled', 'uncanny-automator' ); ?>"

				class="uap-spacing-top"
			></uo-switch>

			<div class="uap-field-description">
				<?php esc_html_e( 'When enabled, actions that send data to an external site or service will be run in a background process to accelerate the execution of recipes.  We recommend leaving this setting enabled unless instructed otherwise by support.', 'uncanny-automator' ); ?>
			</div>

		</div>
		<?php
	}
}

