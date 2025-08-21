<?php

namespace Uncanny_Automator;

/**
 * Class Admin_Settings_General_License
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Agustin B.
 */
class Admin_Settings_General_License {

	/**
	 * The URL to upgrade to Pro.
	 *
	 * @var string
	 */
	const URL_UPGRADE_TO_PRO = 'https://automatorplugin.com/pricing';

	/**
	 * The URL to license key page.
	 *
	 * @var string
	 */
	const URL_LICENSE_KEY_PAGE = 'https://automatorplugin.com/knowledge-base/where-can-i-find-my-license-key/';

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {

		// Define the tab
		$this->create_tab();

		// Add content
		$this->add_automator_free_content();
	}

	/**
	 * Adds the tab using the automator_settings_tab filter
	 */
	private function create_tab() {
		// Add the tab using the filter
		add_filter(
			'automator_settings_general_tabs',
			function ( $tabs ) {
				// General
				$tabs['license'] = (object) array(
					'name'     => esc_html__( 'License', 'uncanny-automator' ),
					'function' => array( $this, 'tab_output' ),
					'preload'  => false, // Determines if the content should be loaded even if the tab is not selected
					'icon'     => 'badge-check',
				);

				return $tabs;
			},
			10,
			1
		);
	}

	/**
	 * Outputs the content of the "General" tab
	 */
	public function tab_output() {
		// Load the view
		include Utilities::automator_get_view( 'admin-settings/tab/general/license.php' );
	}

	/**
	 * Adds the license content of Automator Free
	 */
	public function add_automator_free_content() {
		// Check if the user has Automator Free
		if ( ! is_automator_pro_active() ) {
			// Add block to connect the site to automatorplugin.com
			$this->tab_content_connect_site();

			// Add the "Upgrade to Pro" block
			$this->tab_content_upgrade_to_pro();
		}
	}

	/**
	 * Adds the block used to connect the site to automatorplugin.com
	 */
	public function tab_content_connect_site() {
		// Add block to connect the site to automatorplugin.com
		add_action(
			'automator_settings_general_license_content',
			function () {

				// Get data of the connected site
				$site_data = Api_Server::is_automator_connected( true ); // Force check if its in the settings page.

				// Check if the user connected their site to automatorplugin.com
				$site_is_connected = isset( $site_data ) && isset( $site_data['license'] );

				// Get the URL to connect the site
				$connect_site_url = add_query_arg(
					array(
						'post_type' => 'uo-recipe',
						'page'      => 'uncanny-automator-setup-wizard',
					),
					admin_url( 'edit.php' )
				);

				// Get the URL to disconnect the site
				$disconnect_site_url = add_query_arg(
					array(
						'action' => 'discount_automator_connect',
						'state'  => wp_create_nonce( 'automator_setup_wizard_redirect_nonce' ),
					)
				);

				// Load the view
				include Utilities::automator_get_view( 'admin-settings/tab/general/license/connect-site.php' );
			},
			10
		);
	}

	/**
	 * Adds the block used to promote Pro in the License page
	 */
	public function tab_content_upgrade_to_pro() {
		// Add block to promote Pro
		add_action(
			'automator_settings_general_license_content',
			function () {

				// The link to upgrade to Pro. Usage in button.
				$upgrade_to_pro_url_button = add_query_arg(
					array(
						'utm_source'  => 'uncanny_automator',
						'utm_medium'  => 'license_tab',
						'utm_content' => 'license_upgrade_button',
					),
					self::URL_UPGRADE_TO_PRO
				);

				// The link to upgrade to Pro. Usage in link.
				$upgrade_to_pro_url_link = add_query_arg(
					array(
						'utm_source'  => 'uncanny_automator',
						'utm_medium'  => 'license_tab',
						'utm_content' => 'license_upgrade_link',
					),
					self::URL_UPGRADE_TO_PRO
				);

				// The URL to license key page.
				$license_key_url = add_query_arg(
					array(
						'utm_source'  => 'uncanny_automator',
						'utm_medium'  => 'settings',
						'utm_content' => 'license_key_page',
					),
					self::URL_LICENSE_KEY_PAGE
				);

				// Handle error and success messages.
				$error_message   = $this->get_error_message();
				$success_message = $this->get_success_message();

				// Load the view.
				include Utilities::automator_get_view( 'admin-settings/tab/general/license/upgrade-to-pro.php' );
			},
			15
		);
	}

	/**
	 * Get error message from URL parameter and translate to user-friendly message.
	 *
	 * @return string|null User-friendly error message or null if no error.
	 */
	private function get_error_message() {

		$error_message = automator_filter_input( 'error_message', INPUT_GET );

		if ( empty( $error_message ) ) {
			return null;
		}

		$error_code = sanitize_text_field( $error_message );

		$error_messages = array(
			'license_activation_failed' => esc_html_x( 'The license key could not be activated. Please check that your license key is valid and try again.', 'license activation error', 'uncanny-automator' ),
			'download_link_not_found'   => esc_html_x( 'Unable to download the Pro plugin. Please try again or contact support if the issue persists.', 'download error', 'uncanny-automator' ),
			'install_failed'            => esc_html_x( 'The Pro plugin could not be installed. Please check your file permissions and try again.', 'installation error', 'uncanny-automator' ),
			'activation_failed'         => esc_html_x( 'The Pro plugin was installed but could not be activated. Please go to the Plugins page to activate it manually.', 'activation error', 'uncanny-automator' ),
			'permissions_insufficient'  => esc_html_x( 'You do not have permission to install plugins. Please contact your administrator.', 'permissions error', 'uncanny-automator' ),
			'invalid_download_link'     => esc_html_x( 'The download link is invalid. Please try again or contact support.', 'invalid link error', 'uncanny-automator' ),
			'license_key_required'      => esc_html_x( 'Please enter a valid license key.', 'license key required error', 'uncanny-automator' ),
			'Invalid request.'          => esc_html_x( 'Invalid request. Please refresh the page and try again.', 'invalid request error', 'uncanny-automator' ),
		);

		return isset( $error_messages[ $error_code ] )
			? $error_messages[ $error_code ]
			: esc_html_x( 'An unexpected error occurred. Please try again or contact support.', 'generic error', 'uncanny-automator' );
	}

	/**
	 * Get success message from URL parameter.
	 *
	 * @return string|null Success message or null if no success status.
	 */
	private function get_success_message() {

		$success_message = automator_filter_input( 'status', INPUT_GET );

		if ( empty( $success_message ) || 'success' !== $success_message ) {
			return null;
		}

		return esc_html_x( 'Automator Pro has been successfully installed and activated!', 'installation success', 'uncanny-automator' );
	}
}

new Admin_Settings_General_License();
