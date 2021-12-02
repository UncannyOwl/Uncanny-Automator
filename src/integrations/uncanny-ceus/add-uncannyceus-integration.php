<?php

namespace Uncanny_Automator;

/**
 * Class Add_Uc_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Uncannyceus_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Uncannyceus_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'UNCANNYCEUS' );
		$this->set_name( 'Uncanny CEUs' );
		$this->set_icon( 'uncanny-owl-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'uncanny-continuing-education-credits/uncanny-continuing-education-credits.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'CEU_PLUGIN_NAME' );
	}
}
