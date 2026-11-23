<?php

namespace Uncanny_Automator\Integrations\Userswp;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Userswp_User_Deletes_Account
 *
 * As WordPress's "A user is deleted": a user trigger bound to the deleted id,
 * with tokens from the snapshot the integration took on
 * `uwp_before_delete_account`, while the user still existed. The hook also
 * fires when the deletion failed, so only a truthy `$deleted` counts.
 *
 * @package Uncanny_Automator\Integrations\Userswp
 * @since 8.1.0
 *
 * @property Userswp_Helpers $item_helpers
 */
class Userswp_User_Deletes_Account extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'USERSWP_USER_DELETES_ACCOUNT', 'USERSWP' )
			->trigger_meta( 'UWP_DELETE_ACCOUNT' )
			->hook( 'uwp_after_delete_account', 10, 2 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		// The account, and with it the session, is gone.
		$this->set_is_login_required( false );

		$this->set_sentence( esc_html_x( 'A user deletes their account', 'UsersWP', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A user deletes their account', 'UsersWP', 'uncanny-automator' ) );
	}

	/**
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
		return array_merge( $tokens, $this->item_helpers->user_token_definitions() );
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$user_id = $hook_args[0] ?? null;

		if ( empty( $hook_args[1] ) || ! is_numeric( $user_id ) || 0 >= (int) $user_id ) {
			return false;
		}

		$this->set_user_id( (int) $user_id );

		return true;
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$user_id  = absint( $hook_args[0] ?? 0 );
		$snapshot = $this->item_helpers->read_snapshot( 'delete_account', $user_id );

		if ( is_array( $snapshot ) ) {
			return $snapshot;
		}

		return array_merge(
			$this->item_helpers->empty_keyset( $this->item_helpers->user_token_definitions() ),
			array( 'UWP_USER_ID' => $user_id )
		);
	}
}
