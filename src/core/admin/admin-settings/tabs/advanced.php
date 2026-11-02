<?php
namespace Uncanny_Automator;

use Uncanny_Automator\App\Presentation\Admin\App_Platform_Status_Tab;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Settings_Advanced
 *
 * This class was reference from general.php
 *
 * @since   4.1
 * @version 1.0
 * @package Uncanny_Automator
 */
class Admin_Settings_Advanced {

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
		$this->load_tab( 'background-actions' );
		$this->load_tab( 'automator-cache' );
	}

	/**
	 * Loads the PHP file with the class that defines a tab
	 *
	 * @param string $tab_key The tab ID
	 *
	 * @return void
	 */
	private function load_tab( $tab_key ) {
		include_once __DIR__ . DIRECTORY_SEPARATOR . 'advanced-tabs/' . $tab_key . '.php';
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
				// General
				$tabs['advanced'] = (object) array(
					'name'     => esc_html__( 'Advanced', 'uncanny-automator' ),
					'function' => array( $this, 'tab_output' ),
					'preload'  => false, // Determines if the content should be loaded even if the tab is not selected
				);

				return $tabs;
			},
			30,
			1
		);
	}

	/**
	 * Outputs the content of the "General" tab
	 */
	public function tab_output() {

		// Get the tabs
		$advanced_tabs = $this->get_advanced_tabs();

		// Get the current tab: the requested one, else the first in the list
		$current_advanced_tab = automator_filter_has_var( 'advanced' ) ? sanitize_text_field( automator_filter_input( 'advanced' ) ) : $this->get_default_advanced_tab( $advanced_tabs );

		// Check if the user is requesting the focus version
		// This variable is used in the admin-settings/tab/advanced.php.
		$layout_version = automator_filter_has_var( 'automator_hide_settings_tabs' ) ? 'focus' : 'default';

		// Add the actions and get the selected tab
		foreach ( $advanced_tabs as $tab_key => $tab ) {
			// Check if the function is defined
			if ( isset( $tab->function ) ) {
				// Add action
				add_action( 'automator_settings_advanced_' . $tab_key . '_tab', $tab->function );
			}

			// Check if this is the selected tab
			$tab->is_selected = $tab_key === $current_advanced_tab;
		}

		// Load the view
		include Utilities::automator_get_view( 'admin-settings/tab/advanced.php' );
	}

	/**
	 * The sub-tab that opens when the address names none: App Platform status
	 * when it is registered, else the first in the list. The legacy tabs register
	 * at priority 99 and App Platform status places itself before Background app
	 * actions, so the first key is Automator cache or the retired Background
	 * actions, never App Platform status.
	 *
	 * @param array<string, object> $advanced_tabs The Advanced sub-tabs, in order.
	 *
	 * @return string
	 */
	public function get_default_advanced_tab( $advanced_tabs ) {

		if ( isset( $advanced_tabs[ App_Platform_Status_Tab::TAB ] ) ) {
			return App_Platform_Status_Tab::TAB;
		}

		return is_array( $advanced_tabs ) && array() !== $advanced_tabs ? (string) array_key_first( $advanced_tabs ) : '';
	}

	/**
	 * Returns the general tabs
	 */
	public function get_advanced_tabs() {

		return apply_filters( 'automator_settings_advanced_tabs', array() );
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
			'post_type' => AUTOMATOR_POST_TYPE_RECIPE,
			'page'      => 'uncanny-automator-config',
			'tab'       => 'advanced',
		);

		// Check if there is a selected tab defined
		if ( ! empty( $selected_tab ) ) {
			$url_parameters['advanced'] = $selected_tab;
		}

		// Return the URL
		return add_query_arg(
			$url_parameters,
			admin_url( 'edit.php' )
		);
	}
}

new Admin_Settings_Advanced();
