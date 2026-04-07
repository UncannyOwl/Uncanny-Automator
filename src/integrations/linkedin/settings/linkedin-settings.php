<?php
/**
 * Creates the settings page
 */

namespace Uncanny_Automator\Integrations\Linkedin;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Uncanny_Automator\Settings\OAuth_App_Integration;
use Exception;

/**
 * Linkedin_Settings
 *
 * @package Uncanny_Automator
 *
 * @property Linkedin_App_Helpers $helpers
 */
class Linkedin_Settings extends App_Integration_Settings {

	use OAuth_App_Integration;

	////////////////////////////////////////////////////////////
	// Required abstract method
	////////////////////////////////////////////////////////////

	/**
	 * Get formatted account information for connected user info display.
	 *
	 * @return array Formatted account information for UI display.
	 */
	protected function get_formatted_account_info() {

		$account = $this->helpers->get_account_info();

		$display_name = trim(
			( $account['localizedFirstName'] ?? '' ) . ' ' . ( $account['localizedLastName'] ?? '' )
		);

		$info = array(
			'avatar_type'    => 'text',
			'avatar_value'   => mb_substr( $display_name, 0, 1 ),
			'main_info'      => esc_html( $display_name ),
			'main_info_icon' => true,
		);

		if ( ! empty( $account['id'] ) ) {
			$info['additional'] = sprintf(
				// translators: %s is the LinkedIn user ID.
				esc_html_x( 'ID: %s', 'LinkedIn', 'uncanny-automator' ),
				esc_html( $account['id'] )
			);
		}

		return $info;
	}

	////////////////////////////////////////////////////////////
	// Abstract methods — ordered by OAuth lifecycle
	////////////////////////////////////////////////////////////

	/**
	 * Set properties that apply to both connected and disconnected states.
	 *
	 * @return void
	 */
	public function set_properties() {
		// LinkedIn API proxy uses 'user_url' as the redirect param.
		$this->redirect_param = 'user_url';
	}

	/**
	 * Filter OAuth args to include the selected connection type.
	 *
	 * Called by the OAuth_App_Integration trait during handle_oauth_init.
	 * Stores the selection as pending until authorization succeeds.
	 *
	 * @param array $args The OAuth request arguments.
	 * @param array $data The posted form data.
	 *
	 * @return array The filtered arguments.
	 */
	public function maybe_filter_oauth_args( $args, $data ) {
		$connection_type = sanitize_text_field( $data['uap-linkedin-account-type'] ?? 'business' );

		// Validate the connection type.
		$valid_types     = array( 'business', 'personal', 'both' );
		$connection_type = in_array( $connection_type, $valid_types, true ) ? $connection_type : 'business';

		// Store as pending — only promoted to real on successful auth.
		automator_update_option( $this->helpers->get_option_key( 'pending_connection_type' ), $connection_type );

		// Add connection_type to OAuth args for the API.
		$args['connection_type'] = $connection_type;

		return $args;
	}

	/**
	 * Validate integration-specific credentials.
	 * LinkedIn does not use vault_signature; it passes access_token directly.
	 *
	 * @param array $credentials The credentials from the OAuth callback.
	 *
	 * @return array The validated credentials.
	 * @throws Exception If credentials are invalid.
	 */
	protected function validate_integration_credentials( $credentials ) {

		if ( empty( $credentials['access_token'] ) ) {
			throw new Exception(
				esc_html_x( 'Missing access token from LinkedIn.', 'LinkedIn', 'uncanny-automator' )
			);
		}

		return $credentials;
	}

	/**
	 * Authorize account after credentials are stored.
	 *
	 * @param array $response    The current response array.
	 * @param array $credentials The stored credentials.
	 *
	 * @return array The response array.
	 * @throws Exception If account authorization fails.
	 */
	protected function authorize_account( $response, $credentials ) {

		$user_response = $this->api->api_request(
			array( 'action' => 'get_user' )
		);

		$this->helpers->store_account_info( $user_response['data'] ?? array() );

		// Promote pending connection type on successful authorization.
		$pending_key  = $this->helpers->get_option_key( 'pending_connection_type' );
		$pending_type = automator_get_option( $pending_key, '' );

		if ( ! empty( $pending_type ) ) {
			$this->helpers->store_connection_type( $pending_type );
			automator_delete_option( $pending_key );
		}

		return $response;
	}

	/**
	 * Handle OAuth error by cleaning up the pending connection type.
	 *
	 * @param string $message The error message.
	 *
	 * @return void
	 */
	public function register_oauth_error_alert( $message ) {
		$this->register_alert( $this->get_error_alert( $message ) );

		// Discard pending connection type on auth failure.
		automator_delete_option( $this->helpers->get_option_key( 'pending_connection_type' ) );
	}

	/**
	 * After disconnecting.
	 *
	 * @param array $response The current response array.
	 * @param array $data     The posted data.
	 *
	 * @return array Modified response array.
	 */
	protected function after_disconnect( $response = array(), $data = array() ) {
		// Clean up all cached option data for this integration.
		$this->delete_option_data( $this->helpers->get_option_prefix() );

		return $response;
	}

	////////////////////////////////////////////////////////////
	// Abstract template methods
	////////////////////////////////////////////////////////////

