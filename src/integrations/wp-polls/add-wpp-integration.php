<?php

namespace Uncanny_Automator;

/**
 * Class Add_Wpp_Integration
 * @package Uncanny_Automator
 */
class Add_Wpp_Integration {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WPP';

	/**
	 * Add_Integration constructor.
	 */
	public function __construct() {
	}

	/**
	 * Only load this integration and its triggers and actions if the related plugin is active
	 *
	 * @param $status
	 * @param $plugin
	 *
	 * @return bool
	 */
	public function plugin_active( $status, $plugin ) {


		if ( self::$integration === $plugin ) {
			if ( defined( 'WP_POLLS_VERSION' ) ) {
				// Needs automator free with version 2.10 or greater
				//$automator_version = \Uncanny_Automator_Pro\InitializePlugin::PLUGIN_VERSION;

				//if ( version_compare( $automator_version, '2.10', '>=' ) ) {
					$status = true;
				//}
			} else {
				$status = false;
			}
		}

		return $status;
	}

	/**
	 * Set the directories that the auto loader will run in
	 *
	 * @param $directory
	 *
	 * @return array
	 */
	public function add_integration_directory_func( $directory ) {

		$directory[] = dirname( __FILE__ ) . '/actions';
		$directory[] = dirname( __FILE__ ) . '/helpers';
		$directory[] = dirname( __FILE__ ) . '/triggers';
		$directory[] = dirname( __FILE__ ) . '/tokens';

		return $directory;
	}

	/**
	 * Register the integration by pushing it into the global automator object
	 */
	public function add_integration_func() {

		global $uncanny_automator;

		$uncanny_automator->register->integration( self::$integration, array(
			'name'        => 'WP-Polls',
			'icon_svg'    => Utilities::get_integration_icon( 'wp-polls-icon.svg' ),
		) );
	}
}
