<?php

namespace Uncanny_Automator\Integrations\Formidable;

/**
 * Class Formidable_Integration
 *
 * @package Uncanny_Automator
 */
class Formidable_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Formidable_Helpers();
		$this->set_integration( 'FI' );
		$this->set_name( 'Formidable' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/formidable-forms-icon.svg' );

		$this->set_plugin_file_path( 'formidable/formidable.php' );
		$this->set_developer_name( 'Strategy11' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * Load triggers and actions.
	 *
	 * @return void
	 */
	public function load() {
		new FI_SUBMITFORM( $this->helpers );
		new ANON_FI_SUBMITFORM( $this->helpers );
	}

	/**
	 * Check whether Formidable Forms is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'FrmHooksController' );
	}
}
