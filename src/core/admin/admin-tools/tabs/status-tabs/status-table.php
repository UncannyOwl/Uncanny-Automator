<?php
/**
 * Class Uncanny_Automator\Admin_Tools_Status_Table
 *
 * @since   4.5
 */

namespace Uncanny_Automator;

class Admin_Tools_Status_Table {

	public function __construct() {
		// Define the tab
		$this->create_tab();

		if ( 'uncanny-automator-admin-tools' === automator_filter_input( 'page' ) ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		}
	}

	public function assets() {

		// @TODO: Replace rand with automator version.
		wp_enqueue_style( 'uap-admin-tools', Utilities::automator_get_asset( 'legacy/css/admin/tools.css' ), array(), Utilities::automator_get_version() . '-1.0.5' );

	}

	private function create_tab() {
		// Add the tab using the filter
		add_filter(
			'automator_admin_tools_status_tabs',
			function( $tabs ) {
				// General
				$tabs['status'] = (object) array(
					'name'     => esc_html__( 'Status', 'uncanny-automator' ),
					'function' => array( $this, 'tab_output' ),
					'preload'  => false, // Determines if the content should be loaded even if the tab is not selected
				);

				return $tabs;
			},
			10,
			1
		);
	}

	public function tab_output() {

		// Load the view
		include Utilities::automator_get_view( 'admin-tools/tab/status/status-table.php' );

	}

}

new Admin_Tools_Status_Table();
