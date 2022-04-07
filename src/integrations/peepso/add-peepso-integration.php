<?php

namespace Uncanny_Automator;

/**
 * Class Add_PeepSo_Integration
 *
 * @package Uncanny_Automator
 */
class Add_PeepSo_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Nf_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'PP' );
		$this->set_name( 'PeepSo' );
		$this->set_icon( 'peepso-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'peepso-core/peepso.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'PeepSo' );
	}

}
