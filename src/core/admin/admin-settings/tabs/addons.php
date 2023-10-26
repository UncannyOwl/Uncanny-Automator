<?php
namespace Uncanny_Automator;

/**
 * Class Admin_Settings_Addons
 *
 *
 * @since   5.0.1
 * @version 1.0
 * @package Uncanny_Automator
 */
class Admin_Settings_Addons {

	protected $addons_tabs;

	public function __construct() {

		// Get the tabs
		$this->addons_tabs = apply_filters( 'automator_settings_addons_tabs', array() );

		if ( empty( $this->addons_tabs ) ) {
			return;
		}

		// Add the tab using the filter
		add_filter( 'automator_settings_sections', array( $this, 'register_tab' ), 30, 1 );
	}

	public function register_tab( $tabs ) {
		// General
		$tabs['addons'] = (object) array(
			'name'     => esc_html__( 'Addons', 'uncanny-automator' ),
			'function' => array( $this, 'tab_output' ),
			'preload'  => false, // Determines if the content should be loaded even if the tab is not selected
		);

		return $tabs;
	}

	/**
	 * Outputs the content of the "General" tab
	 */
	public function tab_output() {

		// Get the current tab
		$current_addon_tab = automator_filter_has_var( 'addons' ) ? sanitize_text_field( automator_filter_input( 'addons' ) ) : array_key_first( $this->addons_tabs );

		// Check if the user is requesting the focus version
		// This variable is used in the admin-settings/tab/addons.php.
		$layout_version = automator_filter_has_var( 'automator_hide_settings_tabs' ) ? 'focus' : 'default';

		// Add the actions and get the selected tab
		foreach ( $this->addons_tabs as $tab_key => $tab ) {
			// Check if the function is defined
			if ( isset( $tab->function ) ) {
				// Add action
				add_action( 'automator_settings_addons_' . $tab_key . '_tab', $tab->function );
			}

			// Check if this is the selected tab
			$tab->is_selected = $tab_key === $current_addon_tab;
		}

		// Load the view
		include Utilities::automator_get_view( 'admin-settings/tab/addons.php' );

	}

	/**
	 * Returns the link of the addons tab subtab
	 *
	 * @param  string $selected_tab Optional. The ID of the subtab
	 * @return string               The URL
	 */
	public static function utility_get_addons_page_link( $selected_tab = '' ) {

		// Define the list of URL parameters
		$url_parameters = array(
			'post_type' => 'uo-recipe',
			'page'      => 'uncanny-automator-config',
			'tab'       => 'addons',
		);

		// Check if there is a selected tab defined
		if ( ! empty( $selected_tab ) ) {
			$url_parameters['addons'] = $selected_tab;
		}

		// Return the URL
		return add_query_arg(
			$url_parameters,
			admin_url( 'edit.php' )
		);

	}

}

new Admin_Settings_Addons();
