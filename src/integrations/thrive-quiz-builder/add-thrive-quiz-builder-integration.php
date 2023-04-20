<?php

namespace Uncanny_Automator;

/**
 * Class Add_Thrive_Quiz_Builder_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Thrive_Quiz_Builder_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Thrive_Quiz_Builder_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'THRIVE_QB' );
		$this->set_name( 'Thrive Quiz Builder' );
		$this->set_icon( 'thrive-quiz-builder-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'thrive-quiz-builder/thrive-quiz-builder.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'Thrive_Quiz_Builder' );
	}
}
