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

		$this->set_plugin_file_path( 'uncanny-automator/uncanny-automator.php' );
		$this->set_developer_name( 'Uncanny Automator' );
		$this->set_integration_type( 'built-in' );
		$this->set_distribution_type( 'wp_org' );

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
