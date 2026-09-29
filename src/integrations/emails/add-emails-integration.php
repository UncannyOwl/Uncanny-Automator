<?php
namespace Uncanny_Automator;

/**
 * Add_Emails_Integration class
 *
 * @package Uncanny_Automator
 */
class Add_Emails_Integration {

	use Recipe\Integrations;

	/**
	 * Contruct.
	 *
	 * @return void
	 */
	public function __construct() {

		$this->setup();

	}

	/**
	 * Setup.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->set_integration( 'EMAILS' );

		$this->set_name( 'Emails' );

		$this->set_icon( 'emails-icon.svg' );

		$this->set_icon_path( __DIR__ . '/img/' );

		$this->set_plugin_file_path( 'uncanny-automator/uncanny-automator.php' );
		$this->set_developer_name( 'Uncanny Automator' );
		$this->set_integration_type( 'built-in' );
		$this->set_distribution_type( 'wp_org' );

	}

	/**
	 * Integration dependencies.
	 *
	 * @return boolean True
	 */
	public function plugin_active() {

		return true;

	}
}
