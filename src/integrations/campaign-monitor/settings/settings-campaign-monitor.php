<?php
/**
 * Creates the settings page
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator\Integrations\Campaign_Monitor;

use Exception;

/**
 * Campaign_Monitor_Settings
 */
class Campaign_Monitor_Settings extends \Uncanny_Automator\Settings\Premium_Integration_Settings {


	/**
	 * Is Account Connected.
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
	 * Account details.
	 *
	 * @var array $account
	 */
	protected $account;

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

		$this->set_id( 'campaignmonitor' );
		$this->set_icon( 'CAMPAIGN_MONITOR' );
		$this->set_name( 'Campaign Monitor' );

		if ( automator_filter_has_var( 'error_message' ) ) {
			$this->display_errors( urldecode( automator_filter_input( 'error_message' ) ) );
		}
	}

	/**
	 * Display error messages.
	 *
	 * @param string $error_message - Error message.
	 *
	 * @return void
	 */
	public function display_errors( $error_message ) {
		$this->add_alert(
			array(
				'type'    => 'error',
				'heading' => _x( 'An error has occured', 'Campaign Monitor', 'uncanny-automator' ),
				'content' => $error_message,
			)
		);
	}

	/**
	 * Display Settings panel.
	 *
	 * @return void
	 * @throws Exception
	 */
	public function output_panel() {
		// Account connected.
		$this->is_account_connected = ! empty( Campaign_Monitor_Helpers::get_credentials() );
		// Account details.
		if ( $this->is_account_connected ) {
			$this->account = $this->helpers->get_account_details();
			if ( is_wp_error( $this->account ) ) {
				$this->display_errors( $this->account->get_error_message() );
				$this->is_account_connected = false;
				$this->helpers->remove_credentials();
			}
		}

		// Disconnect URL.
		$this->disconnect_url = $this->is_account_connected ? Campaign_Monitor_Helpers::get_disconnect_url() : '';
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
		?>
		<?php if ( ! $this->is_account_connected ) { ?>

			<div class="uap-settings-panel-content-subtitle">
				<?php echo esc_html_x( 'Connect Uncanny Automator to Campaign Monitor', 'Campaign Monitor', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
				<?php echo esc_html_x( 'Connect Uncanny Automator to Campaign Monitor to streamline automations that incorporate list management, email marketing, customer profile, and activity on your WordPress site.', 'Campaign Monitor', 'uncanny-automator' ); ?>
			</div>

			<p>
				<strong>
					<?php echo esc_html_x( 'Activating this integration will enable the following for use in your recipes:', 'Campaign Monitor', 'uncanny-automator' ); ?>
				</strong>
			</p>

			<ul>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong>
						<?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> 
						<?php echo esc_html_x( 'Add or update a subscriber to a list', 'Campaign Monitor', 'uncanny-automator' ); ?>
				</li>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong>
						<?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> 
						<?php echo esc_html_x( 'Remove a subscriber from a list', 'Campaign Monitor', 'uncanny-automator' ); ?>
				</li>
			</ul>

		<?php } else { ?>

			<uo-alert heading="<?php echo esc_attr_x( 'Uncanny Automator only supports connecting to one Campaign Monitor account at a time although Agency accounts with multiple clients may select their specific client within actions.', 'Campaign Monitor', 'uncanny-automator' ); ?>" class="uap-spacing-bottom"></uo-alert>

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
			<uo-button href="<?php echo esc_url( Campaign_Monitor_Helpers::get_authorization_url() ); ?>" type="button">
				<?php echo esc_html_x( 'Connect Campaign Monitor account', 'Campaign Monitor', 'uncanny-automator' ); ?>
			</uo-button>
			<?php

		} else {

			// Show the connected account details.
			$user_info = sprintf(
				/* translators: 1. Primary Contact email */
				esc_html_x( 'Primary Contact: %1$s', 'Campaign Monitor', 'uncanny-automator' ),
				esc_html( $this->account['email'] )
			);

			$additional_info = false;

			if ( 'client' === $this->account['type'] ) {

				// Add email and Client ID to additional info.
				$additional_info = array(
					$user_info,
					sprintf(
						/* translators: 1. Client ID */
						esc_html_x( 'Client ID: %1$s', 'Campaign Monitor', 'uncanny-automator' ),
						esc_html( $this->account['client']['value'] )
					),
				);

				// Set Client name.
				$user_info = $this->account['client']['text'];
			}
			?>

			<div class="uap-settings-panel-user">

				<div class="uap-settings-panel-user__avatar">
					<uo-icon integration="CAMPAIGN_MONITOR"></uo-icon>
				</div>

				<div class="uap-settings-panel-user-info">
					<div class="uap-settings-panel-user-info__main">
						<?php echo esc_html( $user_info ); ?>
					</div>

				<?php
				// Additional info for client accounts.
				if ( $additional_info ) {
					foreach ( $additional_info as $info ) {
						?>
						<div class="uap-settings-panel-user-info__additional">
							<?php echo esc_html( $info ); ?>
						</div>
						<?php
					}
				}
				?>
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
