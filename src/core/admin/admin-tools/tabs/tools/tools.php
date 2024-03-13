<?php
/**
 * Uncanny_Automator\Admin_Tools_Tabs_Tools
 *
 * @since 4.5
 */

namespace Uncanny_Automator;

/**
 *
 */
class Admin_Tools_Tabs_Tools {

	/**
	 *
	 */
	public function __construct() {

		// Define the tab.
		$this->create_tab();

		add_action(
			'wp_ajax_automator_db_tools',
			function () {
				$this->process_request();
			}
		);

		add_action(
			'wp_ajax_automator_db_tools_empty_api_logs',
			function () {
				$this->empty_api_logs_tables();
			}
		);

	}

	/**
	 * Process wp-ajax request coming from action `automator_db_tools`.
	 *
	 * @return void
	 */
	private function process_request() {

		$this->validate_request();

		$query_params = array(
			'post_type' => 'uo-recipe',
			'page'      => 'uncanny-automator-admin-tools',
			'tab'       => 'tools',
		);

		switch ( automator_filter_input( 'type' ) ) {

			case 'drop_view':
				$dropped = Automator_DB::drop_view( automator_filter_input( 'view' ) );

				$query_params['status'] = $dropped ? 'true' : 'false';

				break;

			case 'repair_tables':
				Automator_DB::verify_base_tables( true );

				delete_option( 'automator_schema_missing_tables' );

				$query_params['database_repaired'] = 'yes';

				do_action( 'automator_repair_tables_after' );
				break;

			case 'purge_tables':
				$purged = Automator_DB::purge_tables();

				delete_option( 'automator_schema_missing_tables' );

				$query_params['purged'] = $purged ? 'true' : 'false';

				break;

		}

		wp_safe_redirect( add_query_arg( $query_params, admin_url( 'edit.php' ) ) );

		exit;

	}

	/**
	 * Empty api logs related table and views.
	 *
	 * @return void
	 */
	public function empty_api_logs_tables() {

		$this->validate_request();

		Automator_DB::empty_table( 'uap_api_log' );
		Automator_DB::empty_table( 'uap_api_log_response' );

		// Update the size of the Database on clear
		$total_size = Automator_System_Report::get_tables_total_size();

		update_option( 'automator_db_size', $total_size, 'no' );

		$query_params = array(
			'post_type' => 'uo-recipe',
			'page'      => 'uncanny-automator-admin-tools',
			'tab'       => 'tools',
			'purged'    => 'true',
		);

		wp_safe_redirect( add_query_arg( $query_params, admin_url( 'edit.php' ) ) );

		die;

	}

	/**
	 * @return void
	 */
	private function validate_request() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permission.' );
		}

		if ( ! wp_verify_nonce( automator_filter_input( 'nonce' ), 'automator_db_tools' ) ) {
			wp_die( 'Invalid nonce.' );
		}

	}

	/**
	 * Adds the tab using the automator_settings_tab filter.
	 *
	 * @return void
	 */
	private function create_tab() {
		// Add the tab using the filter.
		add_filter(
			'automator_admin_tools_tools_tabs',
			function ( $tabs ) {
				$tabs['tools'] = (object) array(
					'name'     => esc_html__( 'Database', 'uncanny-automator' ),
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
	 * Outputs the content of the "Tools" tab.
	 */
	public function tab_output() {
		// Load the view.
		include Utilities::automator_get_view( 'admin-tools/tab/tools/tools.php' );
	}

}

new Admin_Tools_Tabs_Tools();
