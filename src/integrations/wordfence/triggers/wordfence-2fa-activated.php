<?php

namespace Uncanny_Automator\Integrations\Wordfence;

/**
 * Class Wordfence_2fa_Activated
 *
 * Fires when a user enables 2FA (TOTP), via the dedicated
 * `wordfence_ls_2fa_activated` hook ( WP_User $user ). The affected user is
 * resolved from the hook arg and bound to the run.
 *
 * @package Uncanny_Automator\Integrations\Wordfence
 *
 * @property Wordfence_Helpers $item_helpers
 */
class Wordfence_2fa_Activated extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 */
	public static function definition() {
		return self::new_definition( 'WORDFENCE_2FA_ACTIVATED', 'WORDFENCE' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'WORDFENCE_2FA_ACTIVATED_META' )
			->hook( 'wordfence_ls_2fa_activated', 10, 1 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_is_login_required( false );
		$this->set_sentence( esc_html_x( 'A user activates two-factor authentication', 'Wordfence Security', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A user activates two-factor authentication', 'Wordfence Security', 'uncanny-automator' ) );
	}

	/**
	 * Trigger options.
	 *
	 * @return array
	 */
	public function options() {
		return array();
	}

	/**
	 * Validate trigger and bind the affected user.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$user = isset( $hook_args[0] ) ? $hook_args[0] : null;

		if ( ! $user instanceof \WP_User || 0 === (int) $user->ID ) {
			return false;
		}

		$this->set_user_id( $user->ID );

		return true;
	}

	/**
	 * Define tokens.
	 *
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge( $tokens, $this->item_helpers->get_user_token_definitions() );
	}

	/**
	 * Hydrate tokens.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		$user = isset( $hook_args[0] ) ? $hook_args[0] : null;
		return $this->item_helpers->hydrate_user_tokens( $user );
	}
}
