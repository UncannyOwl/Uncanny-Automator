<?php
namespace Uncanny_Automator;

/**
 * Class Add_Webhooks_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Webhooks_Integration {

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

		$this->set_integration( 'WEBHOOKS' );

		$this->set_name( 'Webhooks' );

		$this->set_icon( __DIR__ . '/img/webhooks-icon.svg' );

	}

	/**
	 * Explicitly return true because it doesn't depend on any 3rd-party plugin.
	 *
	 * @return bool
	 */
	public function plugin_active() {

		return true;

	}
}
