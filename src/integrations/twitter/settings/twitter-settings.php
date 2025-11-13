<?php

namespace Uncanny_Automator\Integrations\Twitter;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Uncanny_Automator\Settings\OAuth_App_Integration;

use Exception;

/**
 * Class Twitter_Settings
 *
 * @package Uncanny_Automator
 *
 * @property Twitter_App_Helpers $helpers
 * @property Twitter_Api_Caller $api
 */
class Twitter_Settings extends App_Integration_Settings {

	use OAuth_App_Integration;

	/**
	 * API key option
	 *
	 * @var string
	 */
	const API_KEY_OPTION = 'automator_twitter_api_key';

	/**
	 * API secret option
	 *
	 * @var string
	 */
	const API_SECRET_OPTION = 'automator_twitter_api_secret';

	/**
	 * Access token option
	 *
	 * @var string
	 */
	const ACCESS_TOKEN_OPTION = 'automator_twitter_access_token';

	/**
	 * Access token secret option
	 *
	 * @var string
	 */
	const ACCESS_TOKEN_SECRET_OPTION = 'automator_twitter_access_token_secret';

	/**
	 * The default connection method.
	 *
	 * @var string
	 */
	protected $default_connection_type = 'hybrid';

	////////////////////////////////////////////////////////////
	// Required abstract method
	////////////////////////////////////////////////////////////

	/**
	 * Get formatted account information for connected user info display
	 *
	 * @return array Formatted account information for UI display
	 */
	protected function get_formatted_account_info() {
		$account  = $this->helpers->get_account_info();
		$username = $account['screen_name'] ?? '';
		$avatar   = ! empty( $username ) ? strtoupper( $username[0] ) : '';

		return array(
			'avatar_type'    => 'text',
			'avatar_value'   => $avatar,
			'main_info'      => $username,
			'main_info_icon' => true,
			'additional'     => sprintf(
				// translators: %1$s is the Twitter ID
				esc_html_x( 'ID: %1$s', 'Twitter ID', 'uncanny-automator' ),
				$account['id'] ?? ''
			),
		);
	}

