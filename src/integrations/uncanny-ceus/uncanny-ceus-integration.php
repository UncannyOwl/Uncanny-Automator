<?php

namespace Uncanny_Automator\Integrations\Uncanny_Ceus;

use Uncanny_Automator\Integration;

/**
 * Class Uncanny_Ceus_Integration
 *
 * @package Uncanny_Automator\Integrations\Uncanny_Ceus
 */
class Uncanny_Ceus_Integration extends Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Uncanny_Ceus_Helpers();

		$this->set_integration( 'UNCANNYCEUS' );
		$this->set_name( 'Uncanny CEUs' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/uncanny-owl-icon.svg' );
		$this->set_plugin_file_path( 'uncanny-continuing-education-credits/uncanny-continuing-education-credits.php' );
	}

	/**
	 * Load triggers and actions.
	 *
	 * @return void
	 */
	public function load() {

		// Wire run-time hooks for full-load mode too (editor/REST) — the parent
		// only calls load_shared_hooks() in targeted mode, never alongside load().
		$this->load_shared_hooks();

		// Triggers.
		new UNCANNYCEUS_EARNSCEUS( $this->helpers );
		new UNCANNYCEUS_EARNS_NUMBER_CEUS( $this->helpers );
		new UNCANNYCEUS_EARNS_NUMBERS_MORE_THAN( $this->helpers );

		// Actions.
		new UNCANNYCEUS_AWARDCEUS( $this->helpers );
	}

	/**
	 * Register run-time hooks shared across load modes.
	 *
	 * The host plugin pushes CEU awards through a one-shot cron event
	 * (`uo_ceu_scheduled_learndash_course_completed`). The integration loads
	 * in targeted mode during that tick — which runs load_shared_hooks() but
	 * NOT load() — so this filter must live here, not in load(), or actions
	 * would be skipped exactly when the award fires.
	 *
	 * @return void
	 */
	protected function load_shared_hooks() {
		// Allow Automator actions to fire during the CEU cron tick even when
		// the request would otherwise skip them.
		add_filter( 'uap_run_automator_actions', array( $this->helpers, 'maybe_allow_triggers_to_actionify' ), 10, 2 );
	}

	/**
	 * Check if Uncanny CEUs plugin is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'CEU_PLUGIN_NAME' );
	}
}
