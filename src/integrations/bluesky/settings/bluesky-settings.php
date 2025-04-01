<?php
/**
 * Creates the settings page
 */

namespace Uncanny_Automator\Integrations\Bluesky;

use Exception;
/**
 * Bluesky_Settings
 */
class Bluesky_Settings extends \Uncanny_Automator\Settings\Premium_Integration_Settings {

	/**
	 * The connected status
	 *
	 * @var bool
	 */
	private $is_connected;

	/**
	 * The username / handle
	 *
	 * @var string
	 */
	private $username;

	/**
	 * The app password - only used for errors with the initial connection.
	 *
	 * @var string
	 */
	private $app_password;

	/**
	 * The nonce key
	 *
	 * @var string
	 */
	const NONCE_KEY = 'automator_bluesky';

	/**
	 * Integration status.
	 *
	 * @return string - 'success' or empty string
	 */
	public function get_status() {
		return $this->helpers->integration_status();
	}

	/**
	 * Set the properties of the class and the integration
	 */
	public function set_properties() {

		// The unique page ID that will be added to the URL.
		$this->set_id( 'bluesky' );

		// The integration icon will be used for the settings page.
		$this->set_icon( 'BLUESKY' );

		// The name of the settings tab
		$this->set_name( 'Bluesky' );

		$this->is_connected = $this->helpers->is_connected();

		// Handle form submission.
		add_action( 'init', array( $this, 'handle_form_submission' ) );
	}

	/**
	 * Handle the form submission.
	 *
	 * @return void
	 */
	public function handle_form_submission() {

		if ( ! $this->is_current_page_settings() ) {
			return;
		}

		// Verify the nonce.
		if ( ! wp_verify_nonce( automator_filter_input( 'nonce', INPUT_POST ), self::NONCE_KEY ) ) {
			return;
		}

		// Determine the automator_bluesky_account_action
		$automator_bluesky_account_action = automator_filter_input( 'automator_bluesky_account_action', INPUT_POST );

		if ( 'connect' === $automator_bluesky_account_action ) {
			$this->handle_authorization();
		}

		if ( 'disconnect' === $automator_bluesky_account_action ) {
			$this->disconnect();
		}
	}

	/**
	 * Handle the authorization.
	 *
	 * @return void
	 */
	private function handle_authorization() {

		$this->username     = automator_filter_input( 'automator_bluesky_username', INPUT_POST );
		$this->app_password = automator_filter_input( 'automator_bluesky_app_password', INPUT_POST );

		if ( empty( $this->username ) || empty( $this->app_password ) ) {
			$this->add_alert(
				array(
					'type'    => 'error',
					'heading' => esc_html_x( 'Something went wrong', 'Bluesky', 'uncanny-automator' ),
					'content' => esc_html_x( 'Please enter a valid username and app password.', 'Bluesky', 'uncanny-automator' ),
				)
			);
			return;
		}

		try {

			$response = $this->helpers->api()->api_request(
				array(
					'action'       => 'authenticate',
					'username'     => $this->username,
					'app_password' => $this->app_password,
				),
				null,
				false
			);

			$data = isset( $response['data'] ) ? $response['data'] : array();
			if ( empty( $data ) ) {
				throw new Exception( esc_html_x( 'Invalid response please refresh the page and try again.', 'Bluesky', 'uncanny-automator' ) );
			}

			$this->helpers->save_credentials( $data );

			$this->add_alert(
				array(
					'type'    => 'success',
					'heading' => esc_html_x( 'Connected', 'Bluesky', 'uncanny-automator' ),
					'content' => esc_html_x( 'The integration has been connected successfully.', 'Bluesky', 'uncanny-automator' ),
				)
			);

			$this->is_connected = true;

		} catch ( Exception $e ) {

			$this->add_alert(
				array(
					'type'    => 'error',
					'heading' => esc_html_x( 'Something went wrong', 'Bluesky', 'uncanny-automator' ),
					'content' => $e->getMessage(),
				)
			);

			return;
		}
	}

