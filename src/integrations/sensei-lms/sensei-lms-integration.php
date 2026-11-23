<?php

namespace Uncanny_Automator\Integrations\Sensei_Lms;

use Uncanny_Automator\Integration;

/**
 * Class Sensei_Lms_Integration
 *
 * @package Uncanny_Automator\Integrations\Sensei_Lms
 */
class Sensei_Lms_Integration extends Integration {

	/**
	 * Metadata only. The helper is assigned here so targeted loading, which skips
	 * load(), can still build parts with it.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Sensei_Lms_Helpers();

		$this->set_integration( 'SENSEI_LMS' );
		$this->set_name( 'Sensei LMS' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/sensei-lms-icon.svg' );

		// Identical in Pro: Pro's registration replaces Free's.
		$this->set_plugin_file_path( 'sensei-lms/sensei-lms.php' );
		$this->set_developer_name( 'Automattic' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * Run-time listeners. Called first from load(), and alone in targeted mode.
	 *
	 * @return void
	 */
	public function load_shared_hooks() {
		Course_Completed_Dispatcher::boot();
		Lesson_Completed_Dispatcher::boot();
		Quiz_Submitted_Dispatcher::boot();
	}

	/**
	 * @return void
	 */
	public function load() {

		$this->load_shared_hooks();

		// Triggers.
		new Sensei_User_Enrolled_Course( $this->helpers );
		new Sensei_User_Completes_Course( $this->helpers );
		new Sensei_User_Completes_Lesson( $this->helpers );
		new Sensei_User_Submits_Quiz( $this->helpers );

		// Actions.
		new Sensei_Enroll_User_In_Course( $this->helpers );
		new Sensei_Unenroll_User_From_Course( $this->helpers );
		new Sensei_Mark_Lesson_Complete( $this->helpers );
	}

	/**
	 * Sensei's own duplicate-install guard keys on this class.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( '\Sensei_Main' );
	}
}
