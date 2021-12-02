<?php

namespace Uncanny_Automator;

/**
 * Class Add_WpFusion_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Wf_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Wf_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'WF' );
		$this->set_name( 'WP Fusion' );
		$this->set_icon( 'wp-fusion-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( '' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		if ( class_exists( 'WP_Fusion_Lite' ) || class_exists( 'WP_Fusion' ) ) {
			return true;
		}

		return false;
	}
}
