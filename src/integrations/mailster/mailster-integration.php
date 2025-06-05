<?php

namespace Uncanny_Automator\Integrations\Mailster;

/**
 * Class Mailster_Integration
 * @package Uncanny_Automator
 */
class Mailster_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Must use function in new integration to setup all required values
	 *
	 * @return mixed
	 */
	protected function setup() {
		$this->helpers = new Mailster_Helpers();
		$this->set_integration( 'MAILSTER' );
		$this->set_name( 'Mailster' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/mailster-icon.svg' );
	}

	/**
	 * Pass plugin path, i.e., uncanny-automator/uncanny-automator.php to check if plugin is active. By default it
	 * returns true for an integration.
	 *
	 * @return mixed|bool
	 */
	public function plugin_active() {
		return class_exists( 'Mailster' );
	}

	/**
	 * Load.
	 */
	protected function load() {
		//      load triggers
		new MAILSTER_SUBSCRIBER_ADDED_TO_LIST( $this->helpers );

		//      load actions
		new MAILSTER_ADD_SUBSCRIBER_TO_LIST( $this->helpers );
	}
}
