<?php
namespace Uncanny_Automator\Integrations\Stripe;

use Uncanny_Automator\Api_Server;
/**
 * Stripe Integration Settings
 */
class Stripe_Settings extends \Uncanny_Automator\Settings\Premium_Integration_Settings {


	/**
	 * The helpers class
	 *
	 * @var Stripe_Helpers
	 */
	public $helpers;

	/**
	 * Live or test mode
	 *
	 * @var array
	 */
	private $mode;

	/**
	 * The connected status
	 *
	 * @var bool
	 */
	private $is_connected;

	/**
	 * The nonce key
	 *
	 * @var string
	 */
	const NONCE_KEY = 'automator_stripe';

	/**
	 * The option where we store the connection mode
	 *
	 * @var string
	 */
	const CONNECTION_MODE_OPTION = 'automator_stripe_mode';

	/**
	 * Set the properties of the class and the integration
	 */
	public function set_properties() {

		// The unique page ID that will be added to the URL
		$this->set_id( 'stripe' );

		// The integration icon will be used for the settings page, so set this option to the integration ID
		$this->set_icon( 'STRIPE' );

		// The name of the settings tab
		$this->set_name( 'Stripe' );

		$this->helpers = new Stripe_Helpers();

		$this->mode = $this->helpers->get_mode();

		$this->is_connected = $this->helpers->is_connected();

		$this->register_option( $this->helpers->webhook->get_option_name() );
		$this->register_option( self::CONNECTION_MODE_OPTION );

		$this->check_for_errors();

		$this->set_css( '/stripe/settings/assets/style.css' );
		$this->set_js( '/stripe/settings/assets/script.js' );

		// Handle the disconnect button action
		add_action( 'init', array( $this, 'disconnect' ) );
		add_action( 'init', array( $this, 'capture_oauth_tokens' ) );
	}

	/**
	 * check_for_errors
	 *
	 * @return void
	 */
	public function check_for_errors() {

		$connection_result = automator_filter_input( 'connect' );

		if ( '1' === $connection_result ) {
			$this->add_alert(
				array(
					'type'    => 'success',
					'heading' => esc_html_x( 'Connected', 'Stripe', 'uncanny-automator' ),
					'content' => esc_html_x( 'The integration has been connected successfully.', 'Stripe', 'uncanny-automator' ),
				)
			);
		}

		$error = automator_filter_input( 'error' );

		if ( '' !== $error ) {
			$this->add_alert(
				array(
					'type'    => 'error',
					'heading' => esc_html_x( 'Something went wrong', 'Stripe', 'uncanny-automator' ),
					'content' => $error,
				)
			);
		}

		if ( 'test' === $this->helpers->get_mode() ) {
			$this->add_alert(
				array(
					'type'    => 'error',
					'heading' => esc_html_x( 'Connected in test mode', 'Stripe', 'uncanny-automator' ),
					'content' => esc_html_x( 'The integration is connected in test mode. Recipes will not affect your live account.', 'Stripe', 'uncanny-automator' ),
				)
			);
		}
	}


	/**
	 * Display an error message
	 *
	 * @param string $error_message The error message to display
	 */
	public function display_errors( $error_message ) {
		$this->add_alert(
			array(
				'type'    => 'error',
				'heading' => esc_html_x( 'Something went wrong', 'Stripe', 'uncanny-automator' ),
				'content' => $error_message,
			)
		);
	}

	/**
	 * Get the connected status
	 *
	 * @return string
	 */
	public function get_status() {

		return $this->helpers->integration_status();
	}

	/**
	 * output_form
	 *
	 * We are overriding this method to disable unsaved form warnings when we are not connected
	 * The test mode switch was causing the warning to appear when it shouldn't
	 *
	 * @return void
	 */
	public function output_form() {

		$warn = '';

		// Only warn about unsaved changes if we are connected
		if ( $this->is_connected ) {
			$warn = 'warn-unsaved';
		}

		?>

			<form method="POST" action="options.php" <?php esc_attr( $warn ); ?>>
				<?php settings_fields( $this->get_settings_id() ); ?>
				<?php $this->output_panel(); ?>
			</form>
			<?php
	}

