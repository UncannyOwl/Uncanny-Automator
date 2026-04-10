<?php
/**
 * Creates the settings page
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Joseph G.
 */


namespace Uncanny_Automator\Integrations\Facebook;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Uncanny_Automator\Settings\OAuth_App_Integration;
use WP_Error;
use Exception;

/**
 * Facebook settings class.
 *
 * @package Uncanny_Automator\Integrations\Facebook
 */
class Facebook_Settings extends App_Integration_Settings {

	use OAuth_App_Integration;

	/**
	 * The Facebook bridge instance for shared credential storage and UI helpers.
	 *
	 * @var Facebook_Bridge
	 */
	private $facebook_bridge;

	////////////////////////////////////////////////////////////
	// Required abstract method
	////////////////////////////////////////////////////////////

	/**
	 * Get formatted account information for connected user info display
	 *
	 * @return array
	 */
	protected function get_formatted_account_info() {
		return $this->facebook_bridge->get_formatted_account_info();
	}

	////////////////////////////////////////////////////////////
	// Abstract methods.
	////////////////////////////////////////////////////////////

	/**
	 * Set additonal non-standard properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		$this->oauth_action       = 'facebook_authorization_request';
		$this->redirect_param     = 'user_url';
		$this->facebook_bridge    = Facebook_Bridge::get_instance();
		$this->show_connect_arrow = true;
	}

	/**
	 * Get connect button label
	 *
	 * @return string
	 */
	public function get_connect_button_label() {
		return $this->facebook_bridge->get_connect_button_label();
	}

	/**
	 * Validate integration credentials received from OAuth.
	 *
	 * @param array $credentials The credentials from OAuth response.
	 *
	 * @return array The validated credentials.
	 * @throws Exception If credentials are invalid.
	 */
	protected function validate_integration_credentials( $credentials ) {
		return $this->facebook_bridge->validate_oauth_credentials( $credentials );
	}

	/**
	 * Authorize account after OAuth.
	 *
	 * Called by framework after credentials are stored.
	 * Fetches user info now that credentials are available for API requests.
	 *
	 * @param array $response    The current response array.
	 * @param array $credentials The stored credentials.
	 *
	 * @return array The response array.
	 */
	protected function authorize_account( $response, $credentials ) {
		$this->facebook_bridge->authorize_account( $this->api );
		return $response;
	}

	/**
	 * Get connected alert - customize based on whether this is initial connection or update.
	 *
	 * @param string $message Optional custom message.
	 * @param string $heading Optional custom heading.
	 *
	 * @return array
	 */
	protected function get_connected_alert( $message = '', $heading = '' ) {
		// If already connected before this OAuth flow, it's an update.
		if ( $this->is_connected ) {
			return $this->get_success_alert(
				esc_html_x( 'Your linked Facebook pages have been updated.', 'Facebook', 'uncanny-automator' ),
				esc_html_x( 'Pages updated', 'Facebook', 'uncanny-automator' )
			);
		}

		// Default message for initial connection.
		return parent::get_connected_alert( $message, $heading );
	}

	/**
	 * Before Authorized Account Disconnect.
	 *
	 * @param array $response The current response array.
	 * @param array $data The posted data.
	 *
	 * @return array The response array.
	 */
	protected function before_disconnect( $response = array(), $data = array() ) {
		$this->facebook_bridge->disconnect( $this->api );
		return $response;
	}

	////////////////////////////////////////////////////////////
	// Helper methods.
	////////////////////////////////////////////////////////////

	/**
	 * Get linked pages data.
	 *
	 * @return mixed - Array of pages or WP_Error message.
	 */
	private function get_linked_pages_data() {
		try {
			$pages = $this->helpers->get_linked_pages();
		} catch ( Exception $e ) {
			return new WP_Error( 'facebook_pages_error', $e->getMessage() );
		}

		return $pages;
	}

	////////////////////////////////////////////////////////////
	// Templating methods.
	////////////////////////////////////////////////////////////

	/**
	 * Display - Main disconnected content.
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_main_disconnected_content() {
		// Default header with custom description.
		$this->output_disconnected_header(
			esc_html_x(
				"Use Uncanny Automator to automatically share updates, news and blog posts from your WordPress site to your organization's Facebook Page(s) in the form of posts, images and links",
				'Facebook Pages',
				'uncanny-automator'
			)
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
		// Title.
		$this->output_panel_subtitle(
			esc_html_x( 'Linked pages', 'Facebook', 'uncanny-automator' ),
			'uap-spacing-bottom'
		);

		// List of linked pages.
		$this->output_linked_pages_list();

		// Update / re-authorize button via OAuth.
		$this->output_action_button(
			'oauth_init',
			esc_html_x( 'Update linked pages', 'Facebook', 'uncanny-automator' ),
			array(
				'color' => 'secondary',
			)
		);
	}

	/**
	 * Output the linked pages list or error message.
	 *
	 * @return void - Outputs HTML directly
	 */
	private function output_linked_pages_list() {
		$pages = $this->get_linked_pages_data();
		if ( is_wp_error( $pages ) ) {
			$this->alert_html(
				array(
					'type'    => 'error',
					'heading' => esc_html_x( 'Error retrieving linked pages', 'Facebook', 'uncanny-automator' ),
					'content' => esc_html( $pages->get_error_message() ),
					'class'   => 'uap-spacing-bottom',
				)
			);
			return;
		}

		// Prepare columns.
		$columns = array(
			array(
				'key' => 'icon',
			),
			array(
				'key' => 'link',
			),
		);

		// Prepare rows.
		$pages = array_values( $pages );
		$rows  = array_map(
			function ( $page ) {
				// Handle pages that may be stored as objects.
				$page = is_object( $page ) ? (array) $page : $page;
				return array(
					'id'      => $page['value'],
					'columns' => array(
						'icon' => array(
							'options' => array(
								array(
									'type' => 'icon',
									'data' => array(
										'integration' => 'FACEBOOK',
										'size'        => 'small',
									),
								),
							),
						),
						'link' => array(
							'options' => array(
								array(
									'type' => 'text',
									'data' => sprintf(
										'[%s](https://facebook.com/%s)',
										esc_html( $page['text'] ),
										esc_attr( $page['value'] )
									),
								),
							),
						),
					),
				);
			},
			$pages
		);

		// Render list of linked Facebook pages.
		$this->output_settings_table( $columns, $rows, 'card' );
	}
}
