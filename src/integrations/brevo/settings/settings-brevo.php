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
	 * Save Submitted Settings.
	 *
	 * @return void
	 */
	public function settings_updated() {
		// Gets and saves account details if connected.
		$account = $this->helpers->get_account();
		if ( ! empty( $account['status'] ) ) {
			// Set initial transient data.
			$options = $this->helpers->get_contact_attributes();
			$options = $this->helpers->get_lists();
			$options = $this->helpers->get_templates();
		}
	}

	/**
	 * Main panel content.
	 *
	 * @return string - HTML
	 */
	public function output_panel_content() {

		$this->api_key              = $this->helpers->get_api_key();
		$this->account              = $this->helpers->get_saved_account_details();
		$this->is_account_connected = ! empty( $this->account['status'] );
		$this->disconnect_url       = $this->helpers->get_disconnect_url();

		// Formatting.
		$kses_text = array(
			'strong' => true,
			'i'      => true,
		);
		$kses_link = array(
			'a'       => array(
				'href'   => true,
				'target' => true,
			),
			'uo-icon' => array(
				'id' => true,
			),
		);

		?>
		<?php if ( ! $this->is_account_connected ) { ?>

			<?php
			// If we have an API Key but unable to connect show error message with disconnect button.
			if ( ! empty( $this->account['error'] ) ) {
				if ( 'unauthorized-ip' === $this->account['error'] ) {
					?>
					<uo-alert type="error" heading="<?php echo esc_attr_x( 'IP Whitelist Restriction', 'Brevo', 'uncanny-automator' ); ?>">
						<?php echo esc_html_x( 'Unable to connect your Brevo account due to blocking of unknown IP addresses.', 'Brevo', 'uncanny-automator' ); ?>
						<br><br>
						<?php echo esc_html_x( 'To fix this please follow these steps:', 'Brevo', 'uncanny-automator' ); ?>
						<ol class="uap-spacing-top uap-spacing-top--small">
							<li>
								<?php
								printf(
									/* translators: %s: Link to Brevo security page */
									esc_html_x( 'Go to %s in your Brevo account', 'Brevo', 'uncanny-automator' ),
									wp_kses( $this->helpers->get_authorized_ips_link(), $kses_link )
								);
								?>
							</li>
							<li>
								<?php
								printf(
									/* translators: %s: Deactivate blocking text */
									esc_html_x( 'Click %s', 'Brevo', 'uncanny-automator' ),
									'<strong>' . esc_html_x( 'Deactivate blocking', 'Brevo', 'uncanny-automator' ) . '</strong>'
								);
								?>
							</li>
							<li><?php echo esc_html_x( 'Once deactivated, please try connecting your account again.', 'Brevo', 'uncanny-automator' ); ?></li>
						</ol>
					</uo-alert>
					<br/>
					<?php
				} else {
					?>
					<uo-alert type="error" heading="<?php echo esc_attr_x( 'Unable to connect to Brevo', 'Brevo', 'uncanny-automator' ); ?>">
						<?php echo esc_html( $this->account['error'] ); ?>
					</uo-alert>
					<br/>
					<?php
				}
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
					<uo-icon id="bolt"></uo-icon> <strong><?php echo esc_html_x( 'Action:', 'Brevo', 'uncanny-automator' ); ?></strong> <?php echo esc_html_x( 'Create or update a contact', 'Brevo', 'uncanny-automator' ); ?>
				</li>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong><?php echo esc_html_x( 'Action:', 'Brevo', 'uncanny-automator' ); ?></strong> <?php echo esc_html_x( 'Delete a contact', 'Brevo', 'uncanny-automator' ); ?>
				</li>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong><?php echo esc_html_x( 'Action:', 'Brevo', 'uncanny-automator' ); ?></strong> <?php echo esc_html_x( 'Add a contact to a list', 'Brevo', 'uncanny-automator' ); ?>
				</li>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong><?php echo esc_html_x( 'Action:', 'Brevo', 'uncanny-automator' ); ?></strong> <?php echo esc_html_x( 'Remove a contact from a list', 'Brevo', 'uncanny-automator' ); ?>
				</li>
			</ul>

			<uo-alert heading="<?php echo esc_attr_x( 'Setup instructions', 'Brevo', 'uncanny-automator' ); ?>">

				<?php
				printf(
					/* translators: %s: HTML link to Brevo account */
					esc_html_x(
						'To obtain your Brevo API Key, follow these steps in your %s account:',
						'Brevo',
						'uncanny-automator'
					),
					sprintf(
						'<a href="https://app.brevo.com/" target="_blank">%s</a>',
						esc_html_x( 'Brevo', 'Brevo', 'uncanny-automator' )
					)
				);
				?>

				<ol class="uap-spacing-top uap-spacing-top--small uap-spacing-bottom uap-spacing-bottom--none">
					<li><?php echo esc_html_x( 'Click your Profile button in the upper right side of the screen to see your profile options.', 'Brevo', 'uncanny-automator' ); ?></li>
					<li>
					<?php
						printf(
							/* translators: %s: SMTP & API text */
							esc_html_x( 'Select %s.', 'Brevo', 'uncanny-automator' ),
							'<strong>' . esc_html_x( 'SMTP & API', 'Brevo', 'uncanny-automator' ) . '</strong>'
						);
					?>
					</li>
					<li>
					<?php
						printf(
							/* translators: %1$s: SMTP & API text, %2$s: API Keys text */
							esc_html_x( 'On the %1$s page, click %2$s.', 'Brevo', 'uncanny-automator' ),
							'<i>' . esc_html_x( 'SMTP & API', 'Brevo', 'uncanny-automator' ) . '</i>',
							'<strong>' . esc_html_x( 'API Keys', 'Brevo', 'uncanny-automator' ) . '</strong>'
						);
					?>
					</li>
					<li>
					<?php
						printf(
							/* translators: %s: Generate a new API key text */
							esc_html_x( 'In the upper right, click %s.', 'Brevo', 'uncanny-automator' ),
							'<strong>' . esc_html_x( 'Generate a new API key', 'Brevo', 'uncanny-automator' ) . '</strong>'
						);
					?>
					</li>
					<li>
					<?php
						printf(
							/* translators: %1$s: Name your API key text, %2$s: Generate text */
							esc_html_x( 'A pop-up window will ask you to %1$s. Enter a name such as "your-website-Automator" and click %2$s.', 'Brevo', 'uncanny-automator' ),
							'<i>' . esc_html_x( 'Name your API key', 'Brevo', 'uncanny-automator' ) . '</i>',
							'<strong>' . esc_html_x( 'Generate', 'Brevo', 'uncanny-automator' ) . '</strong>'
						);
					?>
					</li>
					<li>
						<?php
						printf(
							/* translators: %s: Connect Brevo account text */
							esc_html_x( 'You will now have an API key to enter in the field below. Once entered, click the %s button to enable your integration with Automator.', 'Brevo', 'uncanny-automator' ),
							'<strong>' . esc_html_x( 'Connect Brevo account', 'Brevo', 'uncanny-automator' ) . '</strong>'
						);
						?>
						<br>
						<?php
						printf(
							/* translators: %1$s: Note text, %2$s: Save this key text */
							esc_html_x( '%1$s: %2$s', 'Brevo', 'uncanny-automator' ),
							'<strong>' . esc_html_x( 'Note', 'Brevo', 'uncanny-automator' ) . '</strong>',
							'<i>' . esc_html_x( 'Save this key somewhere safe as it will not be accessible again and you will have to generate a new one.', 'Brevo', 'uncanny-automator' ) . '</i>'
						);
						?>
					</li>
				</ol>

				<div class="uap-spacing-top">
					<strong><?php echo esc_html_x( 'Important:', 'Brevo', 'uncanny-automator' ); ?></strong>
					<?php
					printf(
						/* translators: %1$s: Link to Brevo security page, %2$s: Deactivate blocking text */
						esc_html_x( 'To use the Brevo integration with Automator you must %2$s from %1$s', 'Brevo', 'uncanny-automator' ),
						wp_kses( $this->helpers->get_authorized_ips_link(), $kses_link ),
						'<strong>' . esc_html_x( 'deactivate unknown IP blocking', 'Brevo', 'uncanny-automator' ) . '</strong>'
					);
					?>
				</div>

			</uo-alert>

			<?php // Show API Key field. ?>
			<uo-text-field
				id="automator_brevo_api_key"
				value="<?php echo esc_attr( $this->api_key ); ?>"
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
				<uo-icon id="right-from-bracket"></uo-icon>
				<?php echo esc_html_x( 'Disconnect', 'Brevo', 'uncanny-automator' ); ?>
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
								<uo-icon id="rotate"></uo-icon>
							</uo-button>
						</uo-tooltip>
					</div>
				</div>
				<div class="uap-brevo-last-sync-details">
					<?php
						printf(
							/* translators: %s Data type name */
							esc_html_x( "Use the sync button if %s were updated within the last 24hrs and aren't yet showing in your recipes.", 'Brevo', 'uncanny-automator' ),
							esc_html( strtolower( $name ) )
						);
					?>
				</div>
			</div>
		</div>
		<?php
	}
}
