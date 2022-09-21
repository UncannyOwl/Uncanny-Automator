<?php
/**
 * Uncanny_Automator\Admin_Logs
 *
 * @since 4.5
 */
namespace Uncanny_Automator;

class Admin_Logs {

	protected $asset;

	public function __construct() {

		// Register the options menu
		$this->submenu_page();

		// Load tabs
		$this->load_tabs();

		// Enqueue assets from PRO Filters.
		$this->enqueue_assets();

	}

	public function set_asset( $asset ) {

		$this->asset = $asset;

	}

	/**
	 * Adds the "Settings" submenu page
	 */
	private function submenu_page() {

		add_action(
			'admin_menu',
			function() {
				add_submenu_page(
					'edit.php?post_type=uo-recipe',
					esc_attr__( 'Logs', 'uncanny-automator' ),
					esc_attr__( 'Logs', 'uncanny-automator' ),
					'manage_options',
					'uncanny-automator-admin-logs',
					array( $this, 'submenu_page_output' ),
					700
				);
			}
		);

	}

	/**
	 * Load the tabs classes
	 */
	private function load_tabs() {

		$this->load_tab( 'recipe' );

		$this->load_tab( 'trigger' );

		$this->load_tab( 'action' );

		// $this->load_tab( 'api' );

	}

	/**
	 * Loads the PHP file with the class that defines a tab
	 *
	 * @param  string $tab_key The tab ID
	 */
	private function load_tab( $tab_key ) {

		include_once __DIR__ . DIRECTORY_SEPARATOR . 'tabs/' . sanitize_file_name( $tab_key ) . '.php';

	}

	/**
	 * Creates the output of the "Settings" page
	 */
	public function submenu_page_output() {

		// Get the tabs.
		$tabs = $this->get_top_level_tabs();

		// Get the current tab
		$current_tab = automator_filter_has_var( 'tab' ) ? sanitize_text_field( automator_filter_input( 'tab' ) ) : 'recipe';

		// Check if the user is requesting the focus version.
		$layout_version = automator_filter_has_var( 'automator_hide_settings_tabs' ) ? 'focus' : 'default';

		// Add the actions and get the selected tab.
		foreach ( $tabs as $tab_key => $tab ) {

			// Check if the function is defined.
			if ( isset( $tab->function ) ) {
				add_action( 'automator_admin_logs_top_level_tabs_item_content_' . $tab_key, $tab->function );
			}

			// Check if this is the selected tab
			$tab->is_selected = $tab_key === $current_tab;

		}

		// Load the view.
		include Utilities::automator_get_view( 'admin-logs/admin-logs.php' );

	}

	/**
	 * Returns the top level tabs
	 */
	public function get_top_level_tabs() {

		return apply_filters( 'automator_admin_logs_top_level_tabs_items', array() );

	}

	/**
	 * Returns the link of the settings page.
	 *
	 * @param  string $selected_tab Optional. The ID of the selected tab.
	 *
	 * @return string The URL.
	 */
	public static function utility_get_settings_page_link( $selected_tab = '' ) {

		// Define the list of URL parameters.
		$url_parameters = array(
			'post_type' => 'uo-recipe',
			'page'      => 'uncanny-automator-admin-logs',
		);

		// Check if there is a selected tab defined.
		if ( ! empty( $selected_tab ) ) {
			$url_parameters['tab'] = $selected_tab;
		}

		// Return the URL.
		return add_query_arg(
			$url_parameters,
			admin_url( 'edit.php' )
		);

	}

	public function get_current_tab() {

		if ( automator_filter_has_var( 'tab' ) ) {
			return automator_filter_input( 'tab' );
		}

		return 'recipe';

	}

	private function enqueue_assets() {

		require_once __DIR__ . DIRECTORY_SEPARATOR . 'src/asset-manager.php';

		$this->set_asset( new Admin_Logs\Asset_Manager() );

		add_action( 'admin_enqueue_scripts', array( $this->asset, 'enqueue_assets' ) );

	}

}
