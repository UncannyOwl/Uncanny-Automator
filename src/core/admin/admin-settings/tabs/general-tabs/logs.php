<?php

namespace Uncanny_Automator;

/**
 * Class Admin_Settings_General_Logs
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Agustin B.
 */
class Admin_Settings_General_Logs {
	/**
	 * Class constructor
	 */
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
			'automator_settings_general_tabs',
			function( $tabs ) {
				// General
				$tabs['logs'] = (object) array(
					'name'     => esc_html__( 'Logs', 'uncanny-automator' ),
					'function' => array( $this, 'tab_output' ),
					'preload'  => true, // Determines if the content should be loaded even if the tab is not selected
					'icon'     => 'th-list',
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
		// Load the view
		include Utilities::automator_get_view( 'admin-settings/tab/general/logs.php' );
	}
}

new Admin_Settings_General_Logs();
