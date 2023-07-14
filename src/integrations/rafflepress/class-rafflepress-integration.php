<?php

namespace Uncanny_Automator\Integrations\RafflePress;

/**
 * Class RafflePress_Integration
 *
 * @package Uncanny_Automator
 */
class RafflePress_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Rafflepress_Helpers();
		$this->set_integration( 'RAFFLE_PRESS' );
		$this->set_name( 'RafflePress' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/rafflepress-icon.svg' );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Load triggers.
		new RAFFLEPRESS_ANON_REGISTERS_GIVEAWAY( $this->helpers );
	}

	/**
	 * Check if Plugin is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'RAFFLEPRESS_BUILD' ) || defined( 'RAFFLEPRESS_PRO_BUILD' );
	}

}
