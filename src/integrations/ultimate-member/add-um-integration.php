<?php

namespace Uncanny_Automator;

/**
 * Class Add_Um_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Um_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Um_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'UM' );
		$this->set_name( 'Ultimate Member' );
		$this->set_icon( 'ultimate-member-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'ultimate-member/ultimate-member.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'UM' ) || defined( 'um_url' );
	}
}
