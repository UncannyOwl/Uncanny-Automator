<?php
namespace Uncanny_Automator;

class Add_Trello_Integration {

	use Recipe\Integrations;

	public function __construct() {
		$this->setup();
	}

	protected function setup() {

		require_once __DIR__ . '/functions/trello-functions.php';

		$functions = new Trello_Functions();

		$this->set_integration( 'TRELLO' );

		$this->set_name( 'Trello' );

		$this->set_icon( __DIR__ . '/img/trello-icon.svg' );

		$this->set_connected( $functions->integration_status() );

		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'trello-api' ) );

	}

	/**
	 * Method plugin_active
	 *
	 * @return bool True, always.
	 */
	public function plugin_active() {
		return true;
	}
}
