<?php
namespace Uncanny_Automator;

/**
 * Class Add_Konnectz_It_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Konnectz_It_Integration {

	use Recipe\Integrations;

	public function __construct() {
		$this->setup();
	}

	/**
	 * Integration Set-up.
	 */
	protected function setup() {

		$this->set_integration( 'KONNECTZ_IT' );

		$this->set_name( 'KonnectzIT' );

		$this->set_icon( __DIR__ . '/img/konnectzit-icon.svg' );

		$this->set_developer_name( 'KonnectzIT' );
		$this->set_integration_type( 'built-in' );
		$this->set_distribution_type( 'saas' );
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
