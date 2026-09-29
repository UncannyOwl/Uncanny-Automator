<?php

namespace Uncanny_Automator\Integrations\Loginizer;

use Uncanny_Automator\Recipe\Trigger;
use WP_User;

/**
 * Class Loginizer_Social_Registration
 *
 * Fires when Loginizer's social-login flow creates a new account —
 * `social-base.php:100` dispatches `do_action( 'register_new_user', $user_id )`
 * after the user is inserted and before the avatar download and auto-login.
 *
 * `register_new_user` is a WP core hook fired by core registration too, so
 * validate() scopes it to Loginizer's social flow via the `lz_social_provider`
 * request parameter. The account exists but is not yet logged in, so the run is
 * bound to the new user id the hook supplies.
 *
 * @package Uncanny_Automator\Integrations\Loginizer
 *
 * @property Loginizer_Helpers $item_helpers
 */
class Loginizer_Social_Registration extends Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'LOGINIZER_SOCIAL_REGISTRATION', 'LOGINIZER' )
			->trigger_meta( 'LOGINIZER_SOCIAL_REGISTRATION_META' )
			->hook( 'register_new_user', 10, 1 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_is_pro( false );
		$this->set_is_login_required( false );
		$this->set_sentence( esc_html_x( 'A user registers via social login', 'Loginizer', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A user registers via social login', 'Loginizer', 'uncanny-automator' ) );
	}

	/**
	 * No options — the provider is read from request state, not selectable.
	 *
	 * @return array
	 */
	public function options() {
		return array();
	}

	/**
	 * Validate — a real user, created inside Loginizer's social flow.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		unset( $trigger );

		$user_id = isset( $hook_args[0] ) && is_numeric( $hook_args[0] ) ? absint( $hook_args[0] ) : 0;

		if ( 0 === $user_id ) {
			return false;
		}

		// Core registration fires this hook too — only Loginizer's social route
		// carries a provider, so this is what scopes the trigger.
		if ( '' === $this->item_helpers->social_provider() ) {
			return false;
		}

		$this->set_user_id( $user_id );

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
		return array_merge(
			$tokens,
			array(
				array(
					'tokenId'   => 'LOGINIZER_SOCIAL_USER_ID',
					'tokenName' => esc_html_x( 'User ID', 'Loginizer', 'uncanny-automator' ),
					'tokenType' => 'int',
				),
				array(
					'tokenId'   => 'LOGINIZER_SOCIAL_USER_LOGIN',
					'tokenName' => esc_html_x( 'Username', 'Loginizer', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'LOGINIZER_SOCIAL_USER_EMAIL',
					'tokenName' => esc_html_x( 'User email', 'Loginizer', 'uncanny-automator' ),
					'tokenType' => 'email',
				),
				array(
					'tokenId'   => 'LOGINIZER_SOCIAL_USER_DISPLAY_NAME',
					'tokenName' => esc_html_x( 'User display name', 'Loginizer', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'LOGINIZER_SOCIAL_PROVIDER',
					'tokenName' => esc_html_x( 'Social provider', 'Loginizer', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'LOGINIZER_SOCIAL_DEFAULT_ROLE',
					'tokenName' => esc_html_x( 'Role assigned', 'Loginizer', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
			)
		);
	}

	/**
	 * Hydrate tokens. Returns the full keyset even for an unresolvable user.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		unset( $trigger );

		$user_id = isset( $hook_args[0] ) ? absint( $hook_args[0] ) : 0;
		$user    = 0 === $user_id ? false : get_user_by( 'ID', $user_id );

		return array(
			'LOGINIZER_SOCIAL_USER_ID'           => $user_id,
			'LOGINIZER_SOCIAL_USER_LOGIN'        => $user instanceof WP_User ? (string) $user->user_login : '',
			'LOGINIZER_SOCIAL_USER_EMAIL'        => $user instanceof WP_User ? (string) $user->user_email : '',
			'LOGINIZER_SOCIAL_USER_DISPLAY_NAME' => $user instanceof WP_User ? (string) $user->display_name : '',
			'LOGINIZER_SOCIAL_PROVIDER'          => $this->item_helpers->social_provider(),
			'LOGINIZER_SOCIAL_DEFAULT_ROLE'      => $this->item_helpers->social_default_role(),
		);
	}
}
