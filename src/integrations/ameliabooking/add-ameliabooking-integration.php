<?php
namespace Uncanny_Automator;

/**
 * Class Add_Ameliabooking_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Ameliabooking_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}


	/**
	 * Integration Set-up.
	 */
	protected function setup() {
		$this->set_integration( 'AMELIABOOKING' );
		$this->set_name( 'Amelia' );
		$this->set_icon( __DIR__ . '/img/amelia-icon.svg' );
	}

	/**
	 * Explicitly return true because its a 3rd-party plugin.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( '\AmeliaBooking\Plugin' );
	}
}