	/**
	 * Creates the output of the settings page
	 */
	public function output_panel_content() {

		$webhook_endpoint = $this->helpers->webhook->get_url( $this->mode );
		$webhook_secret   = $this->helpers->webhook->get_secret( $this->mode );

		?>
		<?php if ( ! $this->is_connected ) { ?>

			<div class="uap-settings-panel-content-subtitle">
			<?php echo esc_html_x( 'Connect Uncanny Automator to Stripe', 'Stripe', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
			<?php echo esc_html_x( 'Connect Uncanny Automator to Stripe to process and manage customer payments via WordPress.', 'Stripe', 'uncanny-automator' ); ?>
			</div>

			<p>
				<strong>
			<?php echo esc_html_x( 'Activating this integration will enable the following for use in your recipes:', 'Stripe', 'uncanny-automator' ); ?>
				</strong>
			</p>

			<ul>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong>
					<?php echo esc_html_x( 'Action:', 'Stripe', 'uncanny-automator' ); ?></strong>
					<?php echo esc_html_x( 'Create a customer', 'Stripe', 'uncanny-automator' ); ?>
				</li>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong>
					<?php echo esc_html_x( 'Action:', 'Stripe', 'uncanny-automator' ); ?></strong>
					<?php echo esc_html_x( 'Delete a customer', 'Stripe', 'uncanny-automator' ); ?>
				</li>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong>
					<?php echo esc_html_x( 'Action:', 'Stripe', 'uncanny-automator' ); ?></strong>
					<?php echo esc_html_x( 'Create a payment link for a product', 'Stripe', 'uncanny-automator' ); ?>
				</li>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong>
					<?php echo esc_html_x( 'Trigger:', 'Stripe', 'uncanny-automator' ); ?></strong>
					<?php echo esc_html_x( 'A subscription is cancelled', 'Stripe', 'uncanny-automator' ); ?>
				</li>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong>
					<?php echo esc_html_x( 'Trigger:', 'Stripe', 'uncanny-automator' ); ?></strong>
					<?php echo esc_html_x( 'A subscription is paid', 'Stripe', 'uncanny-automator' ); ?>
				</li>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong>
					<?php echo esc_html_x( 'Trigger:', 'Stripe', 'uncanny-automator' ); ?></strong>
					<?php echo esc_html_x( 'A subscription payment fails', 'Stripe', 'uncanny-automator' ); ?>
				</li>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong>
					<?php echo esc_html_x( 'Trigger:', 'Stripe', 'uncanny-automator' ); ?></strong>
					<?php echo esc_html_x( 'A subscription is created', 'Stripe', 'uncanny-automator' ); ?>
				</li>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong>
					<?php echo esc_html_x( 'Trigger:', 'Stripe', 'uncanny-automator' ); ?></strong>
					<?php echo esc_html_x( 'A payment for a product is refunded', 'Stripe', 'uncanny-automator' ); ?>
				</li>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong>
					<?php echo esc_html_x( 'Trigger:', 'Stripe', 'uncanny-automator' ); ?></strong>
					<?php echo esc_html_x( 'A customer is created', 'Stripe', 'uncanny-automator' ); ?>
				</li>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong>
					<?php echo esc_html_x( 'Trigger:', 'Stripe', 'uncanny-automator' ); ?></strong>
					<?php echo esc_html_x( 'One-time payment for a product is completed', 'Stripe', 'uncanny-automator' ); ?>
				</li>
			</ul>

			<?php } else { ?>

			<uo-alert heading="<?php echo esc_attr_x( 'Continue the setup by enabling Stripe triggers', 'Stripe', 'uncanny-automator' ); ?>">

			<?php echo esc_attr_x( 'To enable Stripe triggers please configure webhooks in your Stripe account:', 'Stripe', 'uncanny-automator' ); ?>

			<ol class="uap-spacing-top uap-spacing-top--small uap-spacing-bottom uap-spacing-bottom--none">
				<li><?php echo esc_html_x( 'Go to your', 'Stripe', 'uncanny-automator' ); ?> <a target="_blank" href="https://dashboard.stripe.com/webhooks"><?php echo esc_html_x( 'Stripe Dashboard', 'Stripe', 'uncanny-automator' ); ?></a></li>
				<li><?php echo esc_html_x( 'Click the "Add endpoint" button', 'Stripe', 'uncanny-automator' ); ?></li>
				<li><?php echo esc_html_x( "You'll be asked to enter a webhook URL. Please use this value:", 'Stripe', 'uncanny-automator' ); ?>
				<uo-text-field
					value="<?php echo esc_url( $webhook_endpoint ); ?>"
					disabled
				></uo-text-field></li>
				<li><?php echo esc_html_x( 'Once the webhook is created, copy its signing secret, and paste it here:', 'Stripe', 'uncanny-automator' ); ?>
					<uo-text-field
					id="<?php echo esc_attr( $this->helpers->webhook->get_option_name() ); ?>"
					name="stripe-webhook-secret"
					value="<?php echo esc_attr( $webhook_secret ); ?>"
				></uo-text-field>

				</li>
				<li><?php echo esc_html_x( 'Click the "Save settings" below.', 'Stripe', 'uncanny-automator' ); ?></li>
			</ol>

			</uo-alert>

			<?php
			}
	}

	/**
	 * Generates the OAuth2 URL.
	 *
	 * @return string The OAuth URL.
	 */
	public function get_oauth_url( $mode = 'live' ) {

		$nonce = wp_create_nonce( self::NONCE_KEY );

		return add_query_arg(
			array(
				'action'       => 'authorization_request',
				'nonce'        => $nonce,
				'redirect_url' => rawurlencode( $this->get_settings_page_url() ),
				'mode'         => $mode,
				'plugin_ver'   => AUTOMATOR_PLUGIN_VERSION,
			),
			AUTOMATOR_API_URL . $this->helpers::API_ENDPOINT
		);
	}

	/**
	 * Outputs the bottom right panel content
	 */
	public function output_panel_bottom_right() {

		if ( ! $this->is_connected ) {
			return;
		}

		$link = $this->get_settings_page_url() . '&disconnect=1';

		?>
		<uo-button color="danger" href="<?php echo esc_url( $link ); ?>">
			<uo-icon id="right-from-bracket"></uo-icon>
			<?php echo esc_html_x( 'Disconnect', 'Stripe', 'uncanny-automator' ); ?>
		</uo-button>

		<uo-button type="submit">
			<?php echo esc_html_x( 'Save settings', 'Stripe', 'uncanny-automator' ); ?>
		</uo-button>

		<?php
	}

	/**
	 * Outputs the bottom left panel content
	 */
	public function output_panel_bottom_left() {

		if ( ! $this->is_connected ) {

			?>
			<div class="uap-stripe-connect-button-wrapper">
			<?php

				$link = $this->get_oauth_url( 'live' );

				$button_label = esc_html_x( 'Connect Stripe', 'Stripe', 'uncanny-automator' );

			?>

				<div id="uap-stripe-connect-live-button">
					<?php $this->redirect_button( $button_label, $link ); ?>
				</div>

				<?php

				$link = $this->get_oauth_url( 'test' );

				$button_label = esc_html_x( 'Connect Stripe in test mode', 'Stripe', 'uncanny-automator' );

				?>

				<div id="uap-stripe-connect-test-button">
					<?php $this->redirect_button( $button_label, $link, 'danger' ); ?>
				</div>

				<uo-switch id="uap_stripe_mode" status-label="<?php echo esc_attr_x( 'Enable test mode', 'Stripe', 'uncanny-automator' ); ?>"></uo-switch>

			</div>

			<?php

		} else {

			$user_details = $this->helpers->api->get_user_details();
			$name         = $user_details['settings']['dashboard']['display_name'];
			$mode_prefix  = '';

			if ( 'test' === $this->mode ) {
				$mode_prefix = esc_html_x( '(Test mode)', 'Stripe', 'uncanny-automator' ) . ' ';
			}

			if ( empty( $user_details ) ) {
				return;
			}
			?>
			<div class="uap-settings-panel-user">

				<div class="uap-settings-panel-user__avatar">
			<?php echo esc_html( strtoupper( $name[0] ) ); ?>
				</div>

				<div class="uap-settings-panel-user-info">
					<div class="uap-settings-panel-user-info__main">

			<?php echo esc_html( $mode_prefix . $name ); ?>
						<uo-icon integration="STRIPE"></uo-icon>
					</div>

					<div class="uap-settings-panel-user-info__additional">
			<?php
			printf(
			/* translators: 1. Email address */
				esc_html_x( 'Account ID: %1$s', 'Stripe', 'uncanny-automator' ),
				esc_html( $user_details['id'] )
			);

			?>
					</div>
				</div>
				</div>
			<?php
		}
	}

	/**
	 * Disconnect the integration
	 */
	public function disconnect() {

		// Make sure this settings page is the one that is active
		if ( ! $this->is_current_page_settings() ) {
			return;
		}

		// Check that the URL has our custom disconnect flag
		if ( '1' !== automator_filter_input( 'disconnect' ) ) {
			return;
		}

		$this->helpers->disconnect();

		// Redirect back to the settings page
		wp_safe_redirect( $this->get_settings_page_url() );

		exit;
	}

	/**
	 * capture_oauth_tokens
	 *
	 * @return void
	 */
	public function capture_oauth_tokens() {

		if ( ! $this->is_current_settings_tab() ) {
			return;
		}

		$automator_message = automator_filter_input( 'automator_api_message' );

		if ( empty( $automator_message ) ) {
			return;
		}

		$nonce = wp_create_nonce( self::NONCE_KEY );

		$token = (array) \Uncanny_Automator\Automator_Helpers_Recipe::automator_api_decode_message( $automator_message, $nonce );

		if ( empty( $token['stripe_user_id'] ) || empty( $token['vault_signature'] ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'error' => esc_html_x( 'Missing credentials', 'Stripe', 'uncanny-automator' ),
					),
					$this->get_settings_page_url()
				)
			);
			die;
		}

		$connect = $this->helpers->store_token( $token );

		// Refresh user details
		automator_delete_option( $this->helpers::USER_OPTION );
		$this->helpers->api->get_user_details();

		wp_safe_redirect(
			add_query_arg(
				array(
					'connect' => $connect,
				),
				$this->get_settings_page_url()
			)
		);

		die;
	}

	/**
	 * is_current_settings_tab
	 *
	 * @return boolean
	 */
	public function is_current_settings_tab() {

		if ( 'uo-recipe' !== automator_filter_input( 'post_type' ) ) {
			return false;
		}

		if ( 'uncanny-automator-config' !== automator_filter_input( 'page' ) ) {
			return false;
		}

		if ( 'premium-integrations' !== automator_filter_input( 'tab' ) ) {
			return false;
		}

		if ( automator_filter_input( 'integration' ) !== $this->id ) {
			return false;
		}

		return true;
	}
}
