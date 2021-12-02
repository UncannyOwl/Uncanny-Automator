<?php

namespace Uncanny_Automator;

/**
 * Class Add_Mailpoet_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Mailpoet_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Mailpoet_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'MAILPOET' );
		$this->set_name( 'MailPoet' );
		$this->set_icon( 'mailpoet-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'mailpoet/mailpoet.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( '\MailPoet\Config\Activator' );
	}
}
