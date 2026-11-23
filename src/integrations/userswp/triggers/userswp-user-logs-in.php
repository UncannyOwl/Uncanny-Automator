<?php

namespace Uncanny_Automator\Integrations\Userswp;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Userswp_User_Logs_In
 *
 * Fired by Userswp_Login_Dispatcher with the id of the `WP_User` UsersWP's own
 * `wp_signon()` authenticated, never the session or a lookup of the posted
 * username (R1).
 *
 * @package Uncanny_Automator\Integrations\Userswp
 * @since 8.1.0
 *
 * @property Userswp_Helpers $item_helpers
 */
class Userswp_User_Logs_In extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'USERSWP_USER_LOGS_IN', 'USERSWP' )
			->trigger_meta( 'UWP_LOGIN' )
			->hook( 'automator_userswp_user_logged_in', 10, 2 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		// The login request has no session for the user yet.
		$this->set_is_login_required( false );

		$this->set_sentence( esc_html_x( 'A user logs in', 'UsersWP', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A user logs in', 'UsersWP', 'uncanny-automator' ) );
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
		return array_merge(
			$tokens,
			$this->item_helpers->user_token_definitions(),
			array(
				$this->item_helpers->token_def( 'UWP_LOGIN_ENTERED', esc_html_x( 'Username or email entered', 'UsersWP', 'uncanny-automator' ), 'text' ),
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

		$user_id = $hook_args[0] ?? null;

		if ( ! is_int( $user_id ) || 0 >= $user_id ) {
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
		return array_merge(
			$this->item_helpers->hydrate_user_tokens( absint( $hook_args[0] ?? 0 ) ),
			array( 'UWP_LOGIN_ENTERED' => (string) ( $hook_args[1] ?? '' ) )
		);
	}
}
