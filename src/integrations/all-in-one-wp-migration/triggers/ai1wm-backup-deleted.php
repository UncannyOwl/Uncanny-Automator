<?php

namespace Uncanny_Automator\Integrations\All_In_One_Wp_Migration;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Ai1wm_Backup_Deleted
 *
 * Fires after a `.wpress` backup is successfully unlinked —
 * `Ai1wm_Backups::delete_file()` dispatches `ai1wm_status_backup_deleted` only
 * when the unlink returns truthy (1 arg: the filename). Anonymous: deletions
 * come from the plugin's Backups screen or from cleanup code, with no actor in
 * the payload.
 *
 * @package Uncanny_Automator\Integrations\All_In_One_Wp_Migration
 *
 * @property All_In_One_Wp_Migration_Helpers $item_helpers
 */
class Ai1wm_Backup_Deleted extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'AI1WM_BACKUP_DELETED', 'ALL_IN_ONE_WP_MIGRATION' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'AI1WM_DELETED_BACKUP' )
			->hook( 'ai1wm_status_backup_deleted', 10, 1 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_is_pro( false );
		$this->set_is_login_required( false );

		$this->set_sentence( esc_html_x( 'A site backup is deleted', 'All-in-One WP Migration', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A site backup is deleted', 'All-in-One WP Migration', 'uncanny-automator' ) );
	}

	/**
	 * No options — the filename is free-form, not a fixed enum, so it is
	 * surfaced as an output token rather than a selector.
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
		return array_merge(
			$tokens,
			array(
				array(
					'tokenId'   => 'AI1WM_DELETED_FILENAME',
					'tokenName' => esc_html_x( 'Deleted backup filename', 'All-in-One WP Migration', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
			)
		);
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		// Don't fire from this integration's own delete action — that action
		// calls Ai1wm_Backups::delete_file(), which dispatches this very hook,
		// so an unguarded trigger would re-fire on its own recipe's output.
		if ( All_In_One_Wp_Migration_Helpers::$is_deleting_via_action ) {
			return false;
		}

		$file = isset( $hook_args[0] ) ? $hook_args[0] : '';

		if ( ! is_string( $file ) || '' === $file ) {
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

		$file = isset( $hook_args[0] ) ? $hook_args[0] : '';

		return array(
			'AI1WM_DELETED_FILENAME' => is_string( $file ) ? $file : '',
		);
	}
}
