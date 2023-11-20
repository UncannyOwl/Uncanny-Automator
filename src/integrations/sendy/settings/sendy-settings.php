<?php
/**
 * Creates the settings page
 *
 * @since   4.15.1.1
 * @version 4.15.1.1
 * @package Uncanny_Automator
 * @author  Huma Irfan.
 */

namespace Uncanny_Automator\Integrations\Sendy;

/**
 * Sendy_Settings
 */
class Sendy_Settings extends \Uncanny_Automator\Settings\Premium_Integration_Settings {

	/**
	 * Sendy URL.
	 *
	 * @var string $sendy_url - Sendy URL.
	 */
	protected $sendy_url;

	/**
	 * API Key.
	 *
	 * @var string $api_key - API Key.
	 */
	protected $api_key;

	/**
	 * Is Connected.
	 *
	 * @var bool $is_connected
	 */
	protected $is_connected;

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

		$this->set_id( 'sendy' );
		$this->set_icon( 'SENDY' );
		$this->set_name( 'Sendy' );
		$this->register_option( $this->helpers->get_const( 'KEY_OPTION_KEY' ) );
		$this->register_option( $this->helpers->get_const( 'URL_OPTION_KEY' ) );
	}

	/**
	 * Save Submitted Settings.
	 *
	 * @return void
	 */
	public function settings_updated() {
		$this->helpers->verify_sendy_settings();
	}

	/**
	 * Main panel content.
	 *
	 * @return string - HTML
	 */
	public function output_panel_content() {

		$this->api_key        = $this->helpers->get_api_key();
		$this->sendy_url      = $this->helpers->get_sendy_url();
		$this->is_connected   = ! empty( $this->get_status() );
		$this->disconnect_url = $this->helpers->get_disconnect_url();

		// Formatting.
		$kses_text = array(
			'strong' => true,
			'i'      => true,
			'span'   => array(
				'class' => true,
			),
		);
		$kses_link = array(
			'a' => array(
				'href'   => true,
				'target' => true,
			),
		);

		?>
		<?php if ( ! $this->is_connected ) { ?>

			<?php
			// If we have an error show error message with disconnect button.
			$api_error = $this->helpers->get_sendy_settings( 'error' );
			if ( ! empty( $api_error ) ) {
				?>
				<uo-alert type="error" 
					heading="<?php echo esc_attr_x( 'Unable to connect to Sendy', 'Sendy', 'uncanny-automator' ); ?>">
					<?php echo esc_html( $api_error ); ?>
				</uo-alert>
				<br/>
				<?php
			}
			?>

			<div class="uap-settings-panel-content-subtitle">
				<?php echo esc_html_x( 'Connect Uncanny Automator to Sendy', 'Sendy', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
				<?php echo esc_html_x( 'Connect Uncanny Automator to Sendy to link contact and list management to WordPress activities like submitting forms, making purchases and joining groups.', 'Sendy', 'uncanny-automator' ); ?>
			</div>

			<p>
				<strong><?php echo esc_html_x( 'Activating this integration will enable the following for use in your recipes:', 'Sendy', 'uncanny-automator' ); ?></strong>
			</p>

			<ul>
				<li>
					<uo-icon id="bolt"></uo-icon>
					<strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php echo esc_html_x( 'Add a contact to a list and update contact details within a list', 'Sendy', 'uncanny-automator' ); ?>
				</li>
				<li>
					<uo-icon id="bolt"></uo-icon>
					<strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php echo esc_html_x( 'Delete a contact from a list', 'Sendy', 'uncanny-automator' ); ?>
				</li>
				<li>
					<uo-icon id="bolt"></uo-icon>
					<strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php echo esc_html_x( 'Unsubscribe a contact from a list', 'Sendy', 'uncanny-automator' ); ?>
				</li>
			</ul>

			<uo-alert heading="<?php echo esc_attr_x( 'Setup instructions', 'Sendy', 'uncanny-automator' ); ?>">
				<?php esc_html_x( 'To obtain your Sendy API Key, follow these steps in your Sendy installtion', 'Sendy', 'uncanny-automator' ); ?>

				<ol class="uap-spacing-top uap-spacing-top--small uap-spacing-bottom uap-spacing-bottom--none">
					<li><?php echo wp_kses( _x( 'Log in to Sendy as the <strong>Main user</strong> with the email/password you set when you first set up Sendy.', 'Sendy', 'uncanny-automator' ), $kses_text ); ?></li>
					<li><?php echo wp_kses( _x( 'On the upper right hand corner of the page, click the button that says <span class="dashicons dashicons-admin-users"></span><strong>Sendy</strong>.', 'Sendy', 'uncanny-automator' ), $kses_text ); ?></li>
					<li><?php echo wp_kses( _x( 'Select <strong>Settings</strong>.', 'Sendy', 'uncanny-automator' ), $kses_text ); ?></li>
					<li><?php echo wp_kses( _x( 'On the <i>Settings</i> page you will see your API Key on the right under the title <strong>Your API key</strong>.', 'Sendy', 'uncanny-automator' ), $kses_text ); ?></li>
					<li><?php echo wp_kses( _x( 'Please enter the API key and your Sendy installation URL in the fields below. Once entered, click the <strong>Connect Sendy account</strong> button to enable your integration with Automator.', 'Sendy', 'uncanny-automator' ), $kses_text ); ?></li>
				</ol>

			</uo-alert>

			<?php // Show Sendy URL field. ?>
			<uo-text-field
				id="<?php echo esc_attr( $this->helpers->get_const( 'URL_OPTION_KEY' ) ); ?>"
				value="<?php echo esc_attr( $this->sendy_url ); ?>"
				label="<?php echo esc_attr_x( 'Sendy installation URL', 'Sendy', 'uncanny-automator' ); ?>"
				name="automator_sendy_api[url]"
				required
				class="uap-spacing-top"
			></uo-text-field>

			<?php // Show API Key field. ?>
			<uo-text-field
				id="<?php echo esc_attr( $this->helpers->get_const( 'KEY_OPTION_KEY' ) ); ?>"
				value="<?php echo esc_attr( $this->api_key ); ?>"
				label="<?php echo esc_attr_x( 'API key', 'Sendy', 'uncanny-automator' ); ?>"
				required
				class="uap-spacing-top"
			></uo-text-field>

		<?php } else { ?>

			<?php $this->load_js( '/sendy/settings/assets/script.js' ); ?>
			<?php $this->load_css( '/sendy/settings/assets/style.css' ); ?>


			<uo-alert
				heading="<?php echo esc_attr_x( 'Uncanny Automator only supports connecting to one Sendy account at a time.', 'Sendy', 'uncanny-automator' ); ?>"
				class="uap-spacing-bottom">
			</uo-alert>

			<div class="uap-settings-panel-content-subtitle">
				<?php echo esc_html_x( 'Sendy Data', 'Sendy', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
				<p><?php echo esc_html_x( 'The following data is available for use in your recipes:', 'Sendy', 'uncanny-automator' ); ?></p>
			</div>

			<div id="sendy-transient-sync-list">
				<?php $this->transient_refresh( 'lists' ); ?>
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
		if ( ! $this->is_connected ) {
			?>
			<uo-button type="submit">
				<?php echo esc_html_x( 'Connect Sendy account', 'Sendy', 'uncanny-automator' ); ?>
			</uo-button>
			<?php

		} else {

			// Show Connected Sendy URL.
			?>

			<div class="uap-settings-panel-user">

				<div class="uap-settings-panel-user__avatar">
					<uo-icon integration="SENDY"></uo-icon>
				</div>

				<div class="uap-settings-panel-user-info">
					<div class="uap-settings-panel-user-info__main">
						<?php echo esc_html( $this->sendy_url ); ?>
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

		if ( $this->is_connected ) {
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
		$key     = "automator_sendy_{$key_part}";
		$options = get_transient( $key );
		$count   = ! empty( $options ) ? count( $options ) : 0;

		switch ( $key_part ) {
			case 'lists':
				$name = esc_html_x( 'Contact lists', 'Sendy', 'uncanny-automator' );
				$icon = 'list-view';
				break;
		}
		?>
		<div class="uap-sendy-transient-sync-wrapper uap-spacing-top">
			<div class="uap-sendy-transient-sync">
				<div class="uap-sendy-transient">
					<div class="uap-sendy-transient-content">
						<span class="uap-sendy-transient-name-count">
							<span
								class="dashicons dashicons-<?php echo $icon; ?>"></span><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span class="uap-sendy-transient-name">
								<?php echo esc_html( $name ); ?> ( <span
									class="uap-sendy-sync-items-count"><?php echo esc_html( $count ); ?></span> )
							</span>
						</span>
					</div>
					<div class="uap-sendy-transient-actions">
						<uo-tooltip>
							<?php echo esc_html_x( 'Refresh', 'Sendy', 'uncanny-automator' ); ?>
							<uo-button color="secondary" size="extra-small" slot="target"
								class="uap-sendy-transient-sync-refresh"
								data-key="<?php echo esc_attr( $key_part ); ?>">
								<uo-icon id="sync"></uo-icon>
							</uo-button>
						</uo-tooltip>
					</div>
				</div>
				<div class="uap-sendy-last-sync-details">
					<?php
					printf(
						/* translators: %s Data type name */
						esc_html_x( "Use the sync button if %s were updated in the last 24hrs and aren't yet showing in your recipes.", 'Sendy', 'uncanny-automator' ),
						esc_html( strtolower( $name ) )
					);
					?>
				</div>
			</div>
		</div>
		<?php
	}

}