	/**
	 * Disconnect the integration
	 *
	 * @return void
	 */
	private function disconnect() {

		$this->helpers->remove_credentials();

		wp_safe_redirect( $this->get_settings_page_url() );

		exit;
	}

	/**
	 * Adjust the form action and add nonce field.
	 *
	 * @return string - HTML
	 */
	public function output_form() {
		?>
		<form method="post" action="<?php echo esc_url( $this->get_settings_page_url() ); ?>" warn-unsaved>
			<?php $this->output_panel(); ?>
			<?php wp_nonce_field( self::NONCE_KEY, 'nonce' ); ?>
		</form>
		<?php
	}

	/**
	 * Display - Settings panel.
	 *
	 * @return string - HTML
	 * @throws Exception
	 */
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
			<div class="uap-settings-panel-bottom">
			<?php $this->output_panel_bottom(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Display - Main panel content.
	 *
	 * @return string - HTML
	 */
	public function output_panel_content() {

		if ( $this->is_connected ) {
			$this->output_panel_content_connected();
			return;
		}

		$this->output_panel_content_disconnected();
	}

	/**
	 * Display - Connected main panel content.
	 *
	 * @return string - HTML
	 */
	public function output_panel_content_connected() {
		?>
		<uo-alert
			heading="<?php echo esc_attr_x( 'Uncanny Automator only supports connecting to one Bluesky account at a time.', 'Bluesky', 'uncanny-automator' ); ?>" 
			class="uap-spacing-bottom">
		</uo-alert>
		<?php
	}

	/**
	 * Display - Disconnected main panel content.
	 *
	 * @return string - HTML
	 */
	public function output_panel_content_disconnected() {
		?>
		<div class="uap-settings-panel-content-subtitle">
		<?php echo esc_html_x( 'Connect Uncanny Automator to Bluesky', 'Bluesky', 'uncanny-automator' ); ?>
		</div>
		
		<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
		<?php echo esc_html_x( 'Connect Uncanny Automator to Bluesky to streamline automations to post to your account', 'Bluesky', 'uncanny-automator' ); ?>
		</div>
		
		<p>
			<strong>
			<?php echo esc_html_x( 'Activating this integration will enable the following for use in your recipes:', 'Bluesky', 'uncanny-automator' ); ?>
			</strong>
		</p>
		
		<ul>
			<li>
				<uo-icon id="bolt"></uo-icon> 
				<strong>
				<?php // phpcs:ignore Uncanny_Automator.Strings.TranslationFunction.NoContext
				esc_html_e( 'Action:', 'uncanny-automator' );
				?>
				</strong>
			<?php echo esc_html_x( 'Create a post on Bluesky', 'Bluesky', 'uncanny-automator' ); ?>
			</li>
		</ul>

		<uo-alert heading="<?php echo esc_attr_x( 'Setup instructions', 'Bluesky', 'uncanny-automator' ); ?>">
		<?php echo esc_html_x( 'To obtain your Bluesky App Password, follow these steps:', 'Bluesky', 'uncanny-automator' ); ?>

			<ol class="uap-spacing-top uap-spacing-top--small uap-spacing-bottom uap-spacing-bottom--none">
				<li>
					<?php
					printf(
						/* translators: %s: HTML link to Bluesky app passwords page */
						esc_html_x(
							'Visit your %s in your Bluesky account or navigate to Settings > Security > App Passwords',
							'Bluesky',
							'uncanny-automator'
						),
						sprintf(
							'<a href="https://bsky.app/settings/app-passwords" target="_blank">%s</a>',
							esc_html_x( 'App Password settings', 'Bluesky', 'uncanny-automator' )
						)
					);
					?>
				</li>
				<li><?php echo esc_html_x( 'Click on the "Add App Password" button', 'Bluesky', 'uncanny-automator' ); ?></li>
				<li><?php echo esc_html_x( 'Give your password a unique name such as "Automator"', 'Bluesky', 'uncanny-automator' ); ?></li>
				<li><?php echo esc_html_x( 'Click "Next"', 'Bluesky', 'uncanny-automator' ); ?></li>
				<li><?php echo esc_html_x( 'Copy your new password using the copy button and paste it directly into the App Password field below', 'Bluesky', 'uncanny-automator' ); ?></li>
			</ol>

			<div class="uap-spacing-top">
			<?php
			printf(
				/* translators: %1$s: Note text with strong tags */
				esc_html_x( '%1$sNote:%2$s Save this password somewhere safe as it will not be shown again. You will need to create a new App Password if you need to reconnect in the future.', 'Bluesky', 'uncanny-automator' ),
				'<strong>', // phpcs:ignore Uncanny_Automator.Strings.TranslationHtml.HTMLInTranslation
				'</strong>' // phpcs:ignore Uncanny_Automator.Strings.TranslationHtml.HTMLInTranslation
			);
			?>
			</div>
		</uo-alert>

		<uo-text-field
			id="automator_bluesky_username"
			value="<?php echo esc_attr( $this->username ); ?>"
			label="<?php echo esc_attr_x( 'Username', 'Bluesky', 'uncanny-automator' ); ?>"
			placeholder="<?php echo esc_attr_x( 'example.bsky.social', 'Bluesky', 'uncanny-automator' ); ?>"
			required
			class="uap-spacing-top"
		></uo-text-field>

		<uo-text-field
			id="automator_bluesky_app_password"
			value="<?php echo esc_attr( $this->app_password ); ?>"
			label="<?php echo esc_attr_x( 'App Password', 'Bluesky', 'uncanny-automator' ); ?>"
			required
			class="uap-spacing-top"
		></uo-text-field>
		<?php
	}

	/**
	 * Display - Bottom left panel content.
	 *
	 * @return string - HTML
	 */
	public function output_panel_bottom_left() {

		// Add the connect button if not connected
		if ( ! $this->is_connected ) {
			?>
			<uo-button 
				type="submit" 
				name="automator_bluesky_account_action"
				value="connect"
			>
				<?php echo esc_html_x( 'Connect Bluesky account', 'Bluesky', 'uncanny-automator' ); ?>
			</uo-button>

			<?php

			return;
		}

		// Show the connected account details.
		$avatar = $this->helpers->get_credential_setting( 'avatar' );
		$email  = $this->helpers->get_credential_setting( 'email' );
		?>
		<div class="uap-settings-panel-user">
			<div class="uap-settings-panel-user__avatar">
				<?php if ( ! empty( $avatar ) ) : ?>
					<img src="<?php echo esc_url( $avatar ); ?>" alt="<?php echo esc_attr_x( 'Avatar', 'Bluesky', 'uncanny-automator' ); ?>">
				<?php else : ?>
					<uo-icon integration="BLUESKY"></uo-icon>
				<?php endif; ?>
			</div>
			<div class="uap-settings-panel-user-info">
				<div class="uap-settings-panel-user-info__main">
					<?php echo esc_html( $this->helpers->get_credential_setting( 'handle' ) ); ?>
				</div>

				<?php if ( ! empty( $email ) ) : ?>
				<div class="uap-settings-panel-user-info__additional">
					<?php
					printf(
						/* translators: 1. Email address */
						esc_html_x( 'Account email: %1$s', 'Bluesky', 'uncanny-automator' ),
						esc_html( $email )
					);
					?>
				</div>
				<?php endif; ?>
			</div>
		</div>

		<?php
	}

	/**
	 * Display - Outputs the bottom right panel content
	 *
	 * @return string - HTML
	 */
	public function output_panel_bottom_right() {

		if ( ! $this->is_connected ) {
			return;
		}
		?>

		<uo-button 
			type="submit"
			color="danger" 
			name="automator_bluesky_account_action"
			value="disconnect"
		>
			<uo-icon id="sign-out"></uo-icon>
			<?php
			// phpcs:ignore Uncanny_Automator.Strings.TranslationFunction.NoContext
			esc_html_e( 'Disconnect', 'uncanny-automator' );
			?>
		</uo-button>

		<?php
	}
}
