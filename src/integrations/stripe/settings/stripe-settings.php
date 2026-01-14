<?php
namespace Uncanny_Automator\Integrations\Stripe;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Uncanny_Automator\Settings\OAuth_App_Integration;
use Exception;

/**
 * Stripe Integration Settings
 *
 * @property Stripe_App_Helpers $helpers
 * @property Stripe_Api_Caller $api
 * @property Stripe_Webhooks $webhooks
 */
class Stripe_Settings extends App_Integration_Settings {

	use OAuth_App_Integration;

	/**
	 * Live or test mode
	 *
	 * @var array
	 */
	private $mode;

	/**
	 * The option where we store the connection mode
	 *
	 * @var string
	 */
	const CONNECTION_MODE_OPTION = 'automator_stripe_mode';

	////////////////////////////////////////////////////////////
	// Required abstract method
	////////////////////////////////////////////////////////////

	/**
	 * Get formatted account information for connected user info display
	 *
	 * @return array
	 */
	protected function get_formatted_account_info() {
		// Get the user details.
		$user_details = $this->api->get_user_details();

		// Maybe prefix the name with test mode.
		$name        = $user_details['settings']['dashboard']['display_name'];
		$mode_prefix = 'test' === $this->mode
			? esc_html_x( '(Test mode)', 'Stripe', 'uncanny-automator' ) . ' '
			: '';

		return array(
			'avatar_type'  => 'text',
			'avatar_value' => esc_html( strtoupper( $name[0] ) ),
			'main_info'    => esc_html( $mode_prefix . $name ),
			'additional'   => sprintf(
				// translators: %s. Account ID.
				esc_html_x( 'Account ID: %s', 'Stripe', 'uncanny-automator' ),
				esc_html( $user_details['id'] )
			),
		);
	}

	////////////////////////////////////////////////////////////
	// Override framework methods.
	////////////////////////////////////////////////////////////

	/**
	 * Set the properties of the class and the integration
	 */
	public function set_properties() {
		// Always set the name w/o Test mode for settings page.
		$this->set_name( 'Stripe' );
		// Set Live vs Test mode.
		$this->mode = $this->helpers->get_mode();
	}

