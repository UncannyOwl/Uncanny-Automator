<?php

namespace Uncanny_Automator\Integrations\Userfeedback;

use Uncanny_Automator\Integration;

/**
 * Class Userfeedback_Integration
 *
 * @package Uncanny_Automator
 */
class Userfeedback_Integration extends Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Userfeedback_Helpers();

		$this->set_integration( 'USERFEEDBACK' );
		$this->set_name( 'UserFeedback' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/userfeedback-icon.svg' );

		$this->set_plugin_file_path( 'userfeedback-lite/userfeedback.php' );
		$this->set_plugin_variations( array( 'userfeedback/userfeedback.php' ) );
		$this->set_developer_name( 'UserFeedback' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * Load triggers.
	 *
	 * @return void
	 */
	public function load() {

		new USER_USERFEEDBACK_SURVEY_SUBMITTED( $this->helpers );
		new ANON_USERFEEDBACK_SURVEY_SUBMITTED( $this->helpers );
	}

	/**
	 * Check whether the UserFeedback plugin is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( '\UserFeedback_Base' );
	}
}
