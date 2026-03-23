<?php

namespace Uncanny_Automator\Integrations\Events_Manager;

use EM_Event;
use Uncanny_Automator\Integration;

/**
 * Class Add_Em_Integration
 *
 * @package Uncanny_Automator
 */
class Events_Manager_Integration extends Integration {


	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Load triggers.
		new EM_EVENT_PUBLISHED( $this->helpers );
		new ANON_EM_REGISTER( $this->helpers );
		new EM_BOOKING_APPROVED( $this->helpers );
		new EM_REGISTER( $this->helpers );

		new Em_Tokens();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'EVENTSMANAGER' );
		$this->set_name( 'Events Manager' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/events-manager-icon.svg' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'EM_Events' );
	}
}
