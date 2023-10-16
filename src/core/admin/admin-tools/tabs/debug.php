<?php
/**
 * Uncanny_Automator\Admin_Tools_Tab_Debug
 *
 * @since 4.5
 */

namespace Uncanny_Automator;

class Admin_Tools_Tab_Debug {

	/**
	 * @var int
	 */
	const PRIORITY = 10;

	/**
	 * @var int
	 */
	const ACCEPTED_ARGS = 1;

	/**
	 * Creates the tab and registers delete log method to wp_ajax_automator_log_delete action hook.
	 *
	 * @return void
	 */
	public function __construct() {

		$this->create_tab();

		add_action( 'wp_ajax_automator_log_delete', array( $this, 'delete_log' ) );

	}

	/**
	 * Deletes the log. Invokes die after redirecting.
	 *
	 * @see redirect
	 *
	 * @return void
	 */
	public function delete_log() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permission.', 401 );
		}

		if ( ! wp_verify_nonce( automator_filter_input( 'nonce' ), 'automator_log_delete' ) ) {
			wp_die( 'Invalid nonce.', 403 );
		}

		// Strips '/' characters. Prevents directory traversal.
		$requested_log = automator_filter_input( 'log_id', INPUT_GET, FILTER_SANITIZE_ENCODED );

		// The absolute path to the log file. Also sanitizes the file name.
		$log_file_path = trailingslashit( UA_DEBUG_LOGS_DIR ) . sanitize_file_name( $requested_log );

		// Make sure the file exists and has a valid extension.
		if ( false === $this->has_log_extension( $requested_log ) || ! file_exists( $log_file_path ) ) {
			$this->redirect( false );
		}

		// Finally delete the file. Redirects with success if the file is deleted.
		if ( unlink( $log_file_path ) ) {
			$this->redirect( true );
		}

		$this->redirect( false );

	}

	/**
	 * Redirects with failure or success message. Invokes die statement after redirecting.
	 *
	 * @param bool $success
	 *
	 * @return void
	 */
	public function redirect( $success = true ) {

		$query_params = array(
			'post_type'    => 'uo-recipe',
			'page'         => 'uncanny-automator-admin-tools',
			'tab'          => 'debug',
			'file_removed' => 'yes',
		);

		if ( false === $success ) {
			$query_params['failed'] = 'yes';
		}

		wp_safe_redirect( add_query_arg( $query_params, admin_url( 'edit.php' ) ) );

		die;
	}

	/**
	 * Determines whether the specific file has .log extension or not.
	 *
	 * @param string $log
	 *
	 * @return bool
	 */
	public function has_log_extension( $log ) {

		$file_parts = pathinfo( $log );

		// Readability.
		if ( ! isset( $file_parts['extension'] ) || AUTOMATOR_LOGS_EXT !== $file_parts['extension'] ) {
			return false;
		}

		return true;
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
			function ( $tabs ) {
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
	 * @param string $selected_tab Optional. The ID of the subtab
	 *
	 * @return string
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
