<?php

namespace Uncanny_Automator\Integrations\Uncanny_Ceus;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Uncanny_Ceus_Helpers
 *
 * @package Uncanny_Automator\Integrations\Uncanny_Ceus
 */
class Uncanny_Ceus_Helpers extends Abstract_Helpers {

	/**
	 * Lazy-instantiated shared tokens collaborator.
	 *
	 * @var Uncanny_Ceus_Tokens|null
	 */
	private $tokens = null;

	/**
	 * Access the shared tokens collaborator.
	 *
	 * @return Uncanny_Ceus_Tokens
	 */
	public function tokens() {
		if ( null === $this->tokens ) {
			$this->tokens = new Uncanny_Ceus_Tokens( $this );
		}

		return $this->tokens;
	}

	/**
	 * Get the plural credit designation label configured by the host plugin.
	 *
	 * @return string
	 */
	public function get_credit_designation_label_plural() {
		// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
		return get_option( 'credit_designation_label_plural', __( 'CEUs', 'uncanny-ceu' ) );
	}

	/**
	 * Allow CEU-driven WordPress cron jobs to fire Automator actions.
	 *
	 * The host plugin pushes CEU awards through a one-time cron event
	 * (`uo_ceu_scheduled_learndash_course_completed`). When the request that
	 * scheduled the cron has already disabled action execution, this filter
	 * re-enables it so the trigger can complete.
	 *
	 * @param mixed $run_automator_actions Current allow/deny decision.
	 * @param mixed $request               REQUEST payload (unused).
	 *
	 * @return mixed
	 */
	public function maybe_allow_triggers_to_actionify( $run_automator_actions, $request ) {

		if ( false !== $run_automator_actions ) {
			return $run_automator_actions;
		}

		$next_crons_jobs = wp_get_ready_cron_jobs();

		foreach ( $next_crons_jobs as $cron_job ) {
			if ( isset( $cron_job['uo_ceu_scheduled_learndash_course_completed'] ) ) {
				return true;
			}
		}

		return $run_automator_actions;
	}

	/**
	 * Has the host plugin reached the version that exposes the award hook?
	 *
	 * @return bool
	 */
	public function host_plugin_supports_award_hook() {

		if ( ! class_exists( '\\uncanny_ceu\\Utilities' ) ) {
			return false;
		}

		$version = \uncanny_ceu\Utilities::get_version();

		return version_compare( $version, '3.0.6', '>' );
	}
}
