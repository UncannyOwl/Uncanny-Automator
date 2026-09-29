<?php

namespace Uncanny_Automator\Integrations\Loginizer;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Loginizer_Login_Blocked
 *
 * Fires when Loginizer refuses a login attempt. Loginizer raises
 * `wp_login_blocked` with apply_filters() at four sites in
 * `loginizer_wp_authenticate()` (`init.php:392/408/424/437`), so the integration
 * bridges it to `automator_loginizer_login_blocked` rather than letting the
 * trigger engine's void callback into the filter chain.
 *
 * The hook carries only the username; the block reason is implicit in which call
 * site fired, so it is surfaced as a normalized output token instead of a
 * pre-selectable field. Anonymous: authentication never completed.
 *
 * @package Uncanny_Automator\Integrations\Loginizer
 *
 * @property Loginizer_Helpers $item_helpers
 */
class Loginizer_Login_Blocked extends Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'LOGINIZER_LOGIN_BLOCKED', 'LOGINIZER' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'LOGINIZER_LOGIN_BLOCKED_META' )
			->hook( 'automator_loginizer_login_blocked', 10, 3 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_is_pro( false );
		$this->set_is_login_required( false );
		$this->set_sentence( esc_html_x( 'A login is blocked', 'Loginizer', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A login is blocked', 'Loginizer', 'uncanny-automator' ) );
	}

	/**
	 * No options — the block reason is derived, not selectable.
	 *
	 * @return array
	 */
	public function options() {
		return array();
	}

	/**
	 * Always fires; the bridge only dispatches on a genuine block.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		unset( $trigger, $hook_args );
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
					'tokenId'   => 'LOGINIZER_BLOCKED_USERNAME',
					'tokenName' => esc_html_x( 'Username', 'Loginizer', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'LOGINIZER_BLOCKED_IP',
					'tokenName' => esc_html_x( 'IP address', 'Loginizer', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'LOGINIZER_BLOCK_REASON',
					'tokenName' => esc_html_x( 'Block reason', 'Loginizer', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
			)
		);
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

		unset( $trigger );

		return array(
			'LOGINIZER_BLOCKED_USERNAME' => isset( $hook_args[0] ) ? (string) $hook_args[0] : '',
			'LOGINIZER_BLOCKED_IP'       => isset( $hook_args[1] ) ? (string) $hook_args[1] : '',
			// Raw, non-localized category so text comparisons stay locale-safe:
			// trusted_ip | blacklisted_ip | lockout_exceeded | blacklisted_username.
			'LOGINIZER_BLOCK_REASON'     => isset( $hook_args[2] ) ? (string) $hook_args[2] : '',
		);
	}
}
