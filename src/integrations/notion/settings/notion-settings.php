<?php
namespace Uncanny_Automator\Integrations\Notion;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Uncanny_Automator\Settings\OAuth_App_Integration;

/**
 * Notion settings class.
 *
 * @package Uncanny_Automator\Integrations\Notion
 *
 * @property Notion_App_Helpers $helpers
 */
class Notion_Settings extends App_Integration_Settings {

	use OAuth_App_Integration;

	////////////////////////////////////////////////////////////
	// Required abstract method
	////////////////////////////////////////////////////////////

	/**
	 * Get formatted account information for connected user info display
	 *
	 * @return array
	 */
	protected function get_formatted_account_info() {
		$account = $this->helpers->get_account_info();
		return array(
			'avatar_type'  => 'text',
			'avatar_value' => substr( strtoupper( $account['owner']['user']['name'] ), 0, 1 ),
			'main_info'    => $account['owner']['user']['person']['email'],
			'additional'   => sprintf(
				// translators: %1$s is the workspace name
				esc_html_x( 'Workspace: %1$s', 'Notion', 'uncanny-automator' ),
				esc_html( $account['workspace_name'] )
			),
		);
	}

	/**
	 * Set additonal non-standard properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		$this->oauth_action       = 'authorization';
		$this->redirect_param     = 'wp_site';
		$this->show_connect_arrow = true;
	}

	/**
	 * Validate integration credentials.
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
	 * Display - Main disconnected content.
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_main_disconnected_content() {
		// Default header with custom description.
		$this->output_disconnected_header(
			esc_html_x( 'Automate Notion workflows with Uncanny Automator: Create and update database entries in Notion and generate new pages based on WordPress activity.', 'Notion', 'uncanny-automator' )
		);
		// Automatically generated list of available triggers and actions.
		$this->output_available_items();
	}

	/**
	 * Display - Main connected content.
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_main_connected_content() {
		$this->output_single_account_message(
			esc_html_x( 'If you create recipes and then change the connected Notion account, your previous recipes may no longer work.', 'Notion', 'uncanny-automator' )
		);
	}
}
