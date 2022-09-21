<?php
/**
 * Uncanny_Automator\Admin_Tools_Tab_Debug
 *
 * @since 4.5
 */

namespace Uncanny_Automator;

class Admin_Tools_Tab_Debug {

	const PRIORITY = 10;

	const ACCEPTED_ARGS = 1;

	public function __construct() {

		$this->create_tab();

		add_action( 'wp_ajax_automator_log_delete', array( $this, 'delete_log' ) );

	}

	public function delete_log() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permission.' );
		}

		if ( ! wp_verify_nonce( automator_filter_input( 'nonce' ), 'automator_log_delete' ) ) {
			wp_die( 'Invalid nonce.' );
		}

		$log = sanitize_file_name( automator_filter_input( 'log_id' ) . '.log' );

		$query_params = array(
			'post_type'    => 'uo-recipe',
			'page'         => 'uncanny-automator-admin-tools',
			'tab'          => 'debug',
			'log'          => $log,
			'file_removed' => 'yes',
		);

		if ( ! is_file( UA_DEBUG_LOGS_DIR . $log ) ) {

			$query_params['failed'] = 'yes';

			wp_safe_redirect( add_query_arg( $query_params, admin_url( 'edit.php' ) ) );

			die;

		}

		if ( unlink( UA_DEBUG_LOGS_DIR . $log ) ) {

			wp_safe_redirect( add_query_arg( $query_params, admin_url( 'edit.php' ) ) );

			die;

		}

		$query_params['failed'] = 'yes';

		wp_safe_redirect( add_query_arg( $query_params, admin_url( 'edit.php' ) ) );

		die;

	}

	/**
	 * Loads the PHP file with the class that defines a tab
	 *
	 * @param string $tab_key The tab ID
	 */
	private function load_tab( $tab_key ) {

		include __DIR__ . DIRECTORY_SEPARATOR . 'debug/' . $tab_key . '.php';

	}

	/**
	 * Adds the tab using the automator_settings_tab filter
	 */
	private function create_tab() {

		$this->load_tab( 'debug' );

		// Add the tab using the filter.
		add_filter(
			'automator_admin_tools_tabs',
			function( $tabs ) {
				$tabs['debug'] = (object) array(
					'name'     => esc_html__( 'Debug', 'uncanny-automator' ),
					'function' => array( $this, 'tab_output' ),
					'preload'  => false,
				);
				return $tabs;
			},
			self::PRIORITY,
			self::ACCEPTED_ARGS
		);

	}

	/**
	 * Outputs the content of the "General" tab
	 */
	public function tab_output() {

		// Get the tabs.
		$debug_tabs = $this->get_debug_tabs();

		// Get the current tab.
		$current_tab = automator_filter_has_var( 'debug' ) ? sanitize_text_field( automator_filter_input( 'debug' ) ) : 'debug';

		// Check if the user is requesting the focus version.
		$layout_version = automator_filter_has_var( 'automator_hide_settings_tabs' ) ? 'focus' : 'default';

		// Add the actions and get the selected tab.
		foreach ( $debug_tabs as $tab_key => $debug_tab ) {

			// Check if the function is defined.
			if ( isset( $debug_tab->function ) ) {

				// Add action.
				add_action( 'automator_admin_tools_tools_' . $tab_key . '_tab', $debug_tab->function );

			}

			// Check if this is the selected tab
			$debug_tab->is_selected = $tab_key === $current_tab;

		}

		// Load the view
		include Utilities::automator_get_view( 'admin-tools/tab/debug.php' );

	}

	/**
	 * Returns the general tabs
	 */
	public function get_debug_tabs() {

		return apply_filters( 'automator_admin_tools_debug_tabs', array() );

	}

	/**
	 * Returns the link of the general tab subtab
	 *
	 * @param  string $selected_tab Optional. The ID of the subtab
	 * @return string               The URL
	 */
	public static function utility_get_debug_page_link( $selected_tab = '' ) {

		// Define the list of URL parameters.
		$url_parameters = array(
			'post_type' => 'uo-recipe',
			'page'      => 'uncanny-automator-admin-tools',
			'tab'       => 'debug',
		);

		// Check if there is a selected tab defined
		if ( ! empty( $selected_tab ) ) {
			$url_parameters['debug'] = $selected_tab;
		}

		// Return the URL
		return add_query_arg(
			$url_parameters,
			admin_url( 'edit.php' )
		);

	}

}

new Admin_Tools_Tab_Debug();
