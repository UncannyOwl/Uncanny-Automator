<?php

namespace Uncanny_Automator;

/**
 * Class Add_Wp_Foro_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Wp_Foro_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Wp_Foro_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'WPFORO' );
		$this->set_name( 'wpForo' );
		$this->set_icon( 'wpforo-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'wpforo/wpforo.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'WPFORO_VERSION' );
	}
}
