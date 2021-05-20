<?php

namespace Uncanny_Automator;

/**
 * Class Add_Mec_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Mec_Integration {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'MEC';

	/**
	 * Add_Integration constructor. Do nothing for now.
	 *
	 * @return self.
	 */
	public function __construct() {
		return $this;
	}

	/**
	 * Only load this integration and its triggers and actions if the related plugin is active
	 *
	 * @param $status
	 * @param $code
	 *
	 * @return bool True if MEC class exists. Otherwise, false.
	 */
	public function plugin_active( $status, $code ) {

		if ( self::$integration === $code ) {
			if ( class_exists( 'MEC' ) ) {
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
	 * @return array The list of directories.
	 */
	public function add_integration_directory_func( $directory ) {

		$directory[] = dirname( __FILE__ ) . '/helpers';
		$directory[] = dirname( __FILE__ ) . '/triggers';
		$directory[] = dirname( __FILE__ ) . '/tokens';

		return $directory;
	}

	/**
	 * Register the integration by pushing it into the global automator object
	 *
	 * @return void.
	 */
	public function add_integration_func() {



		Automator()->register->integration(
			self::$integration,
			array(
				'name'     => 'M.E. Calendar',
				'icon_svg' => Utilities::automator_get_integration_icon( __DIR__ . '/img/modern-events-calendar-icon.svg' )
			)
		);
	}
}
