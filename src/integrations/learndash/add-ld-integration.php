<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Integrations\LearnDash\Tokens\Loopable\Universal\User_Completed_Courses;
use Uncanny_Automator\Integrations\LearnDash\Tokens\Loopable\Universal\User_Enrolled_Courses;
use Uncanny_Automator\Integrations\LearnDash\Tokens\Loopable\Universal\User_Enrolled_Groups;

/**
 * Class Add_Ld_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Ld_Integration {

	use Recipe\Integrations;

	/**
	 * @var string
	 */
	const INTEGRATION_ID = 'LD';

	/**
	 * Add_Ld_Integration constructor.
	 */
	public function __construct() {

		// Set-up the trigger.
		$this->setup();

		$this->create_loopable_tokens();
	}

	/**
	 * Setup the action.
	 */
	protected function setup() {

		$this->set_integration( self::INTEGRATION_ID );
		$this->set_name( 'LearnDash' );
		$this->set_icon( 'learndash-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'sfwd-lms/sfwd_lms.php' );
		$this->set_loopable_tokens( $this->create_loopable_tokens() );

	}

	/**
	 * Determines whether LD is active or not active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'LEARNDASH_VERSION' );
	}

	/**
	 * Create loopable tokens.
	 *
	 * @return array
	 */
	public function create_loopable_tokens() {

		return array(
			'ENROLLED_COURSES' => User_Enrolled_Courses::class,
			'ENROLLED_GROUPS'  => User_Enrolled_Groups::class,
			'COMPLETED_COURSE' => User_Completed_Courses::class,
		);

	}
}
