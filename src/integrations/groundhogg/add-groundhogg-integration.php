<?php

namespace Uncanny_Automator;

/**
 * Class Add_Groundhogg_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Groundhogg_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Groundhogg_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'GH' );
		$this->set_name( 'Groundhogg' );
		$this->set_icon( 'groundhogg-icon-.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'groundhogg/groundhogg.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'GROUNDHOGG_VERSION' );
	}
}
