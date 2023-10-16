<?php
/**
 * Uncanny_Automator\Admin_Tools_Debug_Debug
 *
 * @since   4.5
 */

namespace Uncanny_Automator;

/**
 *
 */
class Admin_Tools_Debug_Debug {

	/**
	 *
	 */
	const SETTINGS_GROUP_NAME = 'uncanny_automator_settings_debug';

	/**
	 *
	 */
	public function __construct() {

		// Define the tab
		$this->create_tab();

		// Define dynamic logs tab.
		$this->create_dynamic_logs_tab();

		// Register the debug option.
		add_action( 'admin_init', array( $this, 'register_settings' ) );

	}

	/**
	 * @return void
	 */
	public function register_settings() {

		register_setting( self::SETTINGS_GROUP_NAME, 'automator_settings_debug_enabled' );

		register_setting( self::SETTINGS_GROUP_NAME, 'automator_settings_debug_notices_enabled' );

	}

	/**
	 * @return void
	 */
	private function create_dynamic_logs_tab() {

		$log_files = $this->get_log_file_names();

		if ( ! empty( $log_files ) ) {

			foreach ( $log_files as $file ) {

				add_filter(
					'automator_admin_tools_debug_tabs',
					function ( $tabs ) use ( $file ) {

						$tab_id = strtolower( sanitize_file_name( $file ) );

						$ext = pathinfo( $tab_id, PATHINFO_EXTENSION );

						$allowed_file_types = array( AUTOMATOR_LOGS_EXT );

						if ( in_array( $ext, $allowed_file_types, true ) ) {
							$tabs[ $tab_id ] = (object) array(
								'name'     => str_replace(
									array(
										'.' . AUTOMATOR_LOGS_EXT,
									),
									'',
									$tab_id
								),
								'function' => array( $this, 'tab_log_output' ),
								'preload'  => false,
							);
						}

						return $tabs;
					},
					20,
					1
				);

			}
		}

	}

	/**
	 * Adds the tab using the automator_settings_tab filter
	 */
	private function create_tab() {
		// Add the tab using the filter
		add_filter(
			'automator_admin_tools_debug_tabs',
			function ( $tabs ) {
				$tabs['debug'] = (object) array(
					'name'     => esc_html__( 'Settings', 'uncanny-automator' ),
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

		// Load the view
		include Utilities::automator_get_view( 'admin-tools/tab/debug/debug.php' );

	}

	/**
	 * @return void
	 */
	public function tab_log_output() {

		include Utilities::automator_get_view( 'admin-tools/tab/debug/log-viewer.php' );

	}

	/**
	 * @return array|false|string[]
	 */
	public function get_log_file_names() {

		if ( ! defined( 'UA_DEBUG_LOGS_DIR' ) || ! is_dir( UA_DEBUG_LOGS_DIR ) ) {
			return false;
		}

		require_once trailingslashit( ABSPATH ) . 'wp-admin/includes/file.php';

		return array_map(
			function ( $file ) {
				return basename( $file );
			},
			list_files( UA_DEBUG_LOGS_DIR, 1, array() )
		);

	}

	/**
	 * @return false|string
	 */
	public function get_requested_log() {

		$requested_log = sanitize_file_name( automator_filter_input( 'debug' ) );

		if ( empty( $requested_log ) ) {
			return false;
		}

		$dir = trailingslashit( UA_DEBUG_LOGS_DIR );

		$log_file = $dir . $requested_log;
		$ext      = pathinfo( $log_file, PATHINFO_EXTENSION );

		if ( is_file( $log_file ) && in_array( $ext, array( AUTOMATOR_LOGS_EXT ), true ) ) {

			return file_get_contents(
				$log_file,
				false,
				null,
				0,
				10000
			);

		}

		return false;

	}

}

new Admin_Tools_Debug_Debug();
