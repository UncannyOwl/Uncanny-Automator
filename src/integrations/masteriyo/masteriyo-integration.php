<?php

namespace Uncanny_Automator\Integrations\Masteriyo;

use Uncanny_Automator\Integration;

/**
 * Class Masteriyo_Integration
 *
 * @package Uncanny_Automator
 */
class Masteriyo_Integration extends Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Masteriyo_Helpers();

		$this->set_integration( 'MASTERIYO' );
		$this->set_name( 'Masteriyo LMS' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/masteriyo-icon.svg' );

		// Identical in Pro: Pro's registration replaces Free's.
		$this->set_plugin_file_path( 'learning-management-system/lms.php' );
		$this->set_developer_name( 'Masteriyo' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
		$this->set_plugin_variations( array( 'learning-management-system-pro/lms.php' ) );
	}

	/**
	 * Run-time listeners. Called first from load(), and alone in targeted mode.
	 *
	 * @return void
	 */
	protected function load_shared_hooks() {
		Masteriyo_Enrollment_Dispatcher::boot();
	}

	/**
	 * Load triggers and actions.
	 *
	 * @return void
	 */
	public function load() {

		$this->load_shared_hooks();

		// Triggers.
		new Masteriyo_User_Enrolls_Course( $this->helpers );
		new Masteriyo_User_Completes_Course( $this->helpers );
		new Masteriyo_User_Completes_Lesson( $this->helpers );
		new Masteriyo_User_Passes_Quiz( $this->helpers );
		new Masteriyo_User_Fails_Quiz( $this->helpers );
		new Masteriyo_User_Starts_Quiz( $this->helpers );
		new Masteriyo_User_Registers( $this->helpers );

		// Actions.
		new Masteriyo_Enroll_User_In_Course( $this->helpers );
		new Masteriyo_Mark_Course_Complete( $this->helpers );
	}

	/**
	 * Check if Masteriyo LMS is active.
	 *
	 * Masteriyo ships free and pro as one codebase — the free plugin bails out
	 * when the pro build is active, so only ever one of them defines
	 * MASTERIYO_VERSION. Gating on that constant covers both editions.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'MASTERIYO_VERSION' );
	}
}
