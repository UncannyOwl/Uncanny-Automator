<?php

namespace Uncanny_Automator\Integrations\Thrive_Architect;

/**
 * Class Thrive_Architect_Integration
 *
 * @package Uncanny_Automator\Integrations\Thrive_Architect
 */
class Thrive_Architect_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setups the integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->set_integration( 'THRIVE_ARCHITECT' );
		$this->set_name( 'Thrive Architect' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/thrive-architect-icon.svg' );

		$this->set_plugin_file_path( 'thrive-visual-editor/thrive-visual-editor.php' );
		$this->set_developer_name( 'Thrive Themes' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'commercial' );
	}

	/**
	 * Selectively instantiates the components.
	 *
	 * @return void
	 */
	protected function load() {
		new FORM_SUBMITTED();
		new USER_FORM_SUBMITTED();
		new USER_REGISTERED();
	}

	/**
	 * Determinines whether the integration should show up or not.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'TVE_IN_ARCHITECT' );
	}
}
