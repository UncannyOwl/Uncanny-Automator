<?php
/**
 * Creates the settings page
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator\Integrations\Ontraport;

use Exception;

/**
 * Ontraport_Settings
 */
class Ontraport_Settings extends \Uncanny_Automator\Settings\Premium_Integration_Settings {


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
	 * @var string
	 */
	const OPT_API_KEY = 'automator_ontraport_api_key';

	/**
	 * @var string
	 */
	const OPT_APP_ID_KEY = 'automator_ontraport_app_id';

	/**
	 * Integration status.
	 *
	 * @return string - 'success' or empty string
	 */
	public function get_status() {

		return true === $this->helpers->integration_status() ? 'success' : '';

	}

	/**
	 * Sets up the properties of the settings page
	 *
	 * @return void
	 */
	public function set_properties() {

		$this->set_id( 'ontraport' );
		$this->set_icon( 'ONTRAPORT' );
		$this->set_name( 'Ontraport' );
		$this->register_option( self::OPT_API_KEY );
		$this->register_option( self::OPT_APP_ID_KEY );

		if ( automator_filter_has_var( 'error_message' ) ) {
			$this->display_errors( automator_filter_input( 'error_message' ) );
		}

	}

	/**
	 * Updates the Ontraport settings and checks the API credentials.
	 *
	 * @return void
	 */
	public function settings_updated() {

		$api_key = automator_get_option( self::OPT_API_KEY, false );
		$app_id  = automator_get_option( self::OPT_APP_ID_KEY, false );

		$validated = $this->helpers->check_credentials( $api_key, $app_id );

		if ( is_wp_error( $validated ) ) {

			Ontraport_Helpers::remove_credentials();

			$this->add_alert(
				array(
					'type'    => 'error',
					'heading' => _x( 'API credentials incorrect', 'Ontraport', 'uncanny-automator' ),
					'content' => $validated->get_error_message(),
				)
			);

			return;
		}

		automator_update_option( Ontraport_Helpers::CREDENTIALS_KEY, time() );

		$this->add_alert(
			array(
				'type'    => 'success',
				'heading' => _x( 'Connection established', 'Ontraport', 'uncanny-automator' ),
				'content' => _x( 'You are successfully connected.', 'Ontraport', 'uncanny-automator' ),
			)
		);

		return;
	}

