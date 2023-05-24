<?php
/**
 * Creates the settings page
 *
 * @since   4.8
 * @version 4.8
 * @package Uncanny_Automator
 * @author  Ajay V.
 */

namespace Uncanny_Automator\Integrations\Microsoft_Teams;

/**
 * Microsoft_Teams_Settings
 */
class Microsoft_Teams_Settings extends \Uncanny_Automator\Settings\Premium_Integration_Settings {

	protected $functions;
	protected $is_user_connected;
	protected $auth_url;
	protected $user;
	protected $disconnect_url;

	public function get_status() {
		return $this->helpers->integration_status();
	}
	/**
	 * Sets up the properties of the settings page
	 */
	public function set_properties() {

		$this->set_id( 'microsoft-teams' );
		$this->set_icon( 'MICROSOFT_TEAMS' );
		$this->set_name( 'Microsoft Teams' );
	}

	/**
	 * output_panel_content
	 */
	public function output_panel_content() {

		$this->auth_url       = $this->helpers->get_auth_url();
		$this->user           = $this->helpers->get_user();
		$this->disconnect_url = $this->helpers->get_disconnect_url();

		$this->is_user_connected = ! empty( $this->user );
		?>
		<?php if ( ! $this->is_user_connected ) { ?>

			<div class="uap-settings-panel-content-subtitle">
				<?php esc_html_e( 'Connect Uncanny Automator to Microsoft Teams', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
				<?php esc_html_e( 'Connect Uncanny Automator to Microsoft Teams to send messages, create channels and more when people perform WordPress actions like submitting forms, making purchases and joining groups. Turn Microsoft Teams into a communications hub that’s tightly integrated with everything that happens on your WordPress site and beyond.', 'uncanny-automator' ); ?>
			</div>

			<p>
				<strong><?php esc_html_e( 'Activating this integration will enable the following for use in your recipes:', 'uncanny-automator' ); ?></strong>
			</p>

			<ul>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Send a message to a team member', 'uncanny-automator' ); ?>
				</li>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Send a message to a channel', 'uncanny-automator' ); ?>
				</li>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Create a team', 'uncanny-automator' ); ?>
				</li>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Create a channel in a team', 'uncanny-automator' ); ?>
				</li>
			</ul>

		<?php } else { ?>

			<uo-alert heading="<?php echo esc_attr( sprintf( __( 'Uncanny Automator only supports connecting to one Microsoft Teams account at a time.', 'uncanny-automator' ) ) ); ?>" class="uap-spacing-bottom">
				<?php esc_html_e( 'You can only connect to a Microsoft Teams account for which you have read and write access.', 'uncanny-automator' ); ?>
			</uo-alert>

			<?php
		}

	}

	/**
	 * output_panel_bottom_left
	 */
	public function output_panel_bottom_left() {

		if ( ! $this->is_user_connected ) {
			?>

				<uo-button class="uap-settings-button-microsoft" href="<?php echo esc_url( $this->auth_url ); ?>">
					<uo-icon id="microsoft"></uo-icon><?php esc_html_e( 'Sign in with Microsoft', 'uncanny-automator' ); ?>
				</uo-button>

		<?php } else { ?>

			<div class="uap-settings-panel-user">

				<div class="uap-settings-panel-user__avatar">
					<?php echo esc_html( strtoupper( $this->user['displayName'][0] ) ); ?>
				</div>

				<div class="uap-settings-panel-user-info">
					<div class="uap-settings-panel-user-info__main">
						<?php echo esc_html( $this->user['displayName'] ); ?>
						<uo-icon integration="MICROSOFT_TEAMS"></uo-icon>
					</div>

					<div class="uap-settings-panel-user-info__additional">
						<?php

						printf(
							/* translators: 1. Email address */
							esc_html__( 'Account email: %1$s', 'uncanny-automator' ),
							esc_html( $this->user['userPrincipalName'] )
						);

						?>
					</div>
				</div>
				</div>
				<?php
		}
	}

	/**
	 * output_panel_bottom_right
	 */
	public function output_panel_bottom_right() {

		if ( ! $this->is_user_connected ) {
			return;
		}

		?>
		<uo-button color="danger" href="<?php echo esc_url( $this->disconnect_url ); ?>">
			<uo-icon id="sign-out"></uo-icon>
			<?php esc_html_e( 'Disconnect', 'uncanny-automator' ); ?>
		</uo-button>
		<?php
	}

}
