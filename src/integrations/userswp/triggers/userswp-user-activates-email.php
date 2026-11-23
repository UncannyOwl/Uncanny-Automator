<?php

namespace Uncanny_Automator\Integrations\Userswp;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Userswp_User_Activates_Email
 *
 * Fires from the activation link once its key is verified, and from an admin's
 * Activate action in wp-admin; either way `$user_id` is the activated account,
 * never the admin.
 *
 * @package Uncanny_Automator\Integrations\Userswp
 * @since 8.1.0
 *
 * @property Userswp_Helpers $item_helpers
 */
class Userswp_User_Activates_Email extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'USERSWP_USER_ACTIVATES_EMAIL', 'USERSWP' )
			->trigger_meta( 'UWP_ACTIVATION' )
			->hook( 'uwp_email_activation_success', 10, 1 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		// The activation link is opened logged out, or by an admin.
		$this->set_is_login_required( false );

		$this->set_sentence( esc_html_x( 'A user activates their email', 'UsersWP', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A user activates their email', 'UsersWP', 'uncanny-automator' ) );
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
		return $this->item_helpers->hydrate_user_tokens( absint( $hook_args[0] ?? 0 ) );
	}
}
