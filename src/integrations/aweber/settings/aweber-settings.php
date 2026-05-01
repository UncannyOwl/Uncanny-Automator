<?php
/**
 * Creates the settings page
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator\Integrations\Aweber;

use Exception;
use Uncanny_Automator\Settings\OAuth_App_Integration;

/**
 * Aweber_Settings
 */
class Aweber_Settings extends \Uncanny_Automator\Settings\App_Integration_Settings {

	/**
	 * Use OAuth trait for OAuth functionality
	 */
	use OAuth_App_Integration;

	/**
	 * Set properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Map to AWeber's existing OAuth params.
		$this->oauth_action   = 'authorize';
		$this->redirect_param = 'user_url';
	}

	/**
	 * Validate integration credentials
	 * Maps AWeber's response format to framework expectations.
	 *
	 * @param array $credentials
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function validate_integration_credentials( $credentials ) {

		// Validate required credential properties.
		$access_token  = $credentials['access_token'] ?? '';
		$refresh_token = $credentials['refresh_token'] ?? '';
		$expires_in    = $credentials['expires_in'] ?? '';

		if ( empty( $access_token ) || empty( $refresh_token ) || empty( $expires_in ) ) {
			throw new Exception( 'Missing or invalid credentials. Please reconnect your account.' );
		}

		// Return credentials with date_added for token expiration tracking.
		return array(
			'access_token'  => $access_token,
			'refresh_token' => $refresh_token,
			'expires_in'    => $expires_in,
			'date_added'    => time(),
		);
	}

	/**
	 * Account info after connection
	 *
	 * @return array
	 */
	protected function get_formatted_account_info() {
		// Just show a generic connected status.
		return array(
			'avatar_type' => 'letter',
			'avatar_text' => 'A',
			'main_info'   => esc_html_x( 'AWeber account', 'AWeber', 'uncanny-automator' ),
			'additional'  => esc_html_x( 'Connected', 'AWeber', 'uncanny-automator' ),
		);
	}

	/**
	 * Output main disconnected content.
	 *
	 * @return void
	 */
	public function output_main_disconnected_content() {

		// Output the standard disconnected integration header with subtitle and description.
		$this->output_disconnected_header(
			esc_html_x( "Connect Uncanny Automator to AWeber to streamline automations that incorporate list management, email marketing, customer profile, and activity on your WordPress site.", 'AWeber', 'uncanny-automator' )
		);

		// Automatically generated list of available triggers and actions scanned from Premium_Integration_Items trait.
		$this->output_available_items();
	}

	/**
	 * Output main connected content
	 *
	 * @return void
	 */
	public function output_main_connected_content() {
		?>
		<uo-alert type="info" heading="<?php echo esc_attr_x( 'Uncanny Automator supports connecting multiple AWeber accounts at a time.', 'AWeber', 'uncanny-automator' ); ?>" class="uap-spacing-bottom">
			<?php echo esc_html_x( 'Uncanny Automator helps you link many AWeber accounts at once. You can pick a different account for each action that needs account details. This gives you more choices and control.', 'AWeber', 'uncanny-automator' ); ?>
		</uo-alert>
		<?php
	}
}