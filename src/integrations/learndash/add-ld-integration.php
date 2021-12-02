<?php

namespace Uncanny_Automator;

/**
 * Class Add_Ld_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Ld_Integration {
	use Recipe\Integrations;

	/**
	 * Add_Ld_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'LD' );
		$this->set_name( 'LearnDash' );
		$this->set_icon( 'learndash-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'sfwd-lms/sfwd_lms.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'LEARNDASH_VERSION' );
	}
}
