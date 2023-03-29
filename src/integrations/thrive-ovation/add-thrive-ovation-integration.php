<?php

namespace Uncanny_Automator;

/**
 * Class Add_Thrive_Ovation_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Thrive_Ovation_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Advanced_Ads_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'THRIVE_OVATION' );
		$this->set_name( 'Thrive Ovation' );
		$this->set_icon( 'thrive-ovation-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'thrive-ovation/thrive-ovation.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'TVO_PLUGIN_FILE_PATH' );
	}

}
