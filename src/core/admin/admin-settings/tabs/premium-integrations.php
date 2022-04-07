<?php

namespace Uncanny_Automator;

/**
 * Class Admin_Settings_Premium_Integrations
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Agustin B.
 */
class Admin_Settings_Premium_Integrations {
	/**
	 * Class constructor
	 */
	public function __construct() {
		// Define the tab
		$this->create_tab();
	}

	/**
	 * Adds the tab using the automator_settings_tab filter
	 */
	private function create_tab() {
		// Add the tab using the filter
		add_filter(
			'automator_settings_sections',
			function( $tabs ) {
				// Premium integrations
				$tabs['premium-integrations'] = (object) array(
					'name'     => esc_html__( 'Premium integrations', 'uncanny-automator' ),
					'function' => array( $this, 'tab_output' ),
					'preload'  => false, // Determines if the content should be loaded even if the tab is not selected
				);

				return $tabs;
			},
			10,
			1
		);
	}

	/**
	 * Outputs the content of the "Premium integrations" tab
	 */
	public function tab_output() {
		// Get the tabs
		$integrations_tabs = $this->get_premium_integrations_tabs();

		// Get the current tab
		$current_integration = automator_filter_has_var( 'integration' ) ? sanitize_text_field( automator_filter_input( 'integration' ) ) : '';

		// Check if the user has access to the premium integrations
		// This will be true if the site is connected (Automator Free) or if the
		// user has Automator Pro activated
		$user_can_use_premium_integrations = Admin_Menu::is_automator_connected() || is_automator_pro_active();

		// Get the link to upgrade to Pro
		$upgrade_to_pro_url = add_query_arg(
			// UTM
			array(
				'utm_source' => 'uncanny_automator',
				'utm_medium' => 'settings',
				'utm_content' => 'premium_integrations_connect'
			),

			'https://automatorplugin.com/pricing/'
		);

		// Get the link to the article about credits
		$credits_article_url = add_query_arg(
			// UTM
			array(
				'utm_source' => 'uncanny_automator',
				'utm_medium' => 'settings',
				'utm_content' => 'premium_integrations_connect'
			),

			'https://automatorplugin.com/knowledge-base/what-are-credits/'
		);

		// Get the link to connect the site
		$connect_site_url = add_query_arg(
			array(
				'post_type' => 'uo-recipe',
				'page'      => 'uncanny-automator-setup-wizard',
			),
			admin_url( 'edit.php' )
		);

		// Check if the user is requesting the focus version
		$layout_version = automator_filter_has_var( 'automator_hide_settings_tabs' ) ? 'focus' : 'default';

		// Add the actions and get the selected tab
		foreach ( $integrations_tabs as $tab_key => $tab ) {
			if (
				// Check if the user can use the premium integrations before adding the content of the tabs
				$user_can_use_premium_integrations &&
				// Check if the function is defined
				isset( $tab->function )
			) {
				// Add action
				add_action( 'automator_settings_premium_integrations_' . $tab_key . '_tab', $tab->function );
			}

			// Check if this is the selected tab
			$tab->is_selected = $user_can_use_premium_integrations ? $tab_key === $current_integration : false;
		}

		// Load the view
		include Utilities::automator_get_view( 'admin-settings/tab/premium-integrations.php' );
	}

	/**
	 * Returns the premium integrations tabs
	 */
	public function get_premium_integrations_tabs() {
		return apply_filters( 'automator_settings_premium_integrations_tabs', array() );
	}

	/**
	 * Returns the link of the premium integrations tab
	 *
	 * @param  string $selected_tab Optional. The ID of the integration
	 * @return string               The URL
	 */
	public static function utility_get_premium_integrations_page_link( $selected_tab = '' ) {
		// Define the list of URL parameters
		$url_parameters = array(
			'post_type' => 'uo-recipe',
			'page'      => 'uncanny-automator-config',
			'tab'       => 'premium-integrations',
		);

		// Check if there is a selected tab defined
		if ( ! empty( $selected_tab ) ) {
			$url_parameters['integration'] = $selected_tab;
		}

		// Return the URL
		return add_query_arg(
			$url_parameters,
			admin_url( 'edit.php' )
		);
	}
}

new Admin_Settings_Premium_Integrations();