	/**
	 * Set connected properties.
	 *
	 * @return void
	 */
	public function set_connected_properties() {
		// Add warning if connected in test mode.
		if ( 'test' === $this->mode ) {
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
	 * Register disconnected options.
	 *
	 * @return void
	 */
	protected function register_disconnected_options() {
		$this->register_option( self::CONNECTION_MODE_OPTION );
	}

	/**
	 * Register connected options.
	 *
	 * @return void
	 */
	protected function register_connected_options() {
		$this->register_option( $this->webhooks->get_option_name() );
	}

	/**
	 * Maybe filter the OAuth args to add the mode ( live or test ).
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	protected function maybe_filter_oauth_args( $args, $data = array() ) {
		// Check if the mode has been posted and add it to the args.
		$mode = (string) $this->get_data_option( self::CONNECTION_MODE_OPTION, $data );
		if ( isset( $mode ) ) {
			// Convert '1' to 'test', empty to 'live'
			$args['mode'] = '1' === $mode ? 'test' : 'live';
		}

		return $args;
	}

	/**
	 * Validate integration credentials after OAuth flow.
	 *
	 * @param array $credentials
	 *
	 * @return array
	 */
	public function validate_integration_credentials( $credentials ) {

		try {
			// Common check for stripe ID and vault signature.
			$this->helpers->validate_credentials( $credentials );
		} catch ( Exception $e ) {
			throw new Exception(
				esc_html_x( 'Missing credentials', 'Stripe', 'uncanny-automator' )
			);
		}

		// Delete any existing user details to be refreshed on reload.
		$this->helpers->delete_account_info();

		return $credentials;
	}

	/**
	 * Before save settings - Provide feedback on signing secret changes.
	 *
	 * @param array $response - The current response array
	 * @param array $data - The data posted to the settings page.
	 *
	 * @return array
	 */
	protected function before_save_settings( $response = array(), $data = array() ) {
		// Get current and posted values
		$current_secret = (string) $this->webhooks->get_webhook_key();
		$posted_secret  = (string) $this->get_data_option( $this->webhooks->get_option_name(), $data );

		// Case 1: First time setting the secret (current empty, posted not empty)
		if ( empty( $current_secret ) && ! empty( $posted_secret ) ) {
			$response['alert'] = $this->get_success_alert(
				esc_html_x( 'Webhook configured', 'Stripe', 'uncanny-automator' ),
				esc_html_x( 'Webhook signing secret has been saved. Stripe triggers are now enabled.', 'Stripe', 'uncanny-automator' )
			);
			return $response;
		}

		// Case 2: Secret is empty
		if ( empty( $posted_secret ) ) {
			$response['alert'] = $this->get_error_alert(
				esc_html_x( 'Missing webhook secret', 'Stripe', 'uncanny-automator' ),
				esc_html_x( 'Webhook signing secret is required for Stripe triggers to work. Please enter a valid signing secret.', 'Stripe', 'uncanny-automator' )
			);
			return $response;
		}

		// Case 3: Handling existing secret (both not empty)
		if ( ! empty( $current_secret ) && ! empty( $posted_secret ) ) {
			if ( $current_secret !== $posted_secret ) {
				// Secret was updated
				$response['alert'] = $this->get_success_alert(
					esc_html_x( 'Webhook updated', 'Stripe', 'uncanny-automator' ),
					esc_html_x( 'Webhook signing secret has been updated. Stripe triggers will continue to work with the new secret.', 'Stripe', 'uncanny-automator' )
				);
				return $response;
			}

			// No change to secret
			$response['alert'] = $this->get_info_alert(
				esc_html_x( 'No changes were made to the webhook signing secret.', 'Stripe', 'uncanny-automator' )
			);
		}

		return $response;
	}

	////////////////////////////////////////////////////////////
	// Content output methods.
	////////////////////////////////////////////////////////////

	/**
	 * Display - Main panel disconnected content.
	 *
	 * @return string - HTML
	 */
	public function output_main_disconnected_content() {
		// Output the standard disconnected integration header with description.
		$this->output_disconnected_header(
			esc_html_x( 'Connect Uncanny Automator to Stripe to process and manage customer payments via WordPress.', 'Stripe', 'uncanny-automator' )
		);

		// Automatically generated list of available triggers and actions.
		$this->output_available_items();
	}

	/**
	 * Display - Bottom left disconnected content.
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_bottom_left_disconnected_content() {
		?>
		<div class="uap-settings-panel-flex-centered">
			<uap-app-integration-settings-section 
				id="stripe-live-connect-section"
				section-type="live-connect"
				state="connection-method"
				show-when="0"
			>
				<?php
				$this->output_action_button(
					'oauth_init',
					esc_html_x( 'Connect Stripe', 'Stripe', 'uncanny-automator' )
				);
				?>
			</uap-app-integration-settings-section>
			<uap-app-integration-settings-section 
				id="stripe-test-connect-section"
				section-type="test-connect"
				state="connection-method"
				show-when="1"
			>
				<?php
				$this->output_action_button(
					'oauth_init',
					esc_html_x( 'Connect Stripe in test mode', 'Stripe', 'uncanny-automator' ),
					array(
						'color' => 'danger',
					)
				);
				?>
			</uap-app-integration-settings-section>

			<!-- Live / test toggle. -->
			<uo-field-input-switch 
				id="<?php echo esc_attr( self::CONNECTION_MODE_OPTION ); ?>"
				name="<?php echo esc_attr( self::CONNECTION_MODE_OPTION ); ?>"
				label-on="<?php echo esc_attr_x( 'Disable test mode', 'Stripe', 'uncanny-automator' ); ?>"
				label-off="<?php echo esc_attr_x( 'Enable test mode', 'Stripe', 'uncanny-automator' ); ?>"
				data-state-control="connection-method"
			></uo-field-input-switch>
		</div>
		<?php
	}

	/**
	 * Display - Main panel connected content.
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_main_connected_content() {

		$this->output_setup_instructions(
			// Additional text to display before the steps.
			esc_html_x( 'To enable Stripe triggers please configure webhooks in your Stripe account:', 'Stripe', 'uncanny-automator' ),
			// Steps to complete the setup.
			array(
				sprintf(
					// translators: 1. Link to Stripe Dashboard.
					esc_html_x( 'Go to your %s', 'Stripe', 'uncanny-automator' ),
					$this->get_escaped_link(
						'https://dashboard.stripe.com/webhooks',
						esc_html_x( 'Stripe Dashboard', 'Stripe', 'uncanny-automator' ),
						array(
							'title' => esc_html_x( 'Visit Stripe Dashboard', 'Stripe', 'uncanny-automator' ),
						)
					)
				),
				esc_html_x( 'Click the "Add endpoint" button', 'Stripe', 'uncanny-automator' ),
				sprintf(
					// translators: 1. Webhook URL input field.
					esc_html_x( "You'll be asked to enter a webhook URL. Please use this value: %s", 'Stripe', 'uncanny-automator' ),
					'<uo-text-field value="' . esc_url( $this->webhooks->get_webhook_url() ) . '" copy-to-clipboard="true" disabled></uo-text-field>'
				),
				sprintf(
					// translators: 1. Webhook secret input field.
					esc_html_x( 'Once the webhook is created, copy its signing secret, and paste it here: %s', 'Stripe', 'uncanny-automator' ),
					'<uo-text-field id="' . esc_attr( $this->webhooks->get_option_name() ) . '" name="stripe-webhook-secret" value="' . esc_attr( $this->webhooks->get_webhook_key( false ) ) . '" type="password"></uo-text-field>'
				),
				esc_html_x( 'Click the "Save settings" below.', 'Stripe', 'uncanny-automator' ),
			),
			// Heading for the steps.
			esc_html_x( 'Continue the setup by enabling Stripe triggers', 'Stripe', 'uncanny-automator' )
		);
	}
}
