<?php
/**
 * Creates the settings page
 */

namespace Uncanny_Automator\Integrations\Get_Response;

/**
 * Get_Response_Settings
 */
class Get_Response_Settings extends \Uncanny_Automator\Settings\Premium_Integration_Settings {

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

		$this->set_id( 'getresponse' );
		$this->set_icon( 'GETRESPONSE' );
		$this->set_name( 'GetResponse' );
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
			$options = $this->helpers->get_contact_fields();
			$options = $this->helpers->get_lists();
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
				<uo-alert type="error" heading="<?php echo esc_attr_x( 'Unable to connect to GetResponse', 'GetResponse', 'uncanny-automator' ); ?>">
					<?php echo esc_html_x( 'The API key you entered is invalid. Please re-enter your API key again.', 'GetResponse', 'uncanny-automator' ); ?>
				</uo-alert>
				<br/>
				<?php
			}
			?>

			<div class="uap-settings-panel-content-subtitle">
				<?php echo esc_html_x( 'Connect Uncanny Automator to GetResponse', 'GetResponse', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
				<?php echo esc_html_x( 'Connect Uncanny Automator to GetResponse to connect contact and list management to WordPress activities like submitting forms, making purchases and joining groups.', 'GetResponse', 'uncanny-automator' ); ?>
			</div>

			<p>
				<strong><?php echo esc_html_x( 'Activating this integration will enable the following for use in your recipes:', 'GetResponse', 'uncanny-automator' ); ?></strong>
			</p>

			<ul>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php echo esc_html_x( 'Create or update a contact', 'GetResponse', 'uncanny-automator' ); ?>
				</li>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php echo esc_html_x( 'Remove a contact', 'GetResponse', 'uncanny-automator' ); ?>
				</li>
			</ul>

			<uo-alert heading="<?php echo esc_attr_x( 'Setup instructions', 'GetResponse', 'uncanny-automator' ); ?>">

				<?php echo wp_kses( _x( 'To obtain your GetResponse API Key, follow these steps from your <a href="https://app.getresponse.com/api/" target="_blank">GetResponse API</a> page:', 'GetResponse', 'uncanny-automator' ), $kses_link ); ?>

				<ol class="uap-spacing-top uap-spacing-top--small uap-spacing-bottom uap-spacing-bottom--none">
					<li><?php echo wp_kses( _x( 'Click the large <strong>Generate API key</strong> button on the <i>API</i> page.', 'GetResponse', 'uncanny-automator' ), $kses_text ); ?></li>
					<li>
					<?php
					echo wp_kses(
						sprintf(
							/* translators: %s: Blog name */
							_x( "Enter a unique name for your key, such as '<i>%s Automator</i>'.", 'GetResponse', 'uncanny-automator' ),
							get_bloginfo( 'name' )
						),
						$kses_text
					);
					?>
					</li>
					<li><?php echo wp_kses( _x( 'Click the <strong>Generate</strong> button.', 'GetResponse', 'uncanny-automator' ), $kses_text ); ?></li>
					<li><?php echo wp_kses( _x( 'Click the <strong>Copy</strong> button next to the generated API key.', 'GetResponse', 'uncanny-automator' ), $kses_text ); ?></li>
					<li><?php echo wp_kses( _x( 'You will now have an <strong>API key</strong> to enter in the field below.', 'GetResponse', 'uncanny-automator' ), $kses_text ); ?></li>
					<li><?php echo wp_kses( _x( 'Once entered, click the <strong>Connect GetResponse account</strong> button to enable your integration with Automator.', 'GetResponse', 'uncanny-automator' ), $kses_text ); ?></li>
				</ol>

			</uo-alert>

			<?php // Show API Key field. ?>
			<uo-text-field
				id="automator_getresponse_api_key"
				value="<?php echo esc_attr( $this->api_key ); ?>"
				label="<?php echo esc_attr_x( 'API key', 'GetResponse', 'uncanny-automator' ); ?>"
				required
				class="uap-spacing-top"
			></uo-text-field>

		<?php } else { ?>

			<?php $this->load_js( '/get-response/settings/assets/script.js' ); ?>
			<?php $this->load_css( '/get-response/settings/assets/style.css' ); ?>


			<uo-alert heading="<?php echo esc_attr_x( 'Uncanny Automator only supports connecting to one GetResponse account at a time.', 'GetResponse', 'uncanny-automator' ); ?>" class="uap-spacing-bottom">
			</uo-alert>

			<div class="uap-settings-panel-content-subtitle">
				<?php echo esc_html_x( 'GetResponse Data', 'GetResponse', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
				<p><?php echo esc_html_x( 'The following data is available for use in your recipes:', 'GetResponse', 'uncanny-automator' ); ?></p>
			</div>

			<div id="getresponse-transient-sync-list">
				<?php $this->transient_refresh( 'contact/lists' ); ?>
				<?php $this->transient_refresh( 'contact/fields' ); ?>
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
				<?php echo esc_html_x( 'Connect GetResponse account', 'GetResponse', 'uncanny-automator' ); ?>
			</uo-button>
			<?php

		} else {

			// Show Account details & connection status
			?>

			<div class="uap-settings-panel-user">

				<div class="uap-settings-panel-user__avatar">
					<uo-icon integration="GETRESPONSE"></uo-icon>
				</div>

				<div class="uap-settings-panel-user-info">
					<div class="uap-settings-panel-user-info__main">
						<?php echo esc_html_x( 'Account Info', 'GetResponse', 'uncanny-automator' ); ?>
					</div>

					<div class="uap-settings-panel-user-info__additional">
						<?php
						printf(
							/* translators: 1. Account ID */
							esc_html_x( 'ID: %1$s', 'GetResponse', 'uncanny-automator' ),
							esc_html( $this->account['id'] )
						);
						?>
						<br>
						<?php
						printf(
							/* translators: 1. Email address */
							esc_html_x( 'Email: %1$s', 'GetResponse', 'uncanny-automator' ),
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

		$key     = "automator_getresponse_{$key_part}";
		$options = get_transient( $key );
		$count   = ! empty( $options ) ? count( $options ) : 0;

		switch ( $key_part ) {
			case 'contact/lists':
				$name = esc_html_x( 'Contact lists', 'GetResponse', 'uncanny-automator' );
				$icon = 'list-view';
				break;
			case 'contact/fields':
				$name = esc_html_x( 'Contact fields', 'GetResponse', 'uncanny-automator' );
				$icon = 'admin-users';
				break;
		}
		?>
		<div class="uap-getresponse-transient-sync-wrapper uap-spacing-top">
			<div class="uap-getresponse-transient-sync">
				<div class="uap-getresponse-transient">
					<div class="uap-getresponse-transient-content">
						<span class="uap-getresponse-transient-name-count">
							<span class="dashicons dashicons-<?php echo $icon; ?>"></span><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span class="uap-getresponse-transient-name">
								<?php echo esc_html( $name ); ?> ( <span class="uap-getresponse-sync-items-count"><?php echo esc_html( $count ); ?></span> )
							</span>
						</span>
					</div>
					<div class="uap-getresponse-transient-actions">
						<uo-tooltip>
							<?php echo esc_html_x( 'Refresh', 'GetResponse', 'uncanny-automator' ); ?>
							<uo-button color="secondary" size="extra-small" slot="target" class="uap-getresponse-transient-sync-refresh" data-key="<?php echo esc_attr( $key_part ); ?>">
								<uo-icon id="rotate"></uo-icon>
							</uo-button>
						</uo-tooltip>
					</div>
				</div>
				<div class="uap-getresponse-last-sync-details">
					<?php
						printf(
							/* translators: %s Data type name */
							esc_html_x( "Use the button with the sync icon to the right if %s were updated within the last 24hrs and aren't yet showing in your recipes.", 'GetResponse', 'uncanny-automator' ),
							esc_html( strtolower( $name ) )
						);
					?>
				</div>
			</div>
		</div>
		<?php
	}

}
