<?php

namespace Uncanny_Automator\Integrations\HubSpot;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Uncanny_Automator\Settings\OAuth_App_Integration;
use Exception;

/**
 * Class HubSpot_Settings
 *
 * @package Uncanny_Automator
 *
 * @property HubSpot_App_Helpers $helpers
 * @property HubSpot_Api_Caller $api
 */
class HubSpot_Settings extends App_Integration_Settings {

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
		$account_info = $this->helpers->get_account_info();

		if ( empty( $account_info ) ) {
			return array();
		}

		// Get first letter for avatar.
		$avatar_text = '';
		if ( ! empty( $account_info['user'] ) ) {
			$avatar_text = strtoupper( substr( $account_info['user'], 0, 1 ) );
		}

		return array(
			'avatar_type'  => 'text',
			'avatar_value' => ! empty( $avatar_text ) ? $avatar_text : 'H',
			'main_info'    => $account_info['user'] ?? esc_html_x( 'HubSpot Account', 'HubSpot', 'uncanny-automator' ),
			'additional'   => ! empty( $account_info['hub_domain'] )
				? sprintf(
					// translators: %s: Hub domain
					esc_html_x( 'Hub Domain: %s', 'HubSpot', 'uncanny-automator' ),
					$account_info['hub_domain']
				)
				: '',
		);
	}

	////////////////////////////////////////////////////////////
	// Framework methods
	////////////////////////////////////////////////////////////

	/**
	 * Authorize account after OAuth.
	 *
	 * @param array $response
	 * @param array $credentials
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function authorize_account( $response, $credentials ) {
		// Fetch account info from HubSpot to validate connection.
		$account_info = $this->api->get_connected_account_info();

		if ( empty( $account_info ) ) {
			throw new Exception( esc_html_x( 'Unable to verify connected account.', 'HubSpot', 'uncanny-automator' ) );
		}

		// Store only the minimal details needed for display.
		$this->helpers->store_account_info(
			array(
				'user'       => $account_info['user'] ?? '',
				'hub_domain' => $account_info['hub_domain'] ?? '',
			)
		);

		return $response;
	}

	/**
	 * Output main disconnected content.
	 *
	 * @return void
	 */
	public function output_main_disconnected_content() {
		// Output the standard disconnected integration header with description.
		$this->output_disconnected_header(
			esc_html_x( 'Connect Uncanny Automator to HubSpot to better segment and engage with your customers. Automatically add users to lists and update your HubSpot contacts based on user activity on your WordPress site.', 'HubSpot', 'uncanny-automator' )
		);

		// Automatically generated list of available triggers and actions.
		$this->output_available_items();
	}

	/**
	 * Before disconnect - notify API proxy to delete vault.
	 *
	 * @param array $response
	 * @param array $data
	 *
	 * @return array
	 */
	protected function before_disconnect( $response = array(), $data = array() ) {

		try {
			// Request API proxy to remove vault credentials.
			$this->api->api_request( 'disconnect' );
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Silently ignore - just a cleanup operation.
		}

		return $response;
	}

	/**
	 * After disconnect - clean up HubSpot-specific option data.
	 *
	 * @param array $response
	 * @param array $data
	 *
	 * @return array
	 */
	protected function after_disconnect( $response = array(), $data = array() ) {
		// Clean up all HubSpot cached option data.
		$this->delete_option_data( $this->helpers->get_option_prefix() );

		return $response;
	}
}