	/**
	 * Display errors.
	 *
	 * @param mixed $error_message
	 * @return void
	 */
	public function display_errors( $error_message ) {
		$this->add_alert(
			array(
				'type'    => 'error',
				'heading' => _x( 'An error exception has occured', 'Ontraport', 'uncanny-automator' ),
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
		$this->is_account_connected = ! empty( Ontraport_Helpers::get_credentials() );

		?>
		<div class="uap-settings-panel">
			<div class="uap-settings-panel-top">
				<?php $this->output_panel_top(); ?>
				<?php $this->display_alerts(); ?>
				<div class="uap-settings-panel-content">
					<?php $this->output_panel_content(); ?>
				</div>
			</div>
			<div class="uap-settings-panel-bottom">
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
		$this->disconnect_url = Ontraport_Helpers::get_disconnect_url();

		?>
		<?php if ( ! $this->is_account_connected ) { ?>

			<div class="uap-settings-panel-content-subtitle">
				<?php echo esc_html_x( 'Connect Uncanny Automator to Ontraport', 'Ontraport', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
				<?php echo esc_html_x( 'Integrate Uncanny Automator with Ontraport to elevate workflow automation and turbocharge productivity for businesses. Seamlessly connecting these platforms empowers users to effortlessly transfer data and ignite actions, slashing manual tasks and boosting efficiency.', 'Ontraport', 'uncanny-automator' ); ?>
			</div>

			<p>
				<strong>
					<?php echo esc_html_x( 'Activating this integration will enable the following for use in your recipes:', 'Ontraport', 'uncanny-automator' ); ?>
				</strong>
			</p>

			<ul>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong>
						<?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong>
						<?php echo esc_html_x( 'Add a tag to a contact', 'Ontraport', 'uncanny-automator' ); ?>
				</li>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong>
						<?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong>
						<?php echo esc_html_x( 'Create a tag', 'Ontraport', 'uncanny-automator' ); ?>
				</li>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong>
						<?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong>
						<?php echo esc_html_x( 'Create or update a contact', 'Ontraport', 'uncanny-automator' ); ?>
				</li>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong>
						<?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong>
						<?php echo esc_html_x( 'Delete a contact', 'Ontraport', 'uncanny-automator' ); ?>
				</li>
			</ul>
			<hr/>
			<h5 style="margin-top: 10px;"><?php echo esc_html_x( 'Setup instructions', 'Ontraport', 'uncanny-automator' ); ?></h5>
			<h5 style="margin-top: 10px;">
				<small>
					<?php echo esc_html_x( 'Part 1: Obtaining Your API Key and App ID', 'Ontraport', 'uncanny-automator' ); ?>
				</small>
			</h5>
			<p>
				<a href="https://ontraport.com/support/integrations/obtain-ontraport-api-key-and-app-id/" target="_blank">
					<?php echo esc_html_x( 'Find out how to obtain your own Ontraport API Key and App ID', 'Ontraport', 'uncanny-automator' ); ?>
				</a>
			</p>
			<h5 style="margin-top: 10px;">
				<small>
					<?php echo esc_html_x( 'Part 2: Completing the Form', 'Ontraport', 'uncanny-automator' ); ?>
				</small>
			</h5>
			<p>
				<ol>
					<li>
						<?php echo esc_html_x( 'Copy and paste the acquired API Key and App ID into the designated fields below.', 'Ontraport', 'uncanny-automator' ); ?>
					</li>
					<li>
						<?php echo esc_html_x( 'Click the "Connect Ontraport Account" button to proceed.', 'Ontraport', 'uncanny-automator' ); ?>
					</li>
				</ol>
			</p>
			<p>
				<uo-text-field
					id="automator_ontraport_app_id"
					value="<?php echo esc_attr( automator_get_option( self::OPT_APP_ID_KEY, '' ) ); ?>"
					label="<?php echo esc_attr_x( 'App ID', 'Ontraport', 'uncanny-automator' ); ?>"
					required
					class="uap-spacing-top"
				></uo-text-field>

				<uo-text-field
					id="automator_ontraport_api_key"
					value="<?php echo esc_attr( automator_get_option( self::OPT_API_KEY, '' ) ); ?>"
					label="<?php echo esc_attr_x( 'API Key', 'Ontraport', 'uncanny-automator' ); ?>"
					required
					class="uap-spacing-top"
				></uo-text-field>

			</p>

		<?php } else { ?>

			<uo-alert heading="<?php echo esc_attr_x( 'Uncanny Automator only supports connecting to one Ontraport account at a time.', 'Ontraport', 'uncanny-automator' ); ?>" class="uap-spacing-bottom"></uo-alert>

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
			<uo-button type="submit">
				<?php echo esc_html_x( 'Connect Ontraport account', 'Ontraport', 'uncanny-automator' ); ?>
			</uo-button>
			<?php

		} else {
			// Show Account details & connection status
			?>

			<div class="uap-settings-panel-user">

				<div class="uap-settings-panel-user__avatar">
					<uo-icon integration="ONTRAPORT"></uo-icon>
				</div>

				<div class="uap-settings-panel-user-info">
					<div class="uap-settings-panel-user-info__main">
						<?php echo _x( 'Ontraport account', 'Ontraport', 'uncanny-automator' ); ?>
					</div>

					<div class="uap-settings-panel-user-info__additional">
						<?php
						printf(
							/* translators: 1. Email address */
							esc_html_x( 'APP ID: %1$s', 'Ontraport', 'uncanny-automator' ),
							automator_get_option( self::OPT_APP_ID_KEY, '' )
						);
						?>
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
