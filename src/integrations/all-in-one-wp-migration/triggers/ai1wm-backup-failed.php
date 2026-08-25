<?php

namespace Uncanny_Automator\Integrations\All_In_One_Wp_Migration;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Ai1wm_Backup_Failed
 *
 * Fires when an export step throws — All-in-One WP Migration dispatches
 * `ai1wm_status_export_error` from both the database-exception and the general
 * exception path in `Ai1wm_Export_Controller::export()` (2 args: `$params`,
 * `$exception`). Anonymous: a failing export may be running under cron with no
 * inherent recipe user.
 *
 * @package Uncanny_Automator\Integrations\All_In_One_Wp_Migration
 *
 * @property All_In_One_Wp_Migration_Helpers $item_helpers
 */
class Ai1wm_Backup_Failed extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'AI1WM_BACKUP_FAILED', 'ALL_IN_ONE_WP_MIGRATION' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'AI1WM_BACKUP_FAILURE' )
			->hook( 'ai1wm_status_export_error', 10, 2 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_is_pro( false );
		$this->set_is_login_required( false );

		$this->set_sentence( esc_html_x( 'A site backup fails', 'All-in-One WP Migration', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A site backup fails', 'All-in-One WP Migration', 'uncanny-automator' ) );
	}

	/**
	 * No options — the failure reason is a free-form exception string with no
	 * finite enum to filter on, so it is surfaced as output tokens instead.
	 *
	 * @return array
	 */
	public function options() {
		return array();
	}

	/**
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge( $tokens, $this->item_helpers->backup_failure_token_definitions() );
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		// The exception is the payload that matters; $params may legitimately be
		// partial at the point the export blew up, so it is not required here.
		if ( ! isset( $hook_args[1] ) || ! $hook_args[1] instanceof \Throwable ) {
			return false;
		}

		// Anonymous system event — no actor in the payload, so the trigger runs
		// unattributed. Intentionally no set_user_id() / current-user gate.
		return true;
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		return $this->item_helpers->hydrate_backup_failure_tokens(
			isset( $hook_args[0] ) ? $hook_args[0] : null,
			isset( $hook_args[1] ) ? $hook_args[1] : null
		);
	}
}
