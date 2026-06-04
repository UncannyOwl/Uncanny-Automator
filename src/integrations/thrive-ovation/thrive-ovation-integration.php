<?php

namespace Uncanny_Automator\Integrations\Thrive_Ovation;

use Uncanny_Automator\Integration;

/**
 * Class Thrive_Ovation_Integration
 *
 * @package Uncanny_Automator\Integrations\Thrive_Ovation
 */
class Thrive_Ovation_Integration extends Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Thrive_Ovation_Helpers();
		$this->set_integration( 'THRIVE_OVATION' );
		$this->set_name( 'Thrive Ovation' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/thrive-ovation-icon.svg' );
	}

	/**
	 * Load triggers.
	 *
	 * @return void
	 */
	public function load() {
		new THRIVE_OVATION_TESTIMONIAL_CREATED( $this->helpers );
	}

	/**
	 * Check whether Thrive Ovation is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'TVO_PLUGIN_FILE_PATH' );
	}
}
