<?php
namespace Uncanny_Automator\Settings;

/**
 * Trait for common premium integration templating patterns.
 *
 * @package Uncanny_Automator\Settings
 */
trait Premium_Integration_Templating {

	use Premium_Integration_Templating_Helpers;

	/**
	 * Whether to show the connection arrow in the bottom panel
	 * Mainly used for OAuth integrations
	 *
	 * @var bool
	 */
	protected $show_connect_arrow = false;

	/**
	 * Whether to show the warning unsaved message in the settings page
	 *
	 * @var bool
	 */
	protected $warn_unsaved = true;

	/**
	 * Get available actions
	 *
	 * @return array
	 */
	abstract protected function get_available_actions();

	/**
	 * Get available triggers
	 *
	 * @return array
	 */
	abstract protected function get_available_triggers();

	/**
	 * Output the wrapper for the settings page
	 *
	 * @return void - Outputs HTML directly
	 */
	final public function output_wrapper() {
		do_action( 'automator_settings_premium_integration_before_output', $this );
		$this->output();
		do_action( 'automator_settings_premium_integration_after_output', $this );
	}

	/**
	 * Outputs the content of the settings page of this integration
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output() {
		$this->output_form();
	}

	/**
	 * Output the settings form
	 *
	 * @return void
	 */
	public function output_form() {
		?>
		<uap-app-integration-settings-form integration-id="<?php echo esc_attr( $this->get_id() ); ?>">
			<form method="POST">
				<?php wp_nonce_field( 'automator_integration_settings', 'automator_nonce' ); ?>
				<input type="hidden" name="action" value="automator_integration_settings_<?php echo esc_attr( $this->get_id() ); ?>">
				<?php $this->output_panel(); ?>
			</form>
		</uap-app-integration-settings-form>
		<?php
	}

	/**
	 * Output the main panel structure
	 *
	 * @return void
	 */
	public function output_panel() {

		// Dynamic hook status key.
		$status = $this->get_hook_status_key();
		$id     = $this->get_id();

		/**
		 * Hook before panel output.
		 *
		 * @param App_Integration_Settings $this The integration settings instance.
		 */
		do_action( "automator_app_settings_{$id}_before_{$status}_panel", $this );

		?>
		<div class="uap-settings-panel">
			<div class="uap-settings-panel-top">
				<?php $this->output_panel_top(); ?>
				<?php $this->display_alerts(); ?>
				<div class="uap-settings-panel-content">
					<?php $this->output_panel_content(); ?>
				</div>
			</div>
			<div class="uap-settings-panel-bottom"<?php echo $this->should_show_connect_arrow() ? ' has-arrow' : ''; ?>>
				<?php $this->output_panel_bottom(); ?>
			</div>
		</div>
		<?php
		/**
		 * Hook after panel output.
		 *
		 * @param App_Integration_Settings $this The integration settings instance.
		 */
		do_action( "automator_app_settings_{$id}_after_{$status}_panel", $this );
	}

	/**
	 * Output the panel top section
	 *
	 * @return void
	 */
	public function output_panel_top() {
		?>
		<div class="uap-settings-panel-title">
			<?php $this->output_panel_title(); ?>
		</div>
		<?php
	}

	/**
	 * Output the panel title
	 *
	 * @return void
	 */
	public function output_panel_title() {
		?>
		<uo-icon integration="<?php echo esc_attr( $this->get_icon() ); ?>"></uo-icon> <?php echo esc_attr( $this->get_name() ); ?>
		<?php if ( $this->get_is_third_party() ) : ?>
			<uo-chip size="small" filled>
				<?php echo esc_html_x( '3rd-party', 'Integration settings', 'uncanny-automator' ); ?>
			</uo-chip>
		<?php endif; ?>
		<?php
	}

