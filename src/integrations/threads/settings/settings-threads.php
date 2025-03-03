<?php
/**
 * Creates the settings page
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator\Integrations\Threads;

use Exception;

/**
 * Threads_Settings
 */
class Threads_Settings extends \Uncanny_Automator\Settings\Premium_Integration_Settings {

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

		$this->set_id( 'threads' );
		$this->set_icon( 'THREADS' );
		$this->set_name( 'Threads' );

		if ( automator_filter_has_var( 'error_message' ) ) {
			$this->display_errors( automator_filter_input( 'error_message' ) );
		}
	}

	public function display_errors( $error_message ) {
		$this->add_alert(
			array(
				'type'    => 'error',
				'heading' => _x( 'An error exception has occured', 'Threads', 'uncanny-automator' ),
				'content' => $error_message,
			)
		);
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	public function output_panel() {

		// Determines whether the accound is connected or not.
		$this->is_account_connected = Threads_Helpers::is_account_connected();
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
		$this->disconnect_url = Threads_Helpers::get_disconnect_url();

		?>
		<?php if ( ! $this->is_account_connected ) { ?>

			<div class="uap-settings-panel-content-subtitle">
				<?php echo esc_html_x( 'Connect Uncanny Automator to Threads', 'Threads', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
				<?php echo esc_html_x( 'Connect Uncanny Automator to Threads to streamline automations that allow you to create thread posts directly from your WordPress site.', 'Threads', 'uncanny-automator' ); ?>
			</div>

			<p>
				<strong>
					<?php echo esc_html_x( 'Activating this integration will enable the following for use in your recipes:', 'Threads', 'uncanny-automator' ); ?>
				</strong>
			</p>

			<ul>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong>
						<?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong>
						<?php echo esc_html_x( 'Create a thread post', 'Threads', 'uncanny-automator' ); ?>
				</li>
			</ul>

		<?php } else { ?>

			<uo-alert heading="<?php echo esc_attr_x( 'Uncanny Automator only supports connecting to one Threads account at a time.', 'Threads', 'uncanny-automator' ); ?>" class="uap-spacing-bottom">
			<?php echo esc_html_x( 'If you create recipes and then change the connected Threads account, your previous recipes may no longer work.', 'Threads', 'uncanny-automator' ); ?>
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
			<uo-button href="<?php echo esc_url( Threads_Helpers::get_authorization_url() ); ?>" type="button">
				<?php echo esc_html_x( 'Connect Threads account', 'Threads', 'uncanny-automator' ); ?>
			</uo-button>
			<?php

		} else {
			// Show Account details & connection status
			?>

			<div class="uap-settings-panel-user">

				<div class="uap-settings-panel-user__avatar">
					T
				</div>

				<div class="uap-settings-panel-user-info">
					<div class="uap-settings-panel-user-info__main">
						<?php echo esc_html_x( 'Threads account', 'Threads', 'uncanny-automator' ); ?>
						<uo-icon integration="THREADS"></uo-icon>
					</div>

					<div class="uap-settings-panel-user-info__additional">
						<?php $account = automator_get_option( Threads_Helpers::CREDENTIALS, array() ); ?>
						<?php if ( ! empty( $account['user_id'] ) ) { ?>
							<?php
							printf(
							/* translators: %d: User ID */
								esc_html_x( 'User ID: %d', 'Threads', 'uncanny-automator' ),
								absint( $account['user_id'] )
							);
							?>
						<?php } ?>
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