	////////////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Set the properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		$this->set_default_connection_type( 'hybrid' );
	}

	/**
	 * Register settings options.
	 *
	 * @return void
	 */
	public function register_disconnected_options() {
		$this->register_option( self::API_KEY_OPTION );
		$this->register_option( self::API_SECRET_OPTION );
		$this->register_option( self::ACCESS_TOKEN_OPTION );
		$this->register_option( self::ACCESS_TOKEN_SECRET_OPTION );
	}

	/**
	 * Validate integration OAuth credentials from the "Quick connect" flow.
	 *
	 * @param array $credentials - payload from the OAuth response.
	 *
	 * @return array
	 */
	public function validate_integration_credentials( $credentials ) {
		// Empty checks already happen in abstract, overriding here to omit standard vault signature check.
		return $credentials;
	}

	/**
	 * Validate provided OAuth credentials from the "Custom app" flow.
	 *
	 * @param array $response The current response array
	 * @param array $options The stored option data
	 *
	 * @return array
	 */
	public function after_authorization( $response = array(), $options = array() ) {
		try {
			// Build the credentials array.
			$client = array(
				'api_key'            => automator_get_option( self::API_KEY_OPTION, '' ),
				'api_secret'         => automator_get_option( self::API_SECRET_OPTION, '' ),
				'oauth_token'        => automator_get_option( self::ACCESS_TOKEN_OPTION, '' ),
				'oauth_token_secret' => automator_get_option( self::ACCESS_TOKEN_SECRET_OPTION, '' ),
			);

			// Verify the credentials.
			$user = $this->api->verify_credentials( $client );

			// Store the credentials using helper method.
			$this->helpers->store_credentials( $client );

			// Store the user info using account helper method.
			$this->helpers->store_account_info( $user );

			$this->register_alert(
				array(
					'type'    => 'success',
					'heading' => esc_html_x( 'You have successfully connected your X/Twitter account.', 'Twitter', 'uncanny-automator' ),
				)
			);

		} catch ( \Exception $e ) {
			$this->register_alert(
				array(
					'type'    => 'error',
					'heading' => 'Connection error',
					'content' => sprintf(
						// translators: %1$s is the error message
						esc_html_x(
							'There was an error connecting your X/Twitter account: %1$s',
							'Twitter',
							'uncanny-automator'
						),
						wp_json_encode( $e->getMessage() )
					),
				)
			);

			// Clean up on error using helper methods.
			$this->helpers->delete_credentials();
			$this->helpers->delete_account_info();
		}

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
	 * @param string $type "hybrid"|"self-hosted".
	 *
	 * @return void
	 */
	private function set_default_connection_type( $type ) {

		// If the type is not valid, use the default
		$type = ! in_array( (string) $type, array( 'hybrid', 'self-hosted' ), true )
			? 'hybrid'
			: $type;

		// If connected, just use the provided type
		if ( $this->is_connected ) {
			$this->default_connection_type = $type;
			return;
		}

		// If not connected and we have any stored credentials, use self-hosted
		// As the user has encountered an error connecting.
		$has_credentials = ! empty( automator_get_option( self::API_KEY_OPTION, '' ) ) ||
			! empty( automator_get_option( self::API_SECRET_OPTION, '' ) ) ||
			! empty( automator_get_option( self::ACCESS_TOKEN_OPTION, '' ) ) ||
			! empty( automator_get_option( self::ACCESS_TOKEN_SECRET_OPTION, '' ) );

		$this->default_connection_type = $has_credentials ? 'self-hosted' : 'hybrid';
	}

	////////////////////////////////////////////////////////////
	// Abstract Templating methods
	////////////////////////////////////////////////////////////

	/**
	 * Output main disconnected content.
	 *
	 * @return void
	 */
	public function output_main_disconnected_content() {
		// Output the standard disconnected header with description
		$this->output_disconnected_header(
			esc_html_x( 'Post to X/Twitter directly from your WordPress site - no third-party software or per-transaction fees required. Automatically tweet new articles, sales and other milestones based on any combination of triggers.', 'Twitter', 'uncanny-automator' )
		);

		// Output available actions
		$this->output_available_items();

		// Output connection method options
		$this->output_connection_method_options();

		// Output the custom app content
		$this->output_custom_app_content();
	}

	/**
	 * Output bottom left disconnected content.
	 * Twitter utilizes both OAuth and API keys for connections.
	 *
	 * @return void
	 */
	public function output_bottom_left_disconnected_content() {
		// Quick connect section (OAuth)
		?>
		<uap-app-integration-settings-section 
			id="quick-connect-section"
			section-type="quick-connect"
			state="connection-method"
			show-when="quick-connect"
		>
			<?php $this->output_oauth_connect_button(); ?>
		</uap-app-integration-settings-section>

		<!-- Custom app section (API key) -->
		<uap-app-integration-settings-section 
			id="custom-app-section"
			section-type="custom-app"
			state="connection-method"
			show-when="custom-app"
		>
			<?php $this->output_connect_button(); ?>
		</uap-app-integration-settings-section>
		<?php
	}

	////////////////////////////////////////////////////////////
	// Integration specific templating methods
	////////////////////////////////////////////////////////////

	/**
	 * Output connection method options.
	 *
	 * @return void
	 */
	private function output_connection_method_options() {
		?>
		<div class="uap-settings-panel-content-connection-method">
			<div class="uap-settings-panel-content-connection-method__title">
				<?php echo esc_html_x( 'Connection method', 'Twitter', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content-connection-method__options">
				<div class="uap-settings-panel-content-connection-method__option uap-settings-panel-content-connection-method--quick-connect">
					<uo-field-input-radio 
						name="uap-twitter-connect-method" 
						value="quick-connect"
						data-state-control="connection-method"
						<?php echo checked( $this->get_default_connection_type(), 'hybrid', false ) ? 'checked' : ''; ?>
					>
						<div slot="label" class="uap-custom-app-label">
							<span class="uap-settings-panel-content-connection-method__option-title">
								<?php echo esc_html_x( 'Quick connect', 'Twitter', 'uncanny-automator' ); ?>
							</span>
							<p class="uap-settings-panel-content-paragraph">
								<?php echo esc_html_x( 'The most convenient option: Connect our app to your X/Twitter account', 'Twitter', 'uncanny-automator' ); ?>
							</p>
							<p class="uap-settings-panel-content-paragraph">
								<strong><?php echo esc_html_x( 'Limit: 5 tweets/day', 'Twitter', 'uncanny-automator' ); ?></strong>
							</p>
						</div>
					</uo-field-input-radio>
				</div>
				<div class="uap-settings-panel-content-connection-method__option uap-settings-panel-content-connection-method--custom-app">
					<uo-field-input-radio 
						name="uap-twitter-connect-method" 
						value="custom-app"
						data-state-control="connection-method"
						<?php echo checked( $this->get_default_connection_type(), 'self-hosted', false ) ? 'checked' : ''; ?>
					>
						<div slot="label" class="uap-custom-app-label">
							<span class="uap-settings-panel-content-connection-method__option-title">
								<?php echo esc_html_x( 'Custom app', 'Twitter', 'uncanny-automator' ); ?>
							</span>
							<p class="uap-settings-panel-content-paragraph">
								<?php echo esc_html_x( 'The most powerful option: Use your own app to unlock more tweets', 'Twitter', 'uncanny-automator' ); ?>
							</p>
							<p class="uap-settings-panel-content-paragraph">
								<strong><?php echo esc_html_x( 'Limit: 100 tweets/day', 'Twitter', 'uncanny-automator' ); ?></strong>
							</p>
						</div>
					</uo-field-input-radio>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Output custom app content.
	 *
	 * @return void
	 */
	private function output_custom_app_content() {
		?>
		<uap-app-integration-settings-section 
			id="custom-app-fields-section"
			section-type="custom-app-fields"
			state="connection-method"
			show-when="custom-app"
			class="uap-spacing-top"
		>
			<?php
			// Output setup instructions
			$this->output_setup_instructions(
				sprintf(
					// translators: 1: Knowledge base article link
					esc_html_x( 'Connect your own X/Twitter developer app by adding the app details in the fields below. Visit our %1$s for full instructions.', 'Twitter', 'uncanny-automator' ),
					'<a href="https://automatorplugin.com/knowledge-base/twitter/#use-your-own-twitter-app" target="_blank">' . esc_html_x( 'Knowledge Base article', 'Twitter', 'uncanny-automator' ) . '</a>'
				)
			);

			// Add form fields for the custom app connection.
			$this->text_input_html(
				array(
					'id'       => self::API_KEY_OPTION,
					'value'    => automator_get_option( self::API_KEY_OPTION, '' ),
					'label'    => esc_html_x( 'API key', 'Twitter API key', 'uncanny-automator' ),
					'required' => true,
					'class'    => 'uap-spacing-top',
				)
			);

			$this->text_input_html(
				array(
					'id'       => self::API_SECRET_OPTION,
					'value'    => automator_get_option( self::API_SECRET_OPTION, '' ),
					'label'    => esc_html_x( 'API key secret', 'Twitter API key secret', 'uncanny-automator' ),
					'required' => true,
					'class'    => 'uap-spacing-top',
				)
			);

			$this->text_input_html(
				array(
					'id'       => self::ACCESS_TOKEN_OPTION,
					'value'    => automator_get_option( self::ACCESS_TOKEN_OPTION, '' ),
					'label'    => esc_html_x( 'Access token', 'Twitter access token', 'uncanny-automator' ),
					'required' => true,
					'class'    => 'uap-spacing-top',
				)
			);

			$this->text_input_html(
				array(
					'id'       => self::ACCESS_TOKEN_SECRET_OPTION,
					'value'    => automator_get_option( self::ACCESS_TOKEN_SECRET_OPTION, '' ),
					'label'    => esc_html_x( 'Access token secret', 'Twitter access token secret', 'uncanny-automator' ),
					'required' => true,
					'class'    => 'uap-spacing-top',
				)
			);
			?>
		</uap-app-integration-settings-section>
		<?php
	}
}
