<?php

namespace Uncanny_Automator\Integrations\Duplicator;

/**
 * Class Duplicator_Integration
 * @package Uncanny_Automator
 */
class Duplicator_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Must use function in new integration to setup all required values
	 *
	 * @return mixed
	 */
	protected function setup() {
		$this->set_integration( 'DUPLICATOR' );
		$this->set_name( 'Duplicator' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/duplicator-icon.svg' );

		$this->set_plugin_file_path( 'duplicator/duplicator.php' );
		$this->set_plugin_variations( array( 'duplicator-pro/duplicator-pro.php' ) );
		$this->set_developer_name( 'Duplicator' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Load triggers.
		new BACKUP_COMPLETES_WITH_STATUS();

		// Load actions
		new INITIATE_A_BACKUP();
	}

	/**
	 * Check if Plugin is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'DUPLICATOR_VERSION' ) || defined( 'DUPLICATOR_PRO_VERSION' );
	}
}
