<?php
/**
 * Contains Integration class.
 *
 * @version 2.4.0
 * @since   2.4.0
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

defined( '\ABSPATH' ) || exit;

/**
 * Adds Integration to Automator.
 *
 * @since 2.4.0
 */
class Add_Tutorlms_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Tutorlms_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'TUTORLMS' );
		$this->set_name( 'Tutor LMS' );
		$this->set_icon( 'tutorlms-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'tutor/tutor.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( '\TUTOR\Tutor' );
	}
}