	/**
	 * Display - Main panel content.
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_panel_content() {
		// Dynamic hook status key.
		$status = $this->get_hook_status_key();
		$id     = $this->get_id();

		// Hook before main content based on integration and connection status.
		do_action( "automator_app_settings_{$id}_before_{$status}_panel_content", $this );

		// Output main content based on connection status
		if ( $this->is_connected ) {
			$this->output_main_connected_content();
		} else {
			$this->output_main_disconnected_content();
		}

		// Hook after main content based on integration and connection status.
		do_action( "automator_app_settings_{$id}_after_{$status}_panel_content", $this );
	}

	/**
	 * Output the bottom panel
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_panel_bottom() {
		// Dynamic hook status key.
		$status = $this->get_hook_status_key();
		$id     = $this->get_id();

		// Hook before bottom panel output.
		do_action( "automator_app_settings_{$id}_before_{$status}_panel_bottom", $this );
		?>
		<div class="uap-settings-panel-bottom-left">
			<?php $this->output_panel_bottom_left(); ?>
		</div>
		<div class="uap-settings-panel-bottom-right">
			<?php $this->output_panel_bottom_right(); ?>
		</div>
		<?php
		// Hook after bottom panel output.
		do_action( "automator_app_settings_{$id}_after_{$status}_panel_bottom", $this );
	}

	/**
	 * Display - Bottom left panel content.
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_panel_bottom_left() {
		// Dynamic hook status key.
		$status = $this->get_hook_status_key();
		$id     = $this->get_id();

		// Hook before bottom left content based on integration and connection status.
		do_action( "automator_app_settings_{$id}_before_{$status}_panel_bottom_left", $this );

		// Output bottom left content based on connection status
		if ( $this->is_connected ) {
			$this->output_bottom_left_connected_content();
		} else {
			$this->output_bottom_left_disconnected_content();
		}

		// Hook after bottom left content based on integration and connection status.
		do_action( "automator_app_settings_{$id}_after_{$status}_panel_bottom_left", $this );
	}

	/**
	 * Display - Bottom right panel content.
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_panel_bottom_right() {
		// Dynamic hook status key.
		$status = $this->get_hook_status_key();
		$id     = $this->get_id();

		// Hook before bottom right content based on integration and connection status.
		do_action( "automator_app_settings_{$id}_before_{$status}_panel_bottom_right", $this );

		// Output bottom right content based on connection status
		if ( $this->is_connected ) {
			$this->output_bottom_right_connected_content();
		} else {
			$this->output_bottom_right_disconnected_content();
		}

		// Hook after bottom right content based on integration and connection status.
		do_action( "automator_app_settings_{$id}_after_{$status}_panel_bottom_right", $this );
	}

	/**
	 * Display - Main connected content
	 *
	 * Override this method in the using class to provide custom connected content output
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_main_connected_content() {
		$this->output_single_account_message();
	}

	/**
	 * Display a message indicating that only one account can be connected at a time.
	 *
	 * @param string $additional_message Optional. Additional message to display in the alert.
	 * @return void - Outputs HTML directly
	 */
	protected function output_single_account_message( $additional_message = '' ) {
		?>
		<uo-alert 
			heading="
			<?php
			echo esc_attr(
				sprintf(
					// translators: %s: Integration name
					esc_html_x( 'Uncanny Automator only supports connecting to one %s account at a time.', 'Integration settings', 'uncanny-automator' ),
					esc_html( $this->get_name() )
				)
			);
			?>
			" 
			class="uap-spacing-bottom">
			<?php if ( ! empty( $additional_message ) ) : ?>
				<?php echo wp_kses( $additional_message, $this->filter_content_kses_args() ); ?>
			<?php endif; ?>
		</uo-alert>
		<?php
	}

	/**
	 * Display - Main disconnected content
	 *
	 * Override this method in the extending class to provide custom disconnected content output
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_main_disconnected_content() {
		$this->output_disconnected_header();

		// Output available recipe items.
		$this->output_available_items();
	}

	/**
	 * Output the standard disconnected integration header with subtitle and description
	 *
	 * @param string $description - Optional : The unique description for this integration
	 * @param string $name        - Optional : The name of the integration if different from the default
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_disconnected_header( $description = '', $name = '' ) {

		// If no name is provided, use the default integration name.
		$name = empty( $name ) ? $this->get_name() : $name;

		$this->output_panel_subtitle(
			sprintf(
				// translators: %s: Integration name
				esc_html_x( 'Connect Uncanny Automator to %s', 'Integration settings', 'uncanny-automator' ),
				esc_html( $name )
			)
		);

		// If no description is provided, don't output anything.
		if ( empty( $description ) ) {
			return;
		}

		$this->output_subtle_panel_paragraph( $description );
	}

	/**
	 * Display - Bottom left connected content
	 *
	 * Override this method in the extending class to provide custom connected bottom left content output
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_bottom_left_connected_content() {
		// Get formatted account information for UI display
		$account_info = $this->get_formatted_account_info();

		// Output the connected user info using the standard template
		$this->output_connected_user_info( $account_info );
	}

	/**
	 * Display - Bottom left disconnected content.
	 *
	 * Override this method in the extending class to provide custom content.
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_bottom_left_disconnected_content() {
		$this->output_connect_button();
	}

	/**
	 * Output the connect button for API key based integrations.
	 *
	 * @return void
	 */
	protected function output_connect_button() {
		$this->output_action_button( 'authorize', $this->get_connect_button_label() );
	}

