<?php

namespace Uncanny_Automator\Integrations\Uncanny_Groups;

/**
 * Class Uog_Integration
 *
 * @package Uncanny_Automator
 */
class Uog_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Uog_Helpers();
		$this->set_integration( 'UOG' );
		$this->set_name( 'Uncanny Groups' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/uncanny-owl-icon.svg' );
	}

	/**
	 * Load triggers and actions.
	 *
	 * @return void
	 */
	public function load() {
		// Legacy token class for backward compatibility.
		new \Uncanny_Automator\Uncanny_Groups_Tokens();

		// Triggers.
		new UOG_GROUPCREATED( $this->helpers );
		new UOG_REGISTERED_WITH_GROUPKEY( $this->helpers );
		new UOG_USERREDEEMS_GROUPKEY( $this->helpers );
		new UOG_SEATSADDEDTOGROUP( $this->helpers );
		new UOG_SEATSREMOVEDFROMGROUP( $this->helpers );

		// Actions.
		new UOG_CREATEUNCANNYGROUP( $this->helpers );
		new UOG_ADDSEATSTOGROUP( $this->helpers );
		new UOG_REMOVESEATSFROMGROUP( $this->helpers );
	}

	/**
	 * Check if Uncanny Groups is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'UNCANNY_GROUPS_VERSION' );
	}
}
