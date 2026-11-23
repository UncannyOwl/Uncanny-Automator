<?php

namespace Uncanny_Automator\Integrations\Userswp;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Userswp_User_Changes_Password
 *
 * The hook carries only the posted form, and UsersWP logs the user out and
 * exits right after it, so the user is the snapshot the integration's
 * priority-1 listener took of the session user while the hook ran. The
 * posted passwords are never tokens.
 *
 * @package Uncanny_Automator\Integrations\Userswp
 * @since 8.1.0
 *
 * @property Userswp_Helpers $item_helpers
 */
class Userswp_User_Changes_Password extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'USERSWP_USER_CHANGES_PASSWORD', 'USERSWP' )
			->trigger_meta( 'UWP_CHANGE_PASSWORD' )
			->hook( 'uwp_after_process_change', 10, 1 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		// The session is gone by the time the queue runs.
		$this->set_is_login_required( false );

		$this->set_sentence( esc_html_x( 'A user changes their password', 'UsersWP', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A user changes their password', 'UsersWP', 'uncanny-automator' ) );
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

		$snapshot = $this->item_helpers->read_latest_snapshot( 'change_password' );
		$user_id  = is_array( $snapshot ) ? absint( $snapshot['UWP_USER_ID'] ?? 0 ) : 0;

		if ( 0 === $user_id ) {
			return false;
		}

		$this->set_user_id( $user_id );

		return true;
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$snapshot = $this->item_helpers->read_latest_snapshot( 'change_password' );

		return is_array( $snapshot ) ? $snapshot : $this->item_helpers->empty_keyset( $this->item_helpers->user_token_definitions() );
	}
}
