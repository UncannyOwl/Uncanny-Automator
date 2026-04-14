<?php
/**
 * Zoho Campaigns Settings Page
 *
 * @since   4.8
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator\Integrations\Zoho_Campaigns;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Uncanny_Automator\Settings\OAuth_App_Integration;

/**
 * Zoho_Campaigns_Settings
 *
 * @package Uncanny_Automator
 * @since 4.10
 *
 * @property Zoho_Campaigns_App_Helpers $helpers
 * @property Zoho_Campaigns_Api_Caller $api
 */
class Zoho_Campaigns_Settings extends App_Integration_Settings {

	use OAuth_App_Integration;

	/**
	 * Set properties for the settings page.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Set OAuth properties for backward compatibility.
		$this->oauth_action   = 'authorization';
		$this->redirect_param = 'site_url';
	}

	////////////////////////////////////////////////////////////
	// Required abstract method
	////////////////////////////////////////////////////////////

	/**
	 * Get formatted account information for connected user info display.
	 *
	 * @return array Formatted account information for UI display.
	 */
	protected function get_formatted_account_info() {
		$credentials  = $this->helpers->get_credentials();
		$display_name = $credentials['display_name'] ?? null;

		return array(
			'avatar_type'    => 'text',
			'avatar_value'   => 'Z',
			'main_info'      => esc_html_x( 'Zoho Campaigns account connected', 'ZohoCampaigns', 'uncanny-automator' ),
			'main_info_icon' => true,
			'additional'     => empty( $display_name )
				? ''
				: sprintf(
					// translators: %s is the connected users display name.
					esc_html_x( 'Connected user: %s', 'ZohoCampaigns', 'uncanny-automator' ),
					esc_html( $display_name )
				),
		);
	}

	////////////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Validate integration-specific credentials.
	 *
	 * @param array $credentials
	 *
	 * @return array
	 * @throws Exception If the credentials are invalid.
	 */
	protected function validate_integration_credentials( $credentials ) {
		$this->helpers->validate_credentials( $credentials );
		return $credentials;
	}

	/**
	 * Before disconnect - notify API proxy to delete vault.
	 *
	 * @param array $response The current response array.
	 * @param array $data     The posted data.
	 *
	 * @return array Modified response array.
	 */
	protected function before_disconnect( $response = array(), $data = array() ) {
		// Delete the vault.
		try {
			// Request API proxy to remove vault credentials.
			$this->api->api_request( 'disconnect' );
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Silently ignore - just a cleanup operation.
		}

		return $response;
	}

	/**
	 * After disconnect hook - clean up Zoho Campaigns-specific option data.
	 *
	 * @param array $response
	 * @param array $data
	 *
	 * @return array
	 */
	protected function after_disconnect( $response = array(), $data = array() ) {
		// Clean up all Zoho Campaigns cached option data.
		$this->delete_option_data( $this->helpers->get_option_prefix() );

		return $response;
	}

	////////////////////////////////////////////////////////////
	// Settings page content methods
	////////////////////////////////////////////////////////////

	/**
	 * Output main disconnected content.
	 *
	 * @return void
	 */
	public function output_main_disconnected_content() {

		// Output the standard disconnected integration header with subtitle and description.
		$this->output_disconnected_header(
			esc_html_x( "Uncanny Automator is a powerful automation platform that makes it easy to build workflows that connect Zoho Campaigns with other applications. With Uncanny Automator's drag-and-drop interface, you can quickly and easily create automated workflows that can streamline your email marketing campaigns.", 'Zoho Campaigns', 'uncanny-automator' )
		);

		// Automatically generated list of available triggers and actions scanned from Premium_Integration_Items trait.
		$this->output_available_items();
	}

	/**
	 * Output main connected content.
	 *
	 * @return void
	 */
	public function output_main_connected_content() {
		$this->output_single_account_message(
			esc_html_x( 'If you create recipes and then change the connected Zoho Campaigns account, your previous recipes may no longer work.', 'Zoho Campaigns', 'uncanny-automator' )
		);
	}
}
