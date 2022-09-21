<?php

namespace Uncanny_Automator;

/**
 * Class Add_Wpdm_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Wpdm_Integration {
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
		$this->set_integration( 'WPDM' );
		$this->set_name( 'WP Download Manager' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_icon( 'wordpress-download-manager-icon.svg' );
		$this->set_plugin_file_path( 'download-manager/download-manager.php' );
	}

	/**
	 * Method plugin_active
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'WPDM\WordPressDownloadManager' );
	}
}
