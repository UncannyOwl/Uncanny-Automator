<?php

namespace Uncanny_Automator;

/**
 * Class Admin_Settings_Uncanny_Agent
 *
 * @since   7.0
 * @version 1.0
 * @package Uncanny_Automator
 */
class Admin_Settings_Uncanny_Agent {

	/**
	 * Class constructor
	 */
	public function __construct() {

		// Define the tab
		$this->create_tab();

		// Load tabs
		$this->load_tabs();
	}

	/**
	 * Load the tabs classes
	 *
	 * @return void
	 */
	private function load_tabs() {

		// Load the files
		$this->load_tab( 'general' );
	}

	/**
	 * Loads the PHP file with the class that defines a tab
	 *
	 * @param string $tab_key The tab ID
	 *
	 * @return void
	 */
	private function load_tab( $tab_key ) {
		include_once __DIR__ . DIRECTORY_SEPARATOR . 'uncanny-agent-tabs/' . $tab_key . '.php';
	}

	/**
	 * Adds the tab using the automator_settings_tab filter
	 *
	 * @return void
	 */
	private function create_tab() {
		// Add the tab using the filter
		add_filter(
			'automator_settings_sections',
			function ( $tabs ) {
				// Uncanny Agent
				$tabs['uncanny-agent'] = (object) array(
					'name'     => esc_html__( 'Uncanny Agent', 'uncanny-automator' ),
					'function' => array( $this, 'tab_output' ),
					'preload'  => false, // Determines if the content should be loaded even if the tab is not selected
				);

				return $tabs;
			},
			20,
			1
		);
	}

	/**
	 * Outputs the content of the "Uncanny Agent" tab
	 */
	public function tab_output() {

		// Get the tabs
		$uncanny_agent_tabs = $this->get_uncanny_agent_tabs();

		// Get the current tab
		$current_uncanny_agent_tab = automator_filter_has_var( 'uncanny-agent' ) ? sanitize_text_field( automator_filter_input( 'uncanny-agent' ) ) : 'general';

		// Check if the user is requesting the focus version
		// This variable is used in the admin-settings/tab/uncanny-agent.php.
		$layout_version = automator_filter_has_var( 'automator_hide_settings_tabs' ) ? 'focus' : 'default';

		// Add the actions and get the selected tab
		foreach ( $uncanny_agent_tabs as $tab_key => $tab ) {
			// Check if the function is defined
			if ( isset( $tab->function ) ) {
				// Add action
				add_action( 'automator_settings_uncanny_agent_' . $tab_key . '_tab', $tab->function );
			}

			// Check if this is the selected tab
			$tab->is_selected = $tab_key === $current_uncanny_agent_tab;
		}

		// Load the view
		include Utilities::automator_get_view( 'admin-settings/tab/uncanny-agent.php' );
	}

	/**
	 * Returns the uncanny agent tabs
	 */
	public function get_uncanny_agent_tabs() {

		return apply_filters( 'automator_settings_uncanny_agent_tabs', array() );
	}

	/**
	 * Returns the link of the uncanny agent tab subtab
	 *
	 * @param  string $selected_tab Optional. The ID of the subtab
	 * @return string               The URL
	 */
	public static function utility_get_uncanny_agent_page_link( $selected_tab = '' ) {

		// Define the list of URL parameters
		$url_parameters = array(
			'post_type' => 'uo-recipe',
			'page'      => 'uncanny-automator-config',
			'tab'       => 'uncanny-agent',
		);

		// Check if there is a selected tab defined
		if ( ! empty( $selected_tab ) ) {
			$url_parameters['uncanny-agent'] = $selected_tab;
		}

		// Return the URL
		return add_query_arg(
			$url_parameters,
			admin_url( 'edit.php' )
		);
	}
}

new Admin_Settings_Uncanny_Agent();
