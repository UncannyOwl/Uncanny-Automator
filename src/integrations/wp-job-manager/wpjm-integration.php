<?php

namespace Uncanny_Automator\Integrations\Wpjm;

/**
 * Class Wpjm_Integration
 *
 * @package Uncanny_Automator\Integrations\Wpjm
 */
class Wpjm_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup integration
	 */
	protected function setup() {
		$this->helpers = new Wpjm_Helpers();
		$this->set_integration( 'WPJM' );
		$this->set_name( 'WP Job Manager' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/wp-job-manager-icon.svg' );
	}

	/**
	 * Load triggers and actions
	 */
	public function load() {
		// Load triggers
		new Wpjm_Submitjob( $this->helpers );
		new Wpjm_Jobapplication( $this->helpers );
		new Wpjm_Submitresume( $this->helpers );
	}

	/**
	 * Hooks that must run regardless of lazy-trigger loading.
	 *
	 * The "User submits a job application" trigger is lazy-loaded, so its
	 * hydrate_tokens() runs when the Trigger_Queue drains — outside the original
	 * request scope, where filter_input( INPUT_POST ) is empty. The résumé chosen
	 * on the application form (wp-job-manager-resumes) arrives ONLY in that POST,
	 * so we persist it onto the application here, synchronously at submission
	 * time, before the deferred hydrate runs.
	 *
	 * @return void
	 */
	protected function load_shared_hooks() {
		add_action( 'new_job_application', array( $this, 'persist_application_resume_id' ), 10, 1 );
	}

	/**
	 * Capture the résumé chosen on the application form and persist it onto the
	 * application while the POST request is still live.
	 *
	 * @param int $application_id The new application post ID.
	 *
	 * @return void
	 */
	public function persist_application_resume_id( $application_id ) {

		if ( ! automator_filter_has_var( 'wp_job_manager_resumes_apply_with_resume', INPUT_POST ) ) {
			return;
		}

		$this->save_application_resume_id(
			$application_id,
			absint( automator_filter_input( 'wp_job_manager_resumes_apply_with_resume', INPUT_POST ) )
		);
	}

	/**
	 * Persist the résumé ID onto an application post.
	 *
	 * Pure side-effect helper (no request state) so the persistence rule stays
	 * unit-testable and independent of when/where it is invoked.
	 *
	 * @param int $application_id Application post ID.
	 * @param int $resume_id      Résumé post ID.
	 *
	 * @return bool True when the meta was written.
	 */
	public function save_application_resume_id( $application_id, $resume_id ) {

		$application_id = absint( $application_id );
		$resume_id      = absint( $resume_id );

		// Skip empties and the degenerate self-reference the legacy code guarded against.
		if ( $application_id < 1 || $resume_id < 1 || $resume_id === $application_id ) {
			return false;
		}

		update_post_meta( $application_id, '_resume_id', $resume_id );

		return true;
	}

	/**
	 * Check if plugin is active
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'WP_Job_Manager' );
	}
}
