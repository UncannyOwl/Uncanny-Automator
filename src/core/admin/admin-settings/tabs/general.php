<?php

namespace Uncanny_Automator;

/**
 * Class Admin_Settings_General
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Agustin B.
 */
class Admin_Settings_General {
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
	 */
	private function load_tabs() {
		// Load the files
		$this->load_tab( 'license' );
		$this->load_tab( 'logs' );
		$this->load_tab( 'improve-automator' );
	}

	/**
	 * Loads the PHP file with the class that defines a tab
	 *
	 * @param  string $tab_key The tab ID
	 */
	private function load_tab( $tab_key ) {
		include __DIR__ . DIRECTORY_SEPARATOR . 'general-tabs/' . $tab_key . '.php';
	}

	/**
	 * Adds the tab using the automator_settings_tab filter
	 */
	private function create_tab() {
		// Add the tab using the filter
		add_filter(
			'automator_settings_sections',
			function( $tabs ) {
				// General
				$tabs['general'] = (object) array(
					'name'     => esc_html__( 'General', 'uncanny-automator' ),
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
	 * Outputs the content of the "General" tab
	 */
	public function tab_output() {
		// Get the tabs
		$general_tabs = $this->get_general_tabs();

		// Get the current tab
		$current_general_tab = automator_filter_has_var( 'general' ) ? sanitize_text_field( automator_filter_input( 'general' ) ) : 'license';

		// Check if the user is requesting the focus version
		$layout_version = automator_filter_has_var( 'automator_hide_settings_tabs' ) ? 'focus' : 'default';

		// Add the actions and get the selected tab
		foreach ( $general_tabs as $tab_key => $tab ) {
			// Check if the function is defined
			if ( isset( $tab->function ) ) {
				// Add action
				add_action( 'automator_settings_general_' . $tab_key . '_tab', $tab->function );
			}

			// Check if this is the selected tab
			$tab->is_selected = $tab_key === $current_general_tab;
		}

		// Load the view
		include Utilities::automator_get_view( 'admin-settings/tab/general.php' );
	}

	/**
	 * Returns the general tabs
	 */
	public function get_general_tabs() {
		return apply_filters( 'automator_settings_general_tabs', array() );
	}

	/**
	 * Returns the link of the general tab subtab
	 *
	 * @param  string $selected_tab Optional. The ID of the subtab
	 * @return string               The URL
	 */
	public static function utility_get_general_page_link( $selected_tab = '' ) {
		// Define the list of URL parameters
		$url_parameters = array(
			'post_type' => 'uo-recipe',
			'page'      => 'uncanny-automator-config',
			'tab'       => 'general',
		);

		// Check if there is a selected tab defined
		if ( ! empty( $selected_tab ) ) {
			$url_parameters['general'] = $selected_tab;
		}

		// Return the URL
		return add_query_arg(
			$url_parameters,
			admin_url( 'edit.php' )
		);
	}
}

new Admin_Settings_General();
