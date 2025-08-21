<?php

namespace Uncanny_Automator\Integrations\Thrive_Quiz_Builder;

/**
 * Class Add_Thrive_Quiz_Builder_Integration
 *
 * @package Uncanny_Automator
 */
class Thrive_Quiz_Builder_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup the integration configuration and helpers for Thrive Quiz Builder.
	 */
	protected function setup() {
		$this->helpers = new Thrive_Quiz_Builder_Helpers();
		$this->set_integration( 'THRIVE_QB' );
		$this->set_name( 'Thrive Quiz Builder' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/thrive-quiz-builder-icon.svg' );
	}

	/**
	 * Bootstrap actions, triggers, settings page, etc.
	 *
	 * @return void
	 */
	public function load() {

		//triggers
		new ANON_THRIVE_QB_QUIZ_COMPLETED( $this->helpers );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'Thrive_Quiz_Builder', false );
	}
}