	/**
	 * Output the main disconnected content.
	 *
	 * @return void
	 */
	public function output_main_disconnected_content() {
		$this->output_disconnected_header(
			esc_html_x(
				'Use Uncanny Automator to automatically share updates, news and blog posts from your WordPress site to your LinkedIn page(s) in the form of posts.',
				'Linkedin',
				'uncanny-automator'
			)
		);

		$this->output_available_items();

		$this->output_account_type_options();
	}

	/**
	 * Output the main connected content.
	 *
	 * @return void
	 */
	public function output_main_connected_content() {
		$this->alert_html(
			array(
				'heading' => esc_html_x( 'Uncanny Automator only supports connecting to one LinkedIn account at a time.', 'Linkedin', 'uncanny-automator' ),
				'content' => esc_html_x( 'If you create recipes and then change the connected LinkedIn account, your previous recipes may no longer work.', 'Linkedin', 'uncanny-automator' ),
			)
		);

		$this->output_account_type_options();

		$this->output_reauthorize_button();
	}

	////////////////////////////////////////////////////////////
	// Integration-specific templating methods
	////////////////////////////////////////////////////////////

	/**
	 * Get account type radio option definitions.
	 *
	 * @return array[] Each option with 'value', 'title', and 'description'.
	 */
	private function get_account_type_options_config() {
		return array(
			array(
				'value'       => 'business',
				'title'       => esc_html_x( 'Business Pages', 'Linkedin', 'uncanny-automator' ),
				'description' => esc_html_x( 'Create posts for your connected LinkedIn business pages only.', 'Linkedin', 'uncanny-automator' ),
			),
			array(
				'value'       => 'personal',
				'title'       => esc_html_x( 'Personal Profile', 'Linkedin', 'uncanny-automator' ),
				'description' => esc_html_x( 'Create posts on your personal LinkedIn profile only.', 'Linkedin', 'uncanny-automator' ),
			),
			array(
				'value'       => 'both',
				'title'       => esc_html_x( 'Both (Business Pages + Personal Profile)', 'Linkedin', 'uncanny-automator' ),
				'description' => esc_html_x( 'Create posts on both your business pages and personal profile.', 'Linkedin', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Output account type radio options.
	 *
	 * Uses data-state-control to register with the Lit state manager,
	 * enabling conditional visibility of the re-authorize button.
	 *
	 * @return void
	 */
	private function output_account_type_options() {
		$options = $this->get_account_type_options_config();
		$group   = array(
			'name'          => 'uap-linkedin-account-type',
			'state_control' => 'account-type',
			'current_value' => $this->helpers->get_connection_type(),
		);

		?>
		<div class="uap-settings-panel-content-connection-method">
			<div class="uap-settings-panel-content-connection-method__title">
				<?php echo esc_html_x( 'Account access', 'Linkedin', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content-connection-method__options">
				<?php
				foreach ( $options as $option ) {
					$this->output_connection_method_option( $option, $group );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Output a single connection method radio option.
	 *
	 * @param array $option {
	 *     @type string $value       The radio value.
	 *     @type string $title       The display title.
	 *     @type string $description The description text.
	 * }
	 * @param array $group {
	 *     @type string $name          The radio group name attribute.
	 *     @type string $state_control The data-state-control attribute value.
	 *     @type string $current_value The currently selected value.
	 * }
	 *
	 * @return void
	 */
	private function output_connection_method_option( $option, $group ) {
		?>
		<div class="uap-settings-panel-content-connection-method__option">
			<uo-field-input-radio
				name="<?php echo esc_attr( $group['name'] ); ?>"
				value="<?php echo esc_attr( $option['value'] ); ?>"
				data-state-control="<?php echo esc_attr( $group['state_control'] ); ?>"
				<?php checked( $group['current_value'], $option['value'] ); ?>
			>
				<div slot="label" class="uap-custom-app-label">
					<span class="uap-settings-panel-content-connection-method__option-title">
						<?php echo esc_html( $option['title'] ); ?>
					</span>
					<p class="uap-settings-panel-content-paragraph">
						<?php echo esc_html( $option['description'] ); ?>
					</p>
				</div>
			</uo-field-input-radio>
		</div>
		<?php
	}

	/**
	 * Output the re-authorize button, visible only when the selected
	 * account type differs from the currently stored connection type.
	 *
	 * Uses uap-app-integration-settings-section with show-when to
	 * conditionally display the button for each non-current type.
	 *
	 * @return void
	 */
	private function output_reauthorize_button() {
		$current_type = $this->helpers->get_connection_type();
		$all_types    = array( 'business', 'personal', 'both' );

		foreach ( $all_types as $type ) {
			if ( $type === $current_type ) {
				continue;
			}
			?>
			<uap-app-integration-settings-section
				state="account-type"
				show-when="<?php echo esc_attr( $type ); ?>"
			>
				<?php
				$this->output_oauth_connect_button(
					esc_html_x( 'Update connection', 'Linkedin', 'uncanny-automator' ),
					array( 'class' => 'uap-spacing-top' )
				);
				?>
			</uap-app-integration-settings-section>
			<?php
		}
	}
}
