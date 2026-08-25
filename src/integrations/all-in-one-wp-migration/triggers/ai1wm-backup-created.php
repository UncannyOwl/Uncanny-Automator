<?php

namespace Uncanny_Automator\Integrations\All_In_One_Wp_Migration;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Ai1wm_Backup_Created
 *
 * Fires after All-in-One WP Migration finalizes an export and renames the
 * archive into the backups directory (`ai1wm_status_export_done`, 1 arg).
 * Anonymous by design: an export can originate from the admin screen, WP-Cron,
 * or a Pro schedule, so there is no inherent recipe user.
 *
 * @package Uncanny_Automator\Integrations\All_In_One_Wp_Migration
 *
 * @property All_In_One_Wp_Migration_Helpers $item_helpers
 */
class Ai1wm_Backup_Created extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'AI1WM_BACKUP_CREATED', 'ALL_IN_ONE_WP_MIGRATION' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'AI1WM_BACKUP' )
			->hook( 'ai1wm_status_export_done', 10, 1 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_is_pro( false );
		$this->set_is_login_required( false );

		$this->set_sentence( esc_html_x( 'A site backup is created', 'All-in-One WP Migration', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A site backup is created', 'All-in-One WP Migration', 'uncanny-automator' ) );
	}

	/**
	 * No options — every completed export fires this. The hook carries no
	 * selectable enum to filter on.
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
		return array_merge( $tokens, $this->item_helpers->backup_token_definitions() );
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$params = isset( $hook_args[0] ) ? $hook_args[0] : null;

		if ( ! is_array( $params ) || empty( $params['archive'] ) ) {
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
		return $this->item_helpers->hydrate_backup_tokens( isset( $hook_args[0] ) ? $hook_args[0] : null );
	}
}
