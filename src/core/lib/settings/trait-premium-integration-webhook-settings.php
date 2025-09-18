<?php
namespace Uncanny_Automator\Settings;

/**
 * Trait for common premium integration webhook settings patterns.
 * Intended to be used in the settings view of premium integrations.
 *
 * @package Uncanny_Automator\Settings
 */
trait Premium_Integration_Webhook_Settings {

	/**
	 * Webhook option name.
	 *
	 * @var string
	 */
	protected $webhook_option_name = '';

	/**
	 * Set the webhook option name.
	 *
	 * @param string $option_name The option name.
	 *
	 * @return void
	 */
	protected function set_webhook_option_name( $option_name = '' ) {
		$this->webhook_option_name = empty( sanitize_key( $option_name ) )
			? $this->generate_webhook_option_name()
			: sanitize_key( $option_name );
	}

	/**
	 * Get the webhook option name.
	 *
	 * @return string
	 */
	public function get_webhook_option_name() {
		if ( empty( $this->webhook_option_name ) ) {
			$this->set_webhook_option_name( $this->generate_webhook_option_name() );
		}
		return $this->webhook_option_name;
	}

	/**
	 * Generate the default webhook option name for an integration.
	 * Converts dashes to underscores for proper formatting.
	 *
	 * @return string The formatted webhook option name (e.g., 'enable_active_campaign_webhooks').
	 */
	protected function generate_webhook_option_name() {
		return sprintf( 'enable_%s_webhooks', sanitize_key( $this->get_id() ) );
	}

	/**
	 * Enable webhook switch
	 *
	 * @return void
	 */
	public function output_enable_webhook_switch( $label = '', $id = '' ) {
		$label   = $label ? $label : esc_attr_x( 'Enable triggers', 'Integration settings', 'uncanny-automator' );
		$id      = $id ? $id : $this->get_webhook_option_name();
		$checked = automator_get_option( $id, false );

		// Output the enable webhook switch.
		$this->output_switch(
			array(
				'id'                 => esc_attr( $id ),
				'name'               => esc_attr( $id ),
				'label-on'           => esc_attr( $label ),
				'label-off'          => esc_attr( $label ),
				'checked'            => $checked,
				'data-state-control' => 'webhook-enabled',
			)
		);
	}

	/**
	 * Output webhook configuration instructions with flexible content structure.
	 *
	 * @param array $config Configuration array:
	 *  @property string heading Alert heading
	 *  @property array sections Array of content sections
	 *  @property string class Alert class
	 *
	 * @return void - Outputs the alert HTML
	 */
	protected function output_webhook_instructions( $config ) {
		$defaults = array(
			'heading'  => esc_attr_x( 'Setup instructions', 'Integration settings', 'uncanny-automator' ),
			'sections' => array(),
			'class'    => 'uap-spacing-top',
		);

		$config = wp_parse_args( $config, $defaults );

		?>
		<uo-alert heading="<?php echo esc_attr( $config['heading'] ); ?>" class="<?php echo esc_attr( $config['class'] ); ?>">
			<?php foreach ( $config['sections'] as $section ) : ?>
				<?php $this->output_webhook_section( $section ); ?>
			<?php endforeach; ?>
		</uo-alert>
		<?php
	}

	/**
	 * Output a single webhook instruction section.
	 *
	 * @param array $section Section configuration
	 *
	 * @property string type Section type
	 * @property string content Section content
	 * @property array config Section configuration
	 * @property string action Section action
	 * @property string label Section label
	 *
	 * @return void - Outputs the section HTML
	 */
	protected function output_webhook_section( $section ) {
		$type = $section['type'] ?? 'text';

		switch ( $type ) {
			case 'text':
				echo '<p>' . wp_kses( $section['content'], $this->filter_content_kses_args() ) . '</p>';
				break;

			case 'field':
				$this->text_input_html( $section['config'] );
				break;

			case 'button':
				$this->output_action_button(
					$section['action'],
					$section['label'],
					$section['args'] ?? array()
				);
				break;

			case 'steps':
				echo '<ol class="uap-spacing-top uap-spacing-top--small uap-spacing-bottom uap-spacing-bottom--none">';
				foreach ( $section['items'] as $step ) {
					echo '<li>' . wp_kses( $step, $this->filter_content_kses_args() ) . '</li>';
				}
				echo '</ol>';
				break;
		}
	}

