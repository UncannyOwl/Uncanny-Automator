<?php

namespace Uncanny_Automator\Integrations\Sure_Forms;

/**
 * Class Sure_Forms_Integration
 *
 * @package Uncanny_Automator
 */
class Sure_Forms_Integration extends \Uncanny_Automator\Integration {
	/**
	 * Integration Set-up.
	 */
	protected function setup() {
		$this->helpers = new Sure_Forms_Helpers();
		//$this->register_hooks();

		$this->set_integration( 'SURE_FORMS' );
		$this->set_name( 'SureForms' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/sureforms-icon.svg' );
	}

	/**
	 * Load method to instantiate dependencies.
	 */
	public function load() {
		//triggers
		new USER_SUBMITS_FORM( $this->helpers );
		new ANON_FORM_SUBMITTED( $this->helpers );
	}

	/**
	 * @return bool|mixed
	 */
	public function plugin_active() {
		return class_exists( 'SRFM\Inc\Post_Types' );
	}
}
