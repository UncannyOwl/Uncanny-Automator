<?php

namespace Uncanny_Automator\Integrations\Eventin;

use Uncanny_Automator\Integrations\Eventin\Dispatchers\Eventin_Checkin_Dispatcher;
use Uncanny_Automator\Integrations\Eventin\Dispatchers\Eventin_Order_Dispatcher;

/**
 * Class Eventin_Integration
 *
 * @package Uncanny_Automator
 */
class Eventin_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup Automator integration.
	 *
	 * The plugin metadata below is declared identically in Pro
	 * (uncanny-automator-pro/src/integrations/eventin/eventin-integration.php).
	 * It has to be: `Automator_Functions::set_all_integrations()` replaces a
	 * registry entry wholesale rather than merging it, and Pro registers last,
	 * so a declaration made only here would be dropped at runtime.
	 *
	 * Every value comes from Eventin's own plugin header — never inferred from
	 * the slug. The folder and the entry file disagree: the directory is
	 * `wp-event-solution` while the main file is `eventin.php`.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Eventin_Helpers();
		$this->set_integration( 'EVENTIN' );
		$this->set_name( 'Eventin' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/eventin-icon.svg' );

		$this->set_plugin_file_path( 'wp-event-solution/eventin.php' );
		$this->set_developer_name( 'Themewinter' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * Always-on upstream listeners — registered in targeted mode too.
	 *
	 * The check-in dispatcher listens on WordPress' own meta hooks, which fire
	 * on the front end when the Eventin Pro QR scanner validates a ticket. The
	 * order dispatcher normalizes Eventin's two mutually exclusive
	 * order-completion pipelines into one event. In targeted mode the framework
	 * never calls load(); only this method runs. Booting them here is what makes
	 * those triggers fire at all outside the recipe builder.
	 *
	 * @return void
	 */
	protected function load_shared_hooks() {
		Eventin_Checkin_Dispatcher::boot();
		Eventin_Order_Dispatcher::boot();
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {

		// Register the always-on listeners in full-load mode too — the parent
		// only calls load_shared_hooks() in targeted mode, never alongside load().
		$this->load_shared_hooks();

		// Triggers.
		new Eventin_Ticket_Purchased( $this->helpers );
		new Eventin_Attendee_Registered( $this->helpers );
		new Eventin_Attendee_Checked_In( $this->helpers );
		new Eventin_Event_Created( $this->helpers );
		new Eventin_Event_Updated( $this->helpers );

		// Actions.
		new Eventin_Add_Attendee( $this->helpers );
		new Eventin_Check_In_Attendee( $this->helpers );
		new Eventin_Update_Order_Status( $this->helpers );
	}

	/**
	 * Check if Eventin is active.
	 *
	 * Eventin defines no version constant — the version lives in
	 * Wpeventin::version() (eventin.php:45). Gate on the class, not a constant.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( '\Wpeventin' );
	}
}
