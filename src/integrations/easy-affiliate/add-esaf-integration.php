<?php

namespace Uncanny_Automator;

/**
 * Class Add_Esaf_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Esaf_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Edd_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'ESAF' );
		$this->set_name( 'Easy Affiliate' );
		$this->set_icon( 'easy-affiliate-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'easy-affiliate/easy-affiliate.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'ESAF_PLUGIN_SLUG' );
	}
}
