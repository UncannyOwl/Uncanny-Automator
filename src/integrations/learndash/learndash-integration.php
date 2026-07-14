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
	 * Shared hooks required for LearnDash execution.
	 *
	 * Loopable tokens must run even in targeted mode (@see Recipe_Manifest)
	 * so the Token Loop can find them regardless of which triggers/actions
	 * a given recipe actually uses.
	 *
	 * @return void
	 */
	protected function load_shared_hooks() {

		// Loopable tokens — migrated from old add-ld-integration.php.
		// \Uncanny_Automator\Integration has no set_loopable_tokens(), unlike the
		// old Recipe\Integrations trait, so register directly (matches EDD_Integration).
		( new User_Enrolled_Courses( 'LD' ) )->register_hooks();
		( new User_Enrolled_Groups( 'LD' ) )->register_hooks();
		( new User_Completed_Courses( 'LD' ) )->register_hooks();
	}

	/**
	 * Load triggers, actions, and legacy token classes.
	 *
	 * @return void
	 */
	public function load() {

		$this->load_shared_hooks();

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
