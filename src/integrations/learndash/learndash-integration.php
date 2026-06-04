<?php

namespace Uncanny_Automator\Integrations\Learndash;

use Uncanny_Automator\Integrations\LearnDash\Tokens\Loopable\Universal\User_Completed_Courses;
use Uncanny_Automator\Integrations\LearnDash\Tokens\Loopable\Universal\User_Enrolled_Courses;
use Uncanny_Automator\Integrations\LearnDash\Tokens\Loopable\Universal\User_Enrolled_Groups;

/**
 * Class Ld_Integration
 *
 * @package Uncanny_Automator
 */
class Ld_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Ld_Helpers();

		$this->set_integration( 'LD' );
		$this->set_name( 'LearnDash' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/learndash-icon.svg' );
		$this->set_plugin_file_path( 'sfwd-lms/sfwd_lms.php' );

		// @deprecated 7.2 — Singleton shim for old Pro code calling
		// Automator()->helpers->recipe->learndash->options->method().
		// Prevents fatal errors in Free 7.2 + Old Pro <7.2 scenario.
		// Migrated code MUST use $this->item_helpers instead.
		\Automator()->helpers->recipe->learndash = $this->helpers;
	}

	/**
	 * Load triggers, actions, and legacy token classes.
	 *
	 * @return void
	 */
	public function load() {

		// Loopable tokens — migrated from old add-ld-integration.php.
		// Must be in load() not setup() — setup() runs before plugin_active() check.
		if ( method_exists( $this, 'set_loopable_tokens' ) ) {
			$this->set_loopable_tokens(
				array(
					'ENROLLED_COURSES' => User_Enrolled_Courses::class,
					'ENROLLED_GROUPS'  => User_Enrolled_Groups::class,
					'COMPLETED_COURSE' => User_Completed_Courses::class,
				)
			);
		}

		// Old token class — self-guards in __construct() when modern integration exists.
		new \Uncanny_Automator\Ld_Tokens();

		// Free triggers.
		new LD_COURSEDONE( $this->helpers );
		new LD_LESSONDONE( $this->helpers );
		new LD_TOPICDONE( $this->helpers );
		new LD_QUIZDONE( $this->helpers );
		new LD_PASSQUIZ( $this->helpers );
		new LD_FAILQUIZ( $this->helpers );
		new LD_QUIZPERCENT( $this->helpers );
		new LD_QUIZPOINT( $this->helpers );
		new LD_QUIZSCORE( $this->helpers );

		// Already modern (v4) — namespace updated, wired in.
		new LD_COURSE_PROGRESS_PERCENTAGE( $this->helpers );

		// Free actions.
		new LD_MARKCOURSEDONE( $this->helpers );
		new LD_MARKLESSONDONE( $this->helpers );
		new LD_MARKTOPICDONE( $this->helpers );
		new LD_ENRLCOURSE_A( $this->helpers );
		new LD_CREATEGROUP( $this->helpers );
		new LD_MAKEUSERLEADER( $this->helpers );
	}

	/**
	 * Check if LearnDash is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'LEARNDASH_VERSION' );
	}

}