	/**
	 * Display - Bottom right connected content
	 *
	 * Override this method in the using class to provide custom connected bottom right content output
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_bottom_right_connected_content() {
		// Common disconnect button.
		$this->output_disconnect_button();

		// Check if we have any registered connected options.
		if ( ! empty( $this->get_options() ) ) {
			// Add save settings button.
			$this->output_save_settings_button();
		}
	}

	/**
	 * Display - Bottom right disconnected content
	 *
	 * Override this method in the extending class to provide custom disconnected bottom right content output
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_bottom_right_disconnected_content() {
	}

	/**
	 * Display available actions and triggers
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_available_items() {
		?>
		<p>
			<strong>
				<?php echo esc_html_x( 'Activating this integration will enable the following for use in your recipes:', 'Integration settings', 'uncanny-automator' ); ?>
			</strong>
		</p>

		<ul>
			<?php
			// List triggers
			$trigger_items = $this->get_available_triggers();
			if ( ! empty( $trigger_items ) ) {
				foreach ( $trigger_items as $trigger ) {
					$this->output_item_line(
						esc_html_x( 'Trigger', 'Integration settings', 'uncanny-automator' ),
						esc_html( $trigger )
					);
				}
			}

			// List actions
			$action_items = $this->get_available_actions();
			if ( ! empty( $action_items ) ) {
				foreach ( $action_items as $action ) {
					$this->output_item_line(
						esc_html_x( 'Action', 'Integration settings', 'uncanny-automator' ),
						esc_html( $action )
					);
				}
			}
			?>
		</ul>
		<?php
	}

	/**
	 * Output single item line
	 *
	 * @param string $type - The type of item.
	 * @param string $description - The description of the item.
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_item_line( $type, $description ) {
		?>
		<li>
			<uo-icon id="bolt"></uo-icon> 
			<strong><?php echo esc_html( $type ); ?>:</strong> 
			<?php echo esc_html( $description ); ?>
		</li>
		<?php
	}

	/**
	 * Output connected user info panel
	 *
	 * @param array $args {
	 *     Optional. Array of user info display arguments.
	 *     @property string avatar_type     'icon', 'image', or 'text'
	 *     @property string avatar_value    Value to use for the avatar
	 *     @property string main_info       Primary user info (e.g. email)
	 *     @property bool   main_info_icon  Whether to show integration icon in main info
	 *     @property string additional      Optional. Additional user info (e.g. username).
	 * }
	 * @return void - Outputs HTML directly
	 */
	protected function output_connected_user_info( $args = array() ) {
		$defaults = array(
			'avatar_type'    => 'icon', // 'icon', 'image', or 'text'
			'avatar_value'   => $this->get_icon(),
			'main_info'      => '',
			'main_info_icon' => false,
			'additional'     => '',
		);

		$args = wp_parse_args( $args, $defaults );

		// Get allowed HTML tags from filter
		$kses_args = $this->filter_content_kses_args();
		?>
		<div class="uap-settings-panel-user">
			<div class="uap-settings-panel-user__avatar">
				<?php
				switch ( $args['avatar_type'] ) {
					case 'image':
						?>
						<img src="<?php echo esc_url( $args['avatar_value'] ); ?>" 
							alt="<?php echo esc_attr( $args['main_info'] ); ?>">
						<?php
						break;
					case 'text':
						echo esc_html( $args['avatar_value'] );
						break;
					case 'icon':
					default:
						?>
						<uo-icon integration="<?php echo esc_attr( $args['avatar_value'] ); ?>"></uo-icon>
						<?php
				}
				?>
			</div>
			<div class="uap-settings-panel-user-info">
				<div class="uap-settings-panel-user-info__main">
					<?php echo wp_kses( $args['main_info'], $kses_args ); ?>
					<?php if ( $args['main_info_icon'] ) : ?>
						<uo-icon integration="<?php echo esc_attr( $this->get_icon() ); ?>"></uo-icon>
					<?php endif; ?>
				</div>
				<?php if ( ! empty( $args['additional'] ) ) : ?>
					<div class="uap-settings-panel-user-info__additional">
						<?php echo wp_kses( $args['additional'], $kses_args ); ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Determine if the bottom arrow should be shown to highlight the connect button.
	 *
	 * @return bool
	 */
	public function should_show_connect_arrow() {
		return $this->show_connect_arrow && 'success' !== $this->get_status();
	}
}