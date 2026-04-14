<?php
/**
 * ConvertKit_Settings class
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator\Integrations\ConvertKit;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Uncanny_Automator\Settings\OAuth_App_Integration;

use Exception;

/**
 * ConvertKit_Settings
 *
 * @property ConvertKit_App_Helpers $helpers
 * @property ConvertKit_Api_Caller $api
 */
class ConvertKit_Settings extends App_Integration_Settings {

	use OAuth_App_Integration;

	/**
	 * Temporary option key for the API key field (used only during connect flow).
	 *
	 * @var string
	 */
	const API_KEY_OPTION = 'automator_convertkit_api_key';

	/**
	 * Temporary option key for the API secret field (used only during connect flow).
	 *
	 * @var string
	 */
	const API_SECRET_OPTION = 'automator_convertkit_api_secret';

	/**
	 * The default connection method.
	 *
	 * @var string
	 */
	protected $default_connection_type = 'oauth';

	////////////////////////////////////////////////////////////
	// Required abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Get formatted account information for connected user info display.
	 *
	 * @return array
	 */
	protected function get_formatted_account_info() {

		$credentials = array();

		try {
			$credentials = $this->helpers->get_credentials();
		} catch ( Exception $e ) {
			return array(
				'avatar_type'  => 'icon',
				'avatar_value' => 'CONVERTKIT',
				'main_info'    => '',
			);
		}

		$name = $credentials['name'] ?? '';

		return array(
			'avatar_type'  => 'icon',
			'avatar_value' => 'CONVERTKIT',
			'main_info'    => ! empty( $name )
				? sprintf(
					/* translators: %s: Account name */
					esc_html_x( 'Connected as: %s', 'ConvertKit', 'uncanny-automator' ),
					esc_html( $name )
				)
				: esc_html_x( 'Connected', 'ConvertKit', 'uncanny-automator' ),
			'additional'   => $credentials['primary_email_address'] ?? '',
		);
	}

	////////////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Set properties for the settings page.
	 *
	 * @return void
	 */
	public function set_properties() {
		$this->name = 'Kit';
		$this->set_default_connection_type( 'oauth' );
	}

	/**
	 * Register disconnected options (temporary fields used during connect flow).
	 *
	 * @return void
	 */
	protected function register_disconnected_options() {
		$this->register_option( self::API_KEY_OPTION );
		$this->register_option( self::API_SECRET_OPTION );
	}

	/**
	 * Validate provided API credentials from the "API key" flow.
	 *
	 * @param array $response The current response array.
	 * @param array $options The stored option data.
	 *
	 * @return array
	 */
	public function after_authorization( $response = array(), $options = array() ) {

		try {

			$api_key    = automator_get_option( self::API_KEY_OPTION, '' );
			$api_secret = automator_get_option( self::API_SECRET_OPTION, '' );

			if ( empty( $api_key ) || empty( $api_secret ) ) {
				throw new Exception(
					esc_html_x( 'Please enter a valid API key and API secret.', 'ConvertKit', 'uncanny-automator' )
				);
			}

			$this->api->authorize_api_keys( $api_key, $api_secret );

			$this->register_alert(
				array(
					'type'    => 'success',
					'heading' => esc_html_x( 'You have successfully connected your Kit account.', 'ConvertKit', 'uncanny-automator' ),
				)
			);

			// Delete the temporary options.
			automator_delete_option( self::API_KEY_OPTION );
			automator_delete_option( self::API_SECRET_OPTION );

		} catch ( Exception $e ) {

			$this->register_alert(
				array(
					'type'    => 'error',
					'heading' => esc_html_x( 'Connection error', 'ConvertKit', 'uncanny-automator' ),
					'content' => sprintf(
						// translators: %s is the error message
						esc_html_x(
							'There was an error connecting your Kit account: %s',
							'ConvertKit',
							'uncanny-automator'
						),
						esc_html( $e->getMessage() )
					),
				)
			);

			$this->helpers->delete_credentials();
		}

		return $response;
	}

