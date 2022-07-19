<?php

namespace Uncanny_Automator;

/**
 * Class Background_Actions
 * @package Uncanny_Automator
 */
class Background_Actions {

	const ENDPOINT    = '/async_action/';
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

	}

	/**
	 * add_option
	 *
	 * @return void
	 */
	public function add_option() {
		add_option( self::OPTION_NAME, $this->test_endpoint( '1' ) );
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
	 * @param  array $action
	 * @return array
	 */
	public function maybe_send_to_background( $action ) {

		$this->action      = $action;
		$this->action_code = $this->get_action_code( $action );

		try {

			$this
			->not_scheduled()
			->can_process_further()
			->should_process_in_background()
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
	 * bg_actions_enabled
	 *
	 * @return void
	 */
	public function bg_actions_enabled() {
		$value = get_option( self::OPTION_NAME, '1' );
		return '1' === $value;
	}

	/**
	 * is_bg_action
	 *
	 * @return void
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
	public function send_to_background( $test = false ) {

		$url = get_rest_url() . AUTOMATOR_REST_API_END_POINT . self::ENDPOINT;

		$request = array(
			'body' => $this->action,
		);

		if ( false === $test ) {
			$request['timeout']  = 0.01;
			$request['blocking'] = false;
		}

		// Call the endpoint to make sure that the process runs at the background.
		// Store the response for unit tests simplification.
		$this->last_response = wp_remote_post( $url, $request );

		$this->action['process_further'] = false;

		return $this;

	}

	/**
	 * test_endpoint
	 *
	 * Disable background actions if the endpoint is not reachable.
	 *
	 * @return void
	 */
	public function test_endpoint( $value ) {

		if ( empty( $value ) ) {
			return '0';
		}

		$this->action = array();
		$this->send_to_background( true );

		if ( ! is_wp_error( $this->last_response ) ) {
			return '1';
		}

		add_settings_error( self::OPTION_NAME, self::OPTION_NAME, $this->last_response->get_error_message(), 'error' );

		return '0';
	}

	/**
	 * validate_rest_call
	 *
	 * @param  mixed $request
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

		if ( isset( $action['process_further'] ) && false === $action['process_further'] ) {

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
	 * @param  array $action
	 * @return void
	 */
	public function run_action( $action ) {

		$action_code = $this->get_action_code( $action );

		$action_execution_function = Automator()->get->action_execution_function_from_action_code( $action_code );

		if ( isset( $action['process_further'] ) ) {
			unset( $action['process_further'] );
		}

		call_user_func_array( $action_execution_function, $action );
	}

	/**
	 * get_action_code
	 *
	 * @param  mixed $action
	 * @return void
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

		<uo-alert class="uap-spacing-top" type="error" heading="<?php esc_html_e( 'Background actions have been automatically disabled because an error was detected:', 'uncanny-automator' ); ?>">

			<?php echo esc_html( $error_message ); ?>

		</uo-alert>

		<?php

	}

	/**
	 * settings_output
	 *
	 * Outputs the background action settings.
	 *
	 * @param  mixed $action
	 * @return void
	 */
	public function settings_output( $settings_group ) {

		$bg_actions_enabled = $this->bg_actions_enabled();

		?>
		<div class="uap-settings-panel-content-subtitle">
			<?php esc_html_e( 'Background actions', 'uncanny-automator' ); ?>
		</div>

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

