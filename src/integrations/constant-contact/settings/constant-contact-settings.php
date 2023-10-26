<?php
namespace Uncanny_Automator\Integrations\Constant_Contact;

/**
 * Class Constant_Contact_Settings
 *
 * @package Uncanny_Automator
 */
class Constant_Contact_Settings extends \Uncanny_Automator\Settings\Premium_Integration_Settings {

	/**
	 * Account Details.
	 *
	 * @var mixed $account - false if not connected or array of account details
	 */
	protected $account;

	/**
	 * API Key.
	 *
	 * @var string $api_key
	 */
	protected $api_key;

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

		$this->set_id( 'constant-contact' );
		$this->set_icon( 'CONSTANT_CONTACT' );
		$this->set_name( 'Constant Contact' );

	}

	/**
	 * Main panel content.
	 *
	 * @return string - HTML
	 */
	public function output_panel_content() {

		do_action( 'automator_constant_contact_settings_before' );

		$this->is_account_connected = ! empty( $this->get_status() );
		$this->account              = ! empty( $this->is_account_connected ) ? $this->helpers->get_credentials() : false;
		$this->disconnect_url       = $this->helpers->get_disconnect_url();

		$just_connected = ( 'yes' === automator_filter_input( 'success' ) );

		if ( ! $this->is_account_connected ) {
			include trailingslashit( __DIR__ ) . '/view-not-connected.php';
		} else {
			include trailingslashit( __DIR__ ) . '/view-connected.php';
		}

	}

	public function output_panel() {
		?>
		<div class="uap-settings-panel">
			<div class="uap-settings-panel-top">
				<?php $this->output_panel_top(); ?>
				<?php $this->display_alerts(); ?>
				<div class="uap-settings-panel-content">
					<?php $this->output_panel_content(); ?>
				</div>
			</div>
			<div class="uap-settings-panel-bottom" <?php echo $this->is_account_connected ? '' : 'has-arrow'; ?> >
				<?php $this->output_panel_bottom(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Bottom left panel content.
	 *
	 * @return string - HTML
	 */
	public function output_panel_bottom_left() {

		$account_info = get_transient( Constant_Contact_Helpers::TRANSIENT_ACCOUNT_INFO );

		// If the user is not connected, show a field for the API key.
		if ( ! $this->is_account_connected ) {
			?>
			<uo-button type="button" href="<?php echo esc_url( Constant_Contact_Helpers::get_authorization_url() ); ?>">
				<?php echo esc_html_x( 'Connect Constant Contact account', 'Constant Contact', 'uncanny-automator' ); ?>
			</uo-button>
			<?php

		} else {

			// Show Account details & connection status
			?>

			<div class="uap-settings-panel-user">

			<?php if ( is_array( $account_info ) && ! empty( $account_info ) ) { ?>

				<div class="uap-settings-panel-user__avatar">
					<uo-icon integration="CONSTANT_CONTACT"></uo-icon>
				</div>

				<div class="uap-settings-panel-user-info">

					<div class="uap-settings-panel-user-info__main">
						<?php echo esc_html( $account_info['first_name'] ); ?>
						<?php echo esc_html( $account_info['last_name'] ); ?>
					</div>

					<div class="uap-settings-panel-user-info__additional">
						<?php echo esc_html( $account_info['contact_email'] ); ?>
					</div>
				</div>

				<?php } ?>

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
