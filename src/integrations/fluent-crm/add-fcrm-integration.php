<?php

namespace Uncanny_Automator;

/**
 * Class Add_Wpff_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Fcrm_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Fcrm_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'FCRM' );
		$this->set_name( 'FluentCRM' );
		$this->set_icon( 'fluent-crm-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'fluent-crm/fluent-crm.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'FLUENTCRM' );
	}
}
