<?php
namespace Uncanny_Automator\Settings;

/**
 * Trait for premium integration alert notifications
 * Registers short lived alerts to transients to be displayed on the settings page
 * For specific integrations and users.
 *
 * @package Uncanny_Automator\Settings
 */
trait Premium_Integration_Alerts {

	/**
	 * An array of alerts to display on the current settings page
	 *
	 * @var array
	 */
	protected $alerts = array();

	/**
	 * Add an alert to the current page
	 *
	 * @param array $alert {
	 *     Alert configuration
	 *     @type string $type    Alert type (success, error, warning, info)
	 *     @type string $heading Alert heading
	 *     @type string $content Alert content
	 * }
	 * @return void
	 */
	public function add_alert( $alert ) {
		$this->alerts[] = $alert;
	}

	/**
	 * Get all alerts for the current page
	 *
	 * @return array
	 */
	public function get_alerts() {
		return $this->alerts;
	}

	/**
	 * Register an alert to be displayed on the next page load
	 *
	 * @param array $alert {
	 *     Alert configuration
	 *     @type string $type    Alert type (success, error, warning, info)
	 *     @type string $heading Alert heading
	 *     @type string $content Alert content
	 * }
	 * @return void
	 */
	public function register_alert( $alert ) {
		$alerts   = $this->get_stored_alerts();
		$alerts[] = $alert;
		$this->store_alerts( $alerts );
	}

	/**
	 * Get all stored alerts for this integration and user
	 *
	 * @return array
	 */
	public function get_stored_alerts() {
		$transient_key = $this->get_alerts_transient_key();
		$alerts        = get_transient( $transient_key );
		return is_array( $alerts ) ? $alerts : array();
	}

	/**
	 * Store alerts in a transient
	 *
	 * @param array $alerts
	 * @return void
	 */
	public function store_alerts( $alerts ) {

		if ( empty( $alerts ) ) {
			return;
		}

		$transient_key = $this->get_alerts_transient_key();
		set_transient( $transient_key, $alerts, 45 ); // 45 seconds should be enough for page load
	}

	/**
	 * Clear all stored alerts for this integration and user
	 *
	 * @return void
	 */
	protected function clear_stored_alerts() {
		$transient_key = $this->get_alerts_transient_key();
		delete_transient( $transient_key );
	}

	/**
	 * Get the transient key for alerts
	 *
	 * @return string
	 */
	private function get_alerts_transient_key() {
		return sprintf(
			'automator_%s_alerts_%d',
			$this->get_id(),
			get_current_user_id()
		);
	}

	/**
	 * Display all alerts and clear stored alerts
	 *
	 * @return void
	 */
	public function display_alerts() {
		// Display stored alerts from previous page load
		$stored_alerts = $this->get_stored_alerts();
		if ( ! empty( $stored_alerts ) ) {
			foreach ( $stored_alerts as $alert ) {
				$this->alert_html( $alert );
			}
			$this->clear_stored_alerts();
		}

		// Display current page alerts
		if ( ! empty( $this->alerts ) ) {
			foreach ( $this->alerts as $alert ) {
				$this->alert_html( $alert );
			}
		}
	}

	/**
	 * Output an alert HTML.
	 *
	 * @param array $alert The alert data.
	 *
	 * @return void - Outputs HTML directly
	 */
	public function alert_html( $alert ) {
		$default = array(
			'type'    => '',
			'heading' => '',
			'content' => '',
			'class'   => 'uap-spacing-bottom uap-spacing-top',
			'button'  => array(), // Optional action button configuration
		);

		$alert = wp_parse_args( $alert, $default );

		// Get the allowed HTML from the templating trait
		$allowed_html = $this->filter_content_kses_args();

		?>
		<uo-alert
			type="<?php echo esc_attr( $alert['type'] ); ?>"
			heading="<?php echo esc_attr( $alert['heading'] ); ?>"
			class="<?php echo esc_attr( $alert['class'] ); ?>"
		>
			<?php echo wp_kses( $alert['content'], $allowed_html ); ?>
			<?php if ( ! empty( $alert['button'] ) ) : ?>
				<div class="uap-spacing-top">
					<?php
					$this->output_action_button(
						$alert['button']['action'] ?? '',
						$alert['button']['label'] ?? '',
						$alert['button']['args'] ?? array()
					);
					?>
				</div>
			<?php endif; ?>
		</uo-alert>
		<?php
	}