	/**
	 * Get standardized webhook regeneration button configuration.
	 *
	 * @param array $args Optional arguments to customize the button:
	 *  @property string label Custom button label
	 *  @property string confirm_heading Custom confirmation heading
	 *  @property string confirm_content Custom confirmation message
	 *  @property string action Custom action name (defaults to 'webhook_url_regeneration')
	 *  @property array args Additional button arguments
	 *  @property string integration_id Integration ID override for the button
	 *
	 * @return array Button configuration array for use in sections
	 */
	protected function get_webhook_regeneration_button( $args = array() ) {
		$defaults = array(
			'label'           => esc_attr_x( 'Regenerate webhook URL', 'Integration settings', 'uncanny-automator' ),
			'confirm_heading' => esc_attr_x( 'Regenerate webhook URL', 'Integration settings', 'uncanny-automator' ),
			'confirm_content' => esc_attr_x( 'Regenerating the URL will prevent triggers from working until the new webhook URL is set in your external configuration.', 'Integration settings', 'uncanny-automator' ),
			'confirm_button'  => esc_attr_x( 'Continue', 'Integration settings', 'uncanny-automator' ),
			'action'          => 'webhook_url_regeneration',
			'args'            => array(),
			'integration_id'  => '',
		);

		$args = wp_parse_args( $args, $defaults );

		// Add integration-id to button args if provided
		$button_args = array_merge(
			array(
				'size'    => 'small',
				'color'   => 'secondary',
				'class'   => 'uap-spacing-top',
				'icon'    => 'rotate',
				'confirm' => array(
					'heading' => $args['confirm_heading'],
					'content' => $args['confirm_content'],
					'button'  => $args['confirm_button'],
				),
			),
			$args['args']
		);

		// Add integration-id if provided
		if ( ! empty( $args['integration_id'] ) ) {
			$button_args['integration-id'] = $args['integration_id'];
		}

		return array(
			'type'   => 'button',
			'action' => $args['action'],
			'label'  => $args['label'],
			'args'   => $button_args,
		);
	}

	/**
	 * Output the webhook details.
	 *
	 * @return void
	 */
	public function output_webhook_details() {
		// Output the webhook details.
	}

	/**
	 * Output webhook settings with switch and conditional content
	 * This is the main method that extending classes should call in their output_main_connected_content()
	 *
	 * @param string $label Optional custom label for the switch
	 * @param string $id Optional custom ID for the switch
	 * @return void
	 */
	public function output_webhook_settings( $label = '', $id = '' ) {
		// Output the enable webhook switch
		$this->output_enable_webhook_switch( $label, $id );

		// Output the webhook details section
		$this->output_webhook_details_section();
	}

	/**
	 * Output webhook details wrapped in app integration section
	 *
	 * @return void
	 */
	public function output_webhook_details_section() {
		// Start output buffering to capture the webhook details content
		ob_start();
		$this->output_webhook_content();
		$content = ob_get_clean();

		// Output the section with the captured content
		$this->output_app_integration_section(
			array(
				'id'           => $this->get_webhook_option_name() . '-details-section',
				'section-type' => 'webhook-details',
				'state'        => 'webhook-enabled',
				'show-when'    => '1',
				'content'      => $content,
			)
		);
	}

	/**
	 * Output the webhook content (to be overridden by extending classes)
	 * This is the method that extending classes should override to provide their specific webhook content
	 *
	 * @return void
	 */
	public function output_webhook_content() {
		// Default implementation - extending classes should override this
		// This provides a basic webhook instructions template
		$this->output_webhook_instructions(
			array(
				'sections' => array(
					array(
						'type'    => 'text',
						'content' => sprintf(
							// translators: %s is the integration name
							esc_html_x( 'To enable %s triggers, please configure webhooks in your external account.', 'Integration settings', 'uncanny-automator' ),
							esc_html( $this->get_name() )
						),
					),
				),
			)
		);
	}

	/**
	 * Register webhook-specific settings hooks.
	 *
	 * @return void
	 */
	public function register_webhook_hooks() {
		// Hook into after disconnect
		add_filter(
			'automator_after_disconnect_' . $this->get_id(),
			array( $this, 'after_disconnect_webhook_cleanup' ),
			10,
			3
		);
	}

	/**
	 * Register webhook options for automatic cleanup on disconnection
	 *
	 * @param array $response The current response array
	 * @param array $data The posted data
	 * @param object $base_settings_object The base settings object
	 *
	 * @return array Modified response array
	 */
	public function after_disconnect_webhook_cleanup( $response, $data, $base_settings_object ) {
		// Collect all webhook options.
		$webhook_options = array_filter(
			array(
				$this->get_webhook_option_name(),
				$this->get_webhook_key_option_name(),
			)
		);

		// Register each webhook option for deletion.
		if ( ! empty( $webhook_options ) ) {
			foreach ( $webhook_options as $option_name ) {
				$base_settings_object->register_option( $option_name );
			}
		}

		return $response;
	}
}