<?php

namespace Uncanny_Automator\Integrations\Userswp;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Userswp_User_Updates_Profile
 *
 * `$user_id` is the logged-in user whose account form was saved. The field
 * tokens are read back through UsersWP after the save, never from the posted
 * form, which can carry a password.
 *
 * @package Uncanny_Automator\Integrations\Userswp
 * @since 8.1.0
 *
 * @property Userswp_Helpers $item_helpers
 */
class Userswp_User_Updates_Profile extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'USERSWP_USER_UPDATES_PROFILE', 'USERSWP' )
			->trigger_meta( 'UWP_ACCOUNT' )
			->hook( 'uwp_after_process_account', 10, 2 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_sentence( esc_html_x( 'A user updates their profile', 'UsersWP', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A user updates their profile', 'UsersWP', 'uncanny-automator' ) );
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
		return array_merge( $tokens, $this->item_helpers->user_token_definitions(), $this->item_helpers->field_token_definitions() );
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$user_id = $hook_args[1] ?? null;

		if ( ! is_numeric( $user_id ) || 0 >= (int) $user_id ) {
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

		$user_id = absint( $hook_args[1] ?? 0 );

		return array_merge( $this->item_helpers->hydrate_user_tokens( $user_id ), $this->item_helpers->field_token_values( $user_id ) );
	}
}
