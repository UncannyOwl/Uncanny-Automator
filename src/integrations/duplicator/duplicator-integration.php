<?php

namespace Uncanny_Automator\Integrations\Duplicator;

/**
 * Class Duplicator_Integration
 * @package Uncanny_Automator
 */
class Duplicator_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Must use function in new integration to setup all required values
	 *
	 * @return mixed
	 */
	protected function setup() {
		$this->set_integration( 'DUPLICATOR' );
		$this->set_name( 'Duplicator' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/duplicator-icon.svg' );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Load triggers.
		new BACKUP_COMPLETES_WITH_STATUS();

		// Load actions
		new INITIATE_A_BACKUP();
	}

	/**
	 * Check if Plugin is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'DUPLICATOR_VERSION' ) || defined( 'DUPLICATOR_PRO_VERSION' );
	}
}
