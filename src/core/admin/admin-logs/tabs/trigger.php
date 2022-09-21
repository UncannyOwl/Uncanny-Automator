<?php
/**
 * Uncanny_Automator\Admin_Logs_Trigger
 *
 * @since 4.5
 */
namespace Uncanny_Automator;

class Admin_Logs_Trigger {

	public function __construct() {

		// Define the tab
		$this->create_tab();

		add_filter( 'automator_core_class_logs_list_table', array( $this, 'modify_table_classes' ), 10, 2 );

	}

	/**
	 * Adds `uap-logs-trigger-table` class to trigger logs table.
	 *
	 * @param array $classes
	 * @param \Uncanny_Automator\Logs_List_Table $logs_list_table
	 *
	 * @return array The collection of classes.
	 */
	public function modify_table_classes( $classes, $logs_list_table ) {

		if ( 'trigger-log' === $logs_list_table->tab ) {

			$classes[] = 'uap-logs-trigger-table';

		}

		return $classes;

	}

	/**
	 * Adds the tab using the automator_settings_tab filter
	 */
	private function create_tab() {
		// Add the tab using the filter
		add_filter(
			'automator_admin_logs_top_level_tabs_items',
			function( $tabs ) {
				$tabs['trigger'] = (object) array(
					'name'     => esc_html__( 'Trigger', 'uncanny-automator' ),
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

		include Utilities::automator_get_view( 'admin-logs/tab/trigger.php' );

	}

}

new Admin_Logs_Trigger();