	/**
	 * Before disconnect — notify the API proxy to clean up vault data.
	 *
	 * @param array $response The current response array.
	 * @param array $data The posted data.
	 *
	 * @return array
	 */
	protected function before_disconnect( $response = array(), $data = array() ) {

		// Request vault cleanup from the proxy. Catch and ignore errors
		// so the user is never prevented from disconnecting locally.
		try {
			$this->api->api_request( 'disconnect' );
		} catch ( Exception $e ) {
			unset( $e );
		}

		return $response;
	}

	/**
	 * After disconnect — delete all cached option data for this integration.
	 *
	 * @param array $response The current response array.
	 * @param array $data The posted data.
	 *
	 * @return array
	 */
	protected function after_disconnect( $response = array(), $data = array() ) {

		$this->delete_option_data( $this->helpers->get_option_prefix() );

		return $response;
	}

	////////////////////////////////////////////////////////////
	// Integration methods
	////////////////////////////////////////////////////////////

	/**
	 * Get the default connection type.
	 *
	 * @return string
	 */
	private function get_default_connection_type() {
		return $this->default_connection_type;
	}

	/**
	 * Sets the default connection type.
	 *
	 * @param string $type "oauth"|"api-key".
	 *
	 * @return void
	 */
	private function set_default_connection_type( $type ) {

		$type = ! in_array( (string) $type, array( 'oauth', 'api-key' ), true )
			? 'oauth'
			: $type;

		if ( $this->is_connected ) {
			$this->default_connection_type = $type;
			return;
		}

		// If not connected and we have stored API key options, show API key tab
		// as the user may have encountered an error connecting.
		$has_credentials = ! empty( automator_get_option( self::API_KEY_OPTION, '' ) )
			|| ! empty( automator_get_option( self::API_SECRET_OPTION, '' ) );

		$this->default_connection_type = $has_credentials ? 'api-key' : 'oauth';
	}

	////////////////////////////////////////////////////////////
	// Abstract templating methods
	////////////////////////////////////////////////////////////

	/**
	 * Output main connected content.
	 *
	 * Shows a v3 upgrade notice when using legacy API key connection.
	 *
	 * @return void
	 */
	public function output_main_connected_content() {

		$this->output_single_account_message();

		if ( $this->helpers->is_v3() ) {
			$this->alert_html(
				array(
					'type'    => 'warning',
					'heading' => esc_html_x( 'You are connected using a legacy API key.', 'ConvertKit', 'uncanny-automator' ),
					'content' => esc_html_x( 'Reconnect your Kit account using the Quick connect option to unlock additional actions and improved functionality.', 'ConvertKit', 'uncanny-automator' ),
				)
			);
			$this->output_oauth_connect_button(
				esc_html_x( 'Reconnect using Quick connect', 'ConvertKit', 'uncanny-automator' )
			);
		}
	}

	/**
	 * Output main disconnected content.
	 *
	 * @return void
	 */
	public function output_main_disconnected_content() {

		$this->output_disconnected_header(
			esc_html_x(
				'Connect Uncanny Automator to Kit to better segment and engage with your audience. Once configured, Automator recipes can add or remove Kit tags for subscribers based on activity on your WordPress site, plus add subscribers to Kit forms and sequences.',
				'ConvertKit',
				'uncanny-automator'
			)
		);

		$this->output_available_items();

		$this->output_connection_method_options();

		$this->output_api_key_content();
	}

	/**
	 * Output bottom left disconnected content.
	 *
	 * @return void
	 */
	public function output_bottom_left_disconnected_content() {
		?>
		<uap-app-integration-settings-section
			id="quick-connect-section"
			section-type="quick-connect"
			state="connection-method"
			show-when="quick-connect"
		>
			<?php $this->output_oauth_connect_button(); ?>
		</uap-app-integration-settings-section>

		<uap-app-integration-settings-section
			id="api-key-section"
			section-type="custom-app"
			state="connection-method"
			show-when="custom-app"
		>
			<?php $this->output_connect_button(); ?>
		</uap-app-integration-settings-section>
		<?php
	}

	////////////////////////////////////////////////////////////
	// Integration-specific templating methods
	////////////////////////////////////////////////////////////

