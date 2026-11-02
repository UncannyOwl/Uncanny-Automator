<?php
namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

	/**
	 * Registers the Addons settings tab when at least one add-on adds a tab to it.
	 */
	public function __construct() {

		// Get the tabs
		$this->addons_tabs = apply_filters( 'automator_settings_addons_tabs', array() );

		if ( empty( $this->addons_tabs ) ) {
			return;
		}

		// Add the tab using the filter
		add_filter( 'automator_settings_sections', array( $this, 'register_tab' ), 30, 1 );
	}
	/**
	 * Register tab.
	 *
	 * @param mixed $tabs The tabs.
	 * @return mixed
	 */
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

		$this->add_legacy_backend_data();
		$this->enqueue_legacy_regenerator_runtime();

		// Load the view
		include Utilities::automator_get_view( 'admin-settings/tab/addons.php' );
	}

	/**
	 * Prints the `UncannyAutomatorBackend` global before the Automator admin bundle.
	 *
	 * The add-on settings scripts read `UncannyAutomatorBackend.rest.nonce` as soon as they load, to sign
	 * their REST requests, and the add-ons tab is the only screen that loads them. They read nothing else
	 * from it. The admin bundle prints in the footer, before the scripts the add-ons enqueue while their tab
	 * renders, so the global is set by the time they run.
	 *
	 * @since 8.0.0
	 * @deprecated Compatibility shim for add-ons that still read this global. They move to the nonce that
	 *             WordPress gives `wp.apiFetch`, and this goes once they have.
	 *
	 * @return void
	 */
	private function add_legacy_backend_data() {

		$backend = array(
			'rest' => array(
				'nonce' => wp_create_nonce( 'wp_rest' ),
			),
		);

		wp_add_inline_script( 'uap-admin', 'window.UncannyAutomatorBackend = ' . wp_json_encode( $backend ) . ';', 'before' );
	}

	/**
	 * Loads WordPress's `regenerator-runtime` script on the add-ons tab.
	 *
	 * Some add-on settings scripts call `window.regeneratorRuntime` as soon as they load without declaring
	 * the script as a dependency, and nothing else on the screen loads it: the Automator admin bundle does
	 * not depend on it. Enqueued here, before the add-ons enqueue their scripts, so WordPress prints it
	 * ahead of them.
	 *
	 * @since 8.0.0
	 * @deprecated Compatibility shim for add-ons whose settings scripts rely on it. They declare their own
	 *             dependencies, and this goes once they do.
	 *
	 * @return void
	 */
	private function enqueue_legacy_regenerator_runtime() {
		wp_enqueue_script( 'regenerator-runtime' );
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
			'post_type' => AUTOMATOR_POST_TYPE_RECIPE,
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
