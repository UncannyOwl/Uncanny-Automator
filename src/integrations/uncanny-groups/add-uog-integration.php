<?php

namespace Uncanny_Automator;

/**
 * Class Add_Uoa_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Uog_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Uog_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'UOG' );
		$this->set_name( 'Uncanny Groups' );
		$this->set_icon( 'uncanny-owl-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'uncanny-learndash-groups/uncanny-learndash-groups.php' );
	}
}
