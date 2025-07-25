<?php

namespace Uncanny_Automator\Integrations\Thrive_Apprentice;

/**
 * Class Thrive_Apprentice_Integration
 *
 * @package Uncanny_Automator
 */
class Thrive_Apprentice_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Integration Set-up.
	 */
	protected function setup() {
		$this->helpers = new Thrive_Apprentice_Helpers();
		$this->set_integration( 'THRIVE_APPRENTICE' );
		$this->set_name( 'Thrive Apprentice' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/thrive-apprentice-icon.svg' );
	}

	public function load() {
		//actions
		new THRIVE_APPRENTICE_GRADE_ASSESSMENT($this->helpers);
		new THRIVE_APPRENTICE_ISSUE_USER_CERTIFICATE($this->helpers);
		new THRIVE_APPRENTICE_UNLOCK_CONTENT($this->helpers);

		//triggers
		new THRIVE_APPRENTICE_CONTENT_UNLOCKED($this->helpers);
		new THRIVE_APPRENTICE_USER_CERTIFICATE_VERIFIED($this->helpers);
		new THRIVE_APPRENTICE_USER_COMPLETES_ALL_FREE_LESSONS_IN_PREMIUM_COURSE($this->helpers);
		new THRIVE_APPRENTICE_USER_COURSE_COMPLETED($this->helpers);
		new THRIVE_APPRENTICE_USER_COURSE_LESSON_COMPLETED($this->helpers);
		new THRIVE_APPRENTICE_USER_COURSE_MODULE_COMPLETED($this->helpers);
		new THRIVE_APPRENTICE_USER_DOWNLOADS_CERTIFICATE_FROM_COURSE($this->helpers);
		new THRIVE_APPRENTICE_USER_FAIL_ASSESSMENT_IN_COURSE($this->helpers);
		new THRIVE_APPRENTICE_USER_PASS_ASSESSMENT_IN_COURSE($this->helpers);
		new THRIVE_APPRENTICE_USER_SUBMITS_ASSESSMENT_IN_COURSE($this->helpers);
		new THRIVE_APPRENTICE_USER_PRODUCT_ACCESS_RECEIVED($this->helpers);
	}


	/**
	 * Determines whether the integration should be loaded or not.
	 *
	 * Checks whether an existing dependency condition is satisfied.
	 *
	 * @return bool Returns true if \TVA_Manager class is active. Returns false, otherwise.
	 */
	public function plugin_active() {
		return class_exists( '\TVA_Const', false );
	}
}
