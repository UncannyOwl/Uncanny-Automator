<?php
/**
 * Uncanny_Automator\Admin_Logs_API
 *
 * @since 4.5
 */
namespace Uncanny_Automator;

class Admin_Logs_API {

	public function __construct() {

		// Define the tab
		$this->create_tab();

	}

	/**
	 * Adds the tab using the automator_settings_tab filter
	 */
	private function create_tab() {
		// Add the tab using the filter
		add_filter(
			'automator_admin_logs_top_level_tabs_items',
			function( $tabs ) {
				$tabs['api'] = (object) array(
					'name'     => esc_html__( 'API', 'uncanny-automator' ),
					'function' => array( $this, 'tab_output' ),
					'preload'  => false,
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

		include Utilities::automator_get_view( 'admin-logs/tab/api.php' );

	}

}

new Admin_Logs_API();
