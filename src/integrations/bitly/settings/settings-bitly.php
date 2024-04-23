<?php
/**
 * Creates the settings page for Bitly
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator\Integrations\Bitly;

/**
 * Bitly_Settings
 */
class Bitly_Settings extends \Uncanny_Automator\Settings\Premium_Integration_Settings {

	/**
	 * Account Details.
	 *
	 * @var mixed $account - false if not connected or array of account details
	 */
	protected $account;

	/**
	 * API Key.
	 *
	 * @var string $access_token
	 */
	protected $access_token;

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
		$this->set_id( 'bitly' );
		$this->set_icon( 'BITLY' );
		$this->set_name( 'Bitly' );
		$this->register_option( $this->helpers->get_const( 'ACCESS_TOKEN' ) );
	}

	/**
	 * Main panel content.
	 *
	 * @return string - HTML
	 */
	public function output_panel_content() {

		$this->access_token         = $this->helpers->get_access_token();
		$this->account              = $this->helpers->get_saved_account_details();
		$this->is_account_connected = ! empty( $this->account['status'] );
		$this->disconnect_url       = $this->helpers->get_disconnect_url();

		// Formatting.
		$kses_text = array(
			'strong' => true,
			'i'      => true,
		);
		$kses_link = array(
			'a' => array(
				'href'   => true,
				'target' => true,
			),
		);

		?>
		<?php if ( ! $this->is_account_connected ) { ?>

			<?php
			// If we have an API Key but unable to connect show error message with disconnect button.
			if ( ! empty( $this->account['error'] ) ) {
				?>
				<uo-alert type="error"
						  heading="<?php echo esc_attr_x( 'Unable to connect to Bitly', 'Bitly', 'uncanny-automator' ); ?>">
					<?php echo esc_html_x( 'The Access Token you entered is invalid. Please re-enter your Access Token again.', 'Bitly', 'uncanny-automator' ); ?>
				</uo-alert>
				<br/>
				<?php
			}
			?>

			<div class="uap-settings-panel-content-subtitle">
				<?php echo esc_html_x( 'Connect Uncanny Automator to Bitly', 'Bitly', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
				<?php echo esc_html_x( 'Connect Uncanny Automator to Bitly to shorten your URLs and use them in your recipes.', 'Bitly', 'uncanny-automator' ); ?>
			</div>

			<p>
				<strong><?php echo esc_html_x( 'Activating this integration will enable the following for use in your recipes:', 'Bitly', 'uncanny-automator' ); ?></strong>
			</p>

			<ul>
				<li>
					<uo-icon id="bolt"></uo-icon>
					<strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php echo esc_html_x( 'Shorten a URL', 'Bitly', 'uncanny-automator' ); ?>
				</li>
			</ul>

			<uo-alert heading="<?php echo esc_attr_x( 'Setup instructions', 'Bitly', 'uncanny-automator' ); ?>">

				<?php echo wp_kses( _x( 'To obtain your Bitly Access Token, follow these steps in your <a href="https://app.bitly.com/settings/api" target="_blank">Bitly</a> account:', 'Bitly', 'uncanny-automator' ), $kses_link ); ?>

				<ol class="uap-spacing-top uap-spacing-top--small uap-spacing-bottom uap-spacing-bottom--none">
					<li><?php echo wp_kses( _x( 'Enter your account password in the "Enter password" field and then click <strong>Generate token</strong>.', 'Bitly', 'uncanny-automator' ), $kses_text ); ?></li>
					<li>
						<?php echo wp_kses( _x( 'You will now have an Access Token to enter in the field below. Once entered, click the <strong>Connect Bitly account</strong> button to enable your integration with Automator.', 'Bitly', 'uncanny-automator' ), $kses_text ); ?>
						<br>
						<?php echo wp_kses( _x( '<strong>Note :</strong><i> Save this token somewhere safe as it will not be accessible again and you will have to generate a new one.</i>', 'Bitly', 'uncanny-automator' ), $kses_text ); ?>
					</li>
				</ol>

			</uo-alert>

			<?php // Show API Key field. ?>
			<uo-text-field
				id="automator_bitly_access_token"
				value="<?php echo esc_attr( str_replace( $this->helpers->invalid_key_message, '', $this->access_token ) ); ?>"
				label="<?php echo esc_attr_x( 'Access Token', 'Bitly', 'uncanny-automator' ); ?>"
				required
				class="uap-spacing-top"
			></uo-text-field>

		<?php } else { ?>

			<uo-alert
				heading="<?php echo esc_attr_x( 'Uncanny Automator only supports connecting to one Bitly account at a time.', 'Bitly', 'uncanny-automator' ); ?>"
				class="uap-spacing-bottom">
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

		// If the user is not connected, show a field for the Access Token.
		if ( ! $this->is_account_connected ) {
			?>
			<uo-button type="submit">
				<?php echo esc_html_x( 'Connect Bitly account', 'Bitly', 'uncanny-automator' ); ?>
			</uo-button>
			<?php

		} else {

			// Show Account details & connection status
			?>

			<div class="uap-settings-panel-user">

				<div class="uap-settings-panel-user__avatar">
					<uo-icon integration="BITLY"></uo-icon>
				</div>

				<div class="uap-settings-panel-user-info">
					<div class="uap-settings-panel-user-info__main">
						<?php
						printf(
						/* translators: 1. Email address */
							esc_html_x( 'Account name: %1$s', 'Bitly', 'uncanny-automator' ),
							esc_html( $this->account['name'] )
						);
						?>
					</div>

					<div class="uap-settings-panel-user-info__additional">
						<?php
						printf(
						/* translators: 1. Email address */
							esc_html_x( 'Account email: %1$s', 'Bitly', 'uncanny-automator' ),
							esc_html( $this->account['email'] )
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
