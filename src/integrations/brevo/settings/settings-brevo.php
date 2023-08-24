<?php
/**
 * Creates the settings page
 *
 * @since   4.15.1.1
 * @version 4.15.1.1
 * @package Uncanny_Automator
 * @author  Curt K.
 */

namespace Uncanny_Automator\Integrations\Brevo;

/**
 * Brevo_Settings
 */
class Brevo_Settings extends \Uncanny_Automator\Settings\Premium_Integration_Settings {

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

		$this->set_id( 'brevo' );
		$this->set_icon( 'BREVO' );
		$this->set_name( 'Brevo' );
		$this->register_option( $this->helpers->get_const( 'OPTION_KEY' ) );

	}

	/**
	 * Main panel content.
	 *
	 * @return string - HTML
	 */
	public function output_panel_content() {

		$this->api_key              = $this->helpers->get_api_key();
		$this->is_account_connected = ! empty( $this->get_status() );
		$this->account              = ! empty( $this->is_account_connected ) ? $this->helpers->get_account() : false;
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
			if ( ! empty( $this->api_key ) && $this->helpers->is_api_key_invalid() ) {
				?>
				<uo-alert type="error" heading="<?php echo esc_attr_x( 'Unable to connect to Brevo', 'Brevo', 'uncanny-automator' ); ?>">
					<?php echo esc_html_x( 'The API key you entered is invalid. Please re-enter your API key again.', 'Brevo', 'uncanny-automator' ); ?>
				</uo-alert>
				<br/>
				<?php
			}
			?>

			<div class="uap-settings-panel-content-subtitle">
				<?php echo esc_html_x( 'Connect Uncanny Automator to Brevo', 'Brevo', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
				<?php echo esc_html_x( 'Connect Uncanny Automator to Brevo to connect contact and list management to WordPress activities like submitting forms, making purchases and joining groups.', 'Brevo', 'uncanny-automator' ); ?>
			</div>

			<p>
				<strong><?php echo esc_html_x( 'Activating this integration will enable the following for use in your recipes:', 'Brevo', 'uncanny-automator' ); ?></strong>
			</p>

			<ul>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php echo esc_html_x( 'Create or update a contact', 'Brevo', 'uncanny-automator' ); ?>
				</li>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php echo esc_html_x( 'Delete a contact', 'Brevo', 'uncanny-automator' ); ?>
				</li>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php echo esc_html_x( 'Add a contact to a list', 'Brevo', 'uncanny-automator' ); ?>
				</li>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php echo esc_html_x( 'Remove a contact from a list', 'Brevo', 'uncanny-automator' ); ?>
				</li>
			</ul>

			<uo-alert heading="<?php echo esc_attr_x( 'Setup instructions', 'Brevo', 'uncanny-automator' ); ?>">

				<?php echo wp_kses( _x( 'To obtain your Brevo API Key, follow these steps in your <a href="https://app.brevo.com/" target="_blank">Brevo</a> account:', 'Brevo', 'uncanny-automator' ), $kses_link ); ?>

				<ol class="uap-spacing-top uap-spacing-top--small uap-spacing-bottom uap-spacing-bottom--none">
					<li><?php echo esc_html_x( 'Click your Profile button in the upper right side of the screen to see your profile options.', 'Brevo', 'uncanny-automator' ); ?></li>
					<li><?php echo wp_kses( _x( 'Select <strong>SMTP & API</strong>.', 'Brevo', 'uncanny-automator' ), $kses_text ); ?></li>
					<li><?php echo wp_kses( _x( 'On the <i>SMTP & API</i> page, click <strong>API Keys</strong>.', 'Brevo', 'uncanny-automator' ), $kses_text ); ?></li>
					<li><?php echo wp_kses( _x( 'In the upper right, click <strong>Generate a new API key</strong>.', 'Brevo', 'uncanny-automator' ), $kses_text ); ?></li>
					<li><?php echo wp_kses( _x( 'A pop-up window will ask you to <i>Name your API key</i>. Enter a name such as "your-website-automator" and click <strong>Generate</strong>.', 'Brevo', 'uncanny-automator' ), $kses_text ); ?></li>
					<li>
						<?php echo wp_kses( _x( 'You will now have an API key to enter in the field below. Once entered, click the <strong>Connect Brevo account</strong> button to enable your integration with Automator.', 'Brevo', 'uncanny-automator' ), $kses_text ); ?>
						<br>
						<?php echo wp_kses( _x( '<strong>Note :</strong><i> Save this key somewhere safe as it will not be accessible again and you will have to generate a new one.</i>', 'Brevo', 'uncanny-automator' ), $kses_text ); ?>
					</li>
				</ol>

			</uo-alert>

			<?php // Show API Key field. ?>
			<uo-text-field
				id="automator_brevo_api_key"
				value="<?php echo esc_attr( str_replace( $this->helpers->invalid_key_message, '', $this->api_key ) ); ?>"
				label="<?php echo esc_attr_x( 'API key', 'Brevo', 'uncanny-automator' ); ?>"
				required
				class="uap-spacing-top"
			></uo-text-field>

		<?php } else { ?>

			<?php $this->load_js( '/brevo/settings/assets/script.js' ); ?>
			<?php $this->load_css( '/brevo/settings/assets/style.css' ); ?>


			<uo-alert heading="<?php echo esc_attr_x( 'Uncanny Automator only supports connecting to one Brevo account at a time.', 'Brevo', 'uncanny-automator' ); ?>" class="uap-spacing-bottom">
			</uo-alert>

			<div class="uap-settings-panel-content-subtitle">
				<?php echo esc_html_x( 'Brevo Data', 'Brevo', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
				<p><?php echo esc_html_x( 'The following data is available for use in your recipes:', 'Brevo', 'uncanny-automator' ); ?></p>
			</div>

			<div id="brevo-transient-sync-list">
				<?php $this->transient_refresh( 'contacts/lists' ); ?>
				<?php $this->transient_refresh( 'contacts/attributes' ); ?>
				<?php $this->transient_refresh( 'templates' ); ?>
			</div>

			<?php
		}

	}

	/**
	 * Bottom left panel content.
	 *
	 * @return string - HTML
	 */
	public function output_panel_bottom_left() {

		// If the user is not connected, show a field for the API key.
		if ( ! $this->is_account_connected ) {
			?>
			<uo-button type="submit">
				<?php echo esc_html_x( 'Connect Brevo account', 'Brevo', 'uncanny-automator' ); ?>
			</uo-button>
			<?php

		} else {

			// Show Account details & connection status
			?>

			<div class="uap-settings-panel-user">

				<div class="uap-settings-panel-user__avatar">
					<uo-icon integration="BREVO"></uo-icon>
				</div>

				<div class="uap-settings-panel-user-info">
					<div class="uap-settings-panel-user-info__main">
						<?php echo esc_html( $this->account['company'] ); ?>
					</div>

					<div class="uap-settings-panel-user-info__additional">
						<?php
						printf(
							/* translators: 1. Email address */
							esc_html_x( 'Account email: %1$s', 'Brevo', 'uncanny-automator' ),
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

	/**
	 * Refresh transient Details.
	 *
	 * @param string $key_part - transient key part.
	 *
	 * @return string - HTML
	 */
	public function transient_refresh( $key_part ) {

		$key     = "automator_brevo_{$key_part}";
		$options = get_transient( $key );
		$count   = ! empty( $options ) ? count( $options ) : 0;

		switch ( $key_part ) {
			case 'contacts/lists':
				$name = esc_html_x( 'Contact lists', 'Brevo', 'uncanny-automator' );
				$icon = 'list-view';
				break;
			case 'contacts/attributes':
				$name = esc_html_x( 'Custom contact attributes', 'Brevo', 'uncanny-automator' );
				$icon = 'admin-users';
				break;
			case 'templates':
				$name = esc_html_x( 'Email Templates', 'Brevo', 'uncanny-automator' );
				$icon = 'email-alt';
				break;
		}
		?>
		<div class="uap-brevo-transient-sync-wrapper uap-spacing-top">
			<div class="uap-brevo-transient-sync">
				<div class="uap-brevo-transient">
					<div class="uap-brevo-transient-content">
						<span class="uap-brevo-transient-name-count">
							<span class="dashicons dashicons-<?php echo $icon; ?>"></span><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span class="uap-brevo-transient-name">
								<?php echo esc_html( $name ); ?> ( <span class="uap-brevo-sync-items-count"><?php echo esc_html( $count ); ?></span> )
							</span>
						</span>
					</div>
					<div class="uap-brevo-transient-actions">
						<uo-tooltip>
							<?php echo esc_html_x( 'Refresh', 'Brevo', 'uncanny-automator' ); ?>
							<uo-button color="secondary" size="extra-small" slot="target" class="uap-brevo-transient-sync-refresh" data-key="<?php echo esc_attr( $key_part ); ?>">
								<uo-icon id="sync"></uo-icon>
							</uo-button>
						</uo-tooltip>
					</div>
				</div>
				<div class="uap-brevo-last-sync-details">
					<?php
						printf(
							/* translators: %s Data type name */
							esc_html_x( "Use the sync button if %s were updated in the last hour and aren't yet showing in your recipes.", 'Brevo', 'uncanny-automator' ),
							esc_html( strtolower( $name ) )
						);
					?>
				</div>
			</div>
		</div>
		<?php
	}

}
