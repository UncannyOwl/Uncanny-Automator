<?php

namespace Uncanny_Automator;

/**
 * Class Add_Masterstudy_Integration
 * @package Uncanny_Automator
 */
class Add_Masterstudy_Integration {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'MSLMS';

	/**
	 * Add_Masterstudy_Integration constructor.
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
			if ( defined( 'STM_LMS_FILE' ) ) {
				$status = true;
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

		$directory[] = dirname( __FILE__ ) . '/helpers';
		$directory[] = dirname( __FILE__ ) . '/actions';
		$directory[] = dirname( __FILE__ ) . '/triggers';
		$directory[] = dirname( __FILE__ ) . '/tokens';

		return $directory;
	}

	/**
	 * Register the integration by pushing it into the global automator object
	 */
	public function add_integration_func() {



		Automator()->register->integration( self::$integration, array(
			'name'     => 'MasterStudy LMS',
			'logo_svg' => Utilities::automator_get_integration_icon( __DIR__ . '/img/masterstudy-lms.svg' ),
			'icon_svg' => Utilities::automator_get_integration_icon( __DIR__ . '/img/masterstudy-lms.svg' ),
		) );
	}
}
