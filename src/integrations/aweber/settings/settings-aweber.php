<?php
/**
 * Creates the settings page
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator\Integrations\Aweber;

use Exception;

/**
 * Aweber_Settings
 */
class Aweber_Settings extends \Uncanny_Automator\Settings\Premium_Integration_Settings {

	/**
	 * Is account conected.
	 *
	 * @var bool $is_account_connected
	 */
	protected $is_account_connected;

	/**
	 * Disconnect URL.
	 *
	 * @var string $disconnect_url
	 */
	protected $disconnect_url;

	/**
	 * Integration status.
	 *
	 * @return string - 'success' or empty string
	 */
	public function get_status() {

		return $this->helpers->integration_status();

	}

	/**
	 * Sets up the properties of the settings page
	 *
	 * @return void
	 */
	public function set_properties() {

		$this->set_id( 'aweber' );
		$this->set_icon( 'AWEBER' );
		$this->set_name( 'AWeber' );

		if ( automator_filter_has_var( 'error_message' ) ) {
			$this->display_errors( automator_filter_input( 'error_message' ) );
		}

	}

	public function display_errors( $error_message ) {
		$this->add_alert(
			array(
				'type'    => 'error',
				'heading' => _x( 'An error exception has occured', 'AWeber', 'uncanny-automator' ),
				'content' => $error_message,
			)
		);
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	public function output_panel() {
		// Account connected.
		$this->is_account_connected = ! empty( Aweber_Helpers::get_credentials() );
		?>
		<div class="uap-settings-panel">
			<div class="uap-settings-panel-top">
				<?php $this->output_panel_top(); ?>
				<?php $this->display_alerts(); ?>
				<div class="uap-settings-panel-content">
					<?php $this->output_panel_content(); ?>
				</div>
			</div>
			<div class="uap-settings-panel-bottom" <?php echo esc_attr( ! $this->is_account_connected ? 'has-arrow' : '' ); ?>>
				<?php $this->output_panel_bottom(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Main panel content.
	 *
	 * @return string - HTML
	 */
	public function output_panel_content() {

		// Disconnect URL.
		$this->disconnect_url = Aweber_Helpers::get_disconnect_url();

		?>
		<?php if ( ! $this->is_account_connected ) { ?>

			<div class="uap-settings-panel-content-subtitle">
				<?php echo esc_html_x( 'Connect Uncanny Automator to AWeber', 'AWeber', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
				<?php echo esc_html_x( 'Connect Uncanny Automator to AWeber to streamline automations that incorporate list management, email marketing, customer profile, and activity on your WordPress site.', 'AWeber', 'uncanny-automator' ); ?>
			</div>

			<p>
				<strong>
					<?php echo esc_html_x( 'Activating this integration will enable the following for use in your recipes:', 'AWeber', 'uncanny-automator' ); ?>
				</strong>
			</p>

			<ul>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong>
						<?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong>
						<?php echo esc_html_x( 'Add a subscriber to a list', 'AWeber', 'uncanny-automator' ); ?>
				</li>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong>
						<?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong>
						<?php echo esc_html_x( 'Add a tag to a subscriber', 'AWeber', 'uncanny-automator' ); ?>
				</li>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong>
						<?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong>
						<?php echo esc_html_x( 'Update a subscriber', 'AWeber', 'uncanny-automator' ); ?>
				</li>
			</ul>

		<?php } else { ?>

			<uo-alert type="info" heading="<?php echo esc_attr_x( 'Uncanny Automator supports connecting multiple AWeber account at a time.', 'AWeber', 'uncanny-automator' ); ?>" class="uap-spacing-bottom">
			<?php echo esc_html_x( 'Uncanny Automator helps you link many AWeber accounts at once. You can pick a different account for each action that needs account details. This gives you more choices and control.', 'AWeber', 'Uncanny Automator' ); ?>
			</uo-alert>

			<?php
		}

	}

	/**
	 * Bottom left panel content.
	 *
	 * @return string - HTML
	 */
	public function output_panel_bottom_left() {

		// Show the connect message if not connected.
		if ( ! $this->is_account_connected ) {
			?>
			<uo-button href="<?php echo esc_url( Aweber_Helpers::get_authorization_url() ); ?>" type="button">
				<?php echo esc_html_x( 'Connect AWeber account', 'AWeber', 'uncanny-automator' ); ?>
			</uo-button>
			<?php

		} else {
			// Show Account details & connection status
			?>

			<div class="uap-settings-panel-user">

				<div class="uap-settings-panel-user__avatar">
					A
				</div>

				<div class="uap-settings-panel-user-info">
					<div class="uap-settings-panel-user-info__main">
						<?php echo esc_html_x( 'AWeber account', 'AWeber', 'uncanny-automator' ); ?>
						<uo-icon integration="AWEBER"></uo-icon>
					</div>

					<div class="uap-settings-panel-user-info__additional">
						<?php echo esc_html_x( 'Connected', 'AWeber', 'uncanny-automator' ); ?>
					</div>
				</div>
			</div>

			<?php
		}
	}

	/**
	 * Bottom right panel content.
	 *
	 * @return string - HTML
	 */
	public function output_panel_bottom_right() {

		if ( $this->is_account_connected ) {
			?>
			<uo-button color="danger" href="<?php echo esc_url( $this->disconnect_url ); ?>">
				<uo-icon id="sign-out"></uo-icon>
				<?php esc_html_e( 'Disconnect', 'uncanny-automator' ); ?>
			</uo-button>
			<?php

		}
	}

}
