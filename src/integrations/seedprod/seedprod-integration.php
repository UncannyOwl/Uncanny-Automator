<?php
namespace Uncanny_Automator\Integrations\Seedprod;

/**
 * Class Seedprod
 *
 * @package Uncanny_Automator\Integrations\Seedprod
 */
class Seedprod_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setups the integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->set_integration( 'SEEDPROD' );
		$this->set_name( 'SeedProd' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/seedprod-icon.svg' );
	}

	/**
	 * Load all components.
	 *
	 * @return void
	 */
	protected function load() {
		new OPTIN_FORM_SUBMITTED();
	}

	/**
	 * Determinines whether the integration should show up or not.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'SEEDPROD_PRO_BUILD' );
	}
}