	/**
	 * Output connection method toggle (Quick connect / API key).
	 *
	 * @return void
	 */
	private function output_connection_method_options() {
		?>
		<div class="uap-settings-panel-content-connection-method">
			<div class="uap-settings-panel-content-connection-method__title">
				<?php echo esc_html_x( 'Connection method', 'ConvertKit', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content-connection-method__options">
				<div class="uap-settings-panel-content-connection-method__option uap-settings-panel-content-connection-method--quick-connect">
					<uo-field-input-radio
						name="uap-convertkit-connect-method"
						value="quick-connect"
						data-state-control="connection-method"
						<?php echo checked( $this->get_default_connection_type(), 'oauth', false ) ? 'checked' : ''; ?>
					>
						<div slot="label" class="uap-custom-app-label">
							<span class="uap-settings-panel-content-connection-method__option-title">
								<?php echo esc_html_x( 'Quick connect', 'ConvertKit', 'uncanny-automator' ); ?>
							</span>
							<p class="uap-settings-panel-content-paragraph">
								<?php echo esc_html_x( 'The recommended option: Sign in to your Kit account to connect', 'ConvertKit', 'uncanny-automator' ); ?>
							</p>
						</div>
					</uo-field-input-radio>
				</div>
				<div class="uap-settings-panel-content-connection-method__option uap-settings-panel-content-connection-method--custom-app">
					<uo-field-input-radio
						name="uap-convertkit-connect-method"
						value="custom-app"
						data-state-control="connection-method"
						<?php echo checked( $this->get_default_connection_type(), 'api-key', false ) ? 'checked' : ''; ?>
					>
						<div slot="label" class="uap-custom-app-label">
							<span class="uap-settings-panel-content-connection-method__option-title">
								<?php echo esc_html_x( 'API key', 'ConvertKit', 'uncanny-automator' ); ?>
							</span>
							<p class="uap-settings-panel-content-paragraph">
								<?php echo esc_html_x( 'Legacy option with limited functionality. Connect using your Kit API key and secret', 'ConvertKit', 'uncanny-automator' ); ?>
							</p>
						</div>
					</uo-field-input-radio>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Output the API key form fields (custom app section).
	 *
	 * @return void
	 */
	private function output_api_key_content() {
		?>
		<uap-app-integration-settings-section
			id="api-key-fields-section"
			section-type="custom-app-fields"
			state="connection-method"
			show-when="custom-app"
			class="uap-spacing-top"
		>
			<?php
			$this->output_setup_instructions(
				esc_html_x( 'To retrieve your Kit V3 API keys, perform the following:', 'ConvertKit', 'uncanny-automator' ),
				array(
					esc_html_x( 'Sign in to your Kit account.', 'ConvertKit', 'uncanny-automator' ),
					esc_html_x( 'Click on your avatar in the upper right corner and select "Settings".', 'ConvertKit', 'uncanny-automator' ),
					esc_html_x( 'Click the "Developer" menu entry on the left side.', 'ConvertKit', 'uncanny-automator' ),
					esc_html_x( 'In the API Keys section, locate the "V3 Key" area. Copy both the API Key and API Secret values to connect Automator to Kit.', 'ConvertKit', 'uncanny-automator' ),
				)
			);

			$this->text_input_html(
				array(
					'id'       => self::API_KEY_OPTION,
					'value'    => esc_attr( automator_get_option( self::API_KEY_OPTION, '' ) ),
					'label'    => esc_attr_x( 'API key', 'ConvertKit', 'uncanny-automator' ),
					'required' => true,
					'class'    => 'uap-spacing-top',
				)
			);

			$this->text_input_html(
				array(
					'id'       => self::API_SECRET_OPTION,
					'value'    => esc_attr( automator_get_option( self::API_SECRET_OPTION, '' ) ),
					'label'    => esc_attr_x( 'API secret', 'ConvertKit', 'uncanny-automator' ),
					'required' => true,
					'class'    => 'uap-spacing-top',
				)
			);
			?>
		</uap-app-integration-settings-section>
		<?php
	}
}
