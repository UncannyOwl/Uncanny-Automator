<?php

namespace Uncanny_Automator\Integrations\Everest_Forms;

/**
 * Class Everest_Forms_Integration
 * @package Uncanny_Automator
 */
class Everest_Forms_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Must use function in new integration to setup all required values
	 *
	 * @return mixed
	 */
	protected function setup() {
		$this->helpers = new Everest_Forms_Helpers();
		$this->set_integration( 'EVEREST_FORMS' );
		$this->set_name( 'Everest Forms' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/everest-forms-icon.svg' );
	}

	/**
	 * @return void
	 */
	protected function load() {
		// Load triggers
		new USER_SUBMITS_FORM( $this->helpers );
		new ANON_FORM_SUBMITTED( $this->helpers );
	}

	/**
	 * @return bool|mixed
	 */
	public function plugin_active() {
		return class_exists( 'EverestForms' );
	}
}
