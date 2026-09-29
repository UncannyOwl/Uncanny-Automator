<?php

namespace Uncanny_Automator\Integrations\Uncanny_Toolkit;

/**
 * Class Ut_Integration
 *
 * @package Uncanny_Automator
 */
class Ut_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Ut_Helpers();
		$this->set_integration( 'UNCANNYTOOLKIT' );
		$this->set_name( 'Uncanny Toolkit' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/uncanny-owl-icon.svg' );

		$this->set_plugin_file_path( 'uncanny-learndash-toolkit/uncanny-learndash-toolkit.php' );
		$this->set_developer_name( 'Uncanny Owl' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * Load triggers and actions.
	 *
	 * @return void
	 */
	public function load() {
		// Legacy token class for backward compatibility.
		new \Uncanny_Automator\Ut_Tokens();

		// Triggers.
		new UT_USER_IMPORTED( $this->helpers );
		new UT_USER_IMPORTED_IN_COURSE( $this->helpers );
		new UT_USER_IMPORTED_IN_GROUP( $this->helpers );
		new UT_GROUP_LEADER_IMPORTED( $this->helpers );
		new UT_USERS_TIME_IN_COURSE_EXCEEDS( $this->helpers );

		// Actions.
		new UT_RESETUSERSTIMEINCOURSE( $this->helpers );
	}

	/**
	 * Check if Uncanny Toolkit is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'UNCANNY_TOOLKIT_VERSION' );
	}
}