	/**
	 * Get an alert configuration
	 *
	 * @param string $type    Alert type (success, error, warning, info)
	 * @param string $message Alert message
	 * @param string $heading Optional heading override
	 *
	 * @return array
	 */
	protected function get_alert( $type, $message, $heading = '' ) {
		$default_headings = array(
			'success' => esc_html_x( 'Success', 'Integration settings', 'uncanny-automator' ),
			'error'   => esc_html_x( 'Error', 'Integration settings', 'uncanny-automator' ),
			'warning' => esc_html_x( 'Warning', 'Integration settings', 'uncanny-automator' ),
			'info'    => esc_html_x( 'Info', 'Integration settings', 'uncanny-automator' ),
		);

		return array(
			'type'    => $type,
			'heading' => ! empty( $heading ) ? $heading : ( $default_headings[ $type ] ?? '' ),
			'content' => esc_html( $message ),
		);
	}

	/**
	 * Register an alert
	 *
	 * @param string $type    Alert type (success, error, warning, info)
	 * @param string $message Alert message
	 * @param string $heading Optional heading override
	 *
	 * @return void
	 */
	protected function register_alert_by_type( $type, $message, $heading = '' ) {
		$this->register_alert(
			$this->get_alert( $type, $message, $heading )
		);
	}

	/**
	 * Get a connected alert
	 *
	 * @param string $message Optional custom message
	 * @param string $heading Optional custom heading
	 *
	 * @return array
	 */
	protected function get_connected_alert( $message = '', $heading = '' ) {
		$default_message = sprintf(
			// translators: %s: Integration name.
			esc_html_x( 'The %s integration has been connected successfully.', 'Integration settings', 'uncanny-automator' ),
			$this->get_name()
		);

		return $this->get_alert(
			'success',
			! empty( $message ) ? $message : $default_message,
			! empty( $heading ) ? $heading : esc_html_x( 'Connected', 'Integration settings', 'uncanny-automator' )
		);
	}

	/**
	 * Register a connected alert
	 *
	 * @param string $message Optional custom message
	 * @param string $heading Optional custom heading
	 *
	 * @return void
	 */
	public function register_connected_alert( $message = '', $heading = '' ) {
		$this->register_alert(
			$this->get_connected_alert( $message, $heading )
		);
	}

	/**
	 * Register a success alert
	 *
	 * @param string $message
	 * @param string $heading
	 *
	 * @return void
	 */
	public function register_success_alert( $message, $heading = '' ) {
		$this->register_alert_by_type( 'success', $message, $heading );
	}

	/**
	 * Register an error alert
	 *
	 * @param string $message
	 * @param string $heading
	 *
	 * @return void
	 */
	public function register_error_alert( $message, $heading = '' ) {
		$this->register_alert_by_type( 'error', $message, $heading );
	}

	/**
	 * Register a warning alert
	 *
	 * @param string $message
	 * @param string $heading
	 *
	 * @return void
	 */
	public function register_warning_alert( $message, $heading = '' ) {
		$this->register_alert_by_type( 'warning', $message, $heading );
	}

	/**
	 * Register an info alert
	 *
	 * @param string $message
	 * @param string $heading
	 *
	 * @return void
	 */
	public function register_info_alert( $message, $heading = '' ) {
		$this->register_alert_by_type( 'info', $message, $heading );
	}

	/**
	 * Get an error alert
	 *
	 * @param string $message Optional custom message
	 * @param string $heading Optional custom heading
	 *
	 * @return array
	 */
	public function get_error_alert( $message = '', $heading = '' ) {
		$default_message = sprintf(
			// translators: %s: Integration name.
			esc_html_x( 'There was an error with the %s integration.', 'Integration settings', 'uncanny-automator' ),
			$this->get_name()
		);

		return $this->get_alert(
			'error',
			! empty( $message ) ? $message : $default_message,
			! empty( $heading ) ? $heading : esc_html_x( 'Error', 'Integration settings', 'uncanny-automator' )
		);
	}

	/**
	 * Get a success alert
	 *
	 * @param string $message Optional custom message
	 * @param string $heading Optional custom heading
	 *
	 * @return array
	 */
	public function get_success_alert( $message = '', $heading = '' ) {
		$default_message = sprintf(
			// translators: %s: Integration name.
			esc_html_x( 'The %s integration operation was completed successfully.', 'Integration settings', 'uncanny-automator' ),
			$this->get_name()
		);

		return $this->get_alert(
			'success',
			! empty( $message ) ? $message : $default_message,
			! empty( $heading ) ? $heading : esc_html_x( 'Success', 'Integration settings', 'uncanny-automator' )
		);
	}

	/**
	 * Get an info alert
	 *
	 * @param string $message The message to display
	 * @param string $heading Optional custom heading
	 *
	 * @return array
	 */
	public function get_info_alert( $message, $heading = '' ) {
		return $this->get_alert( 'info', $message, $heading );
	}

	/**
	 * Get a warning alert
	 *
	 * @param string $message The message to display
	 * @param string $heading Optional custom heading
	 *
	 * @return array
	 */
	public function get_warning_alert( $message, $heading = '' ) {
		return $this->get_alert( 'warning', $message, $heading );
	}
}