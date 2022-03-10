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
	 * Class constructor
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
			function( $tabs ) {
				// General
				$tabs['license'] = (object) array(
					'name'     => esc_html__( 'License', 'uncanny-automator' ),
					'function' => array( $this, 'tab_output' ),
					'preload'  => true, // Determines if the content should be loaded even if the tab is not selected
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
			function() {

				// Get data of the connected site
				$site_data = Admin_Menu::is_automator_connected();

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
			function() {
				// Get the link to upgrade to Pro
				$upgrade_to_pro_url = 'https://automatorplugin.com/pricing/';

				// Load the view
				include Utilities::automator_get_view( 'admin-settings/tab/general/license/upgrade-to-pro.php' );
			},
			15
		);
	}
}

new Admin_Settings_General_License();
