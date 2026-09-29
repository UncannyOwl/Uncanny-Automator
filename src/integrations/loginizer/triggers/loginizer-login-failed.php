<?php

namespace Uncanny_Automator\Integrations\Loginizer;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Loginizer_Login_Failed
 *
 * Fires after a failed login attempt. Listens on
 * `automator_loginizer_login_failed`, which the integration dispatches from
 * `wp_login_failed` at priority 20 — Loginizer's own handler runs at the default
 * priority 10 and is what writes the attempt/lockout counters, and the trigger
 * engine pins every monitored hook to priority 10, so the trigger could not
 * order itself after Loginizer without the bridge.
 *
 * Anonymous: the attempt failed, so no user is authenticated.
 *
 * @package Uncanny_Automator\Integrations\Loginizer
 *
 * @property Loginizer_Helpers $item_helpers
 */
class Loginizer_Login_Failed extends Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'LOGINIZER_LOGIN_FAILED', 'LOGINIZER' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'LOGINIZER_LOGIN_FAILED_META' )
			->hook( 'automator_loginizer_login_failed', 10, 3 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_is_pro( false );
		$this->set_is_login_required( false );
		$this->set_sentence( esc_html_x( 'A login attempt fails', 'Loginizer', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A login attempt fails', 'Loginizer', 'uncanny-automator' ) );
	}

	/**
	 * No options — the hook exposes no pre-selectable entity.
	 *
	 * @return array
	 */
	public function options() {
		return array();
	}

	/**
	 * Always fires.
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
					'tokenId'   => 'LOGINIZER_FAILED_USERNAME',
					'tokenName' => esc_html_x( 'Username', 'Loginizer', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'LOGINIZER_FAILED_IP',
					'tokenName' => esc_html_x( 'IP address', 'Loginizer', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'LOGINIZER_FAILED_ATTEMPTS',
					'tokenName' => esc_html_x( 'Failed attempt count', 'Loginizer', 'uncanny-automator' ),
					'tokenType' => 'int',
				),
				array(
					'tokenId'   => 'LOGINIZER_FAILED_LOCKOUTS',
					'tokenName' => esc_html_x( 'Lockout count', 'Loginizer', 'uncanny-automator' ),
					'tokenType' => 'int',
				),
				array(
					'tokenId'   => 'LOGINIZER_FAILED_URL',
					'tokenName' => esc_html_x( 'URL of the attempt', 'Loginizer', 'uncanny-automator' ),
					'tokenType' => 'url',
				),
			)
		);
	}

	/**
	 * Hydrate tokens. Returns the full keyset even when Loginizer has no log row
	 * yet, so a recipe never resolves a partial map.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		unset( $trigger );

		$stats = isset( $hook_args[2] ) && is_array( $hook_args[2] ) ? $hook_args[2] : array();

		return array(
			'LOGINIZER_FAILED_USERNAME' => isset( $hook_args[0] ) ? (string) $hook_args[0] : '',
			'LOGINIZER_FAILED_IP'       => isset( $hook_args[1] ) ? (string) $hook_args[1] : '',
			'LOGINIZER_FAILED_ATTEMPTS' => isset( $stats['count'] ) ? (int) $stats['count'] : 0,
			'LOGINIZER_FAILED_LOCKOUTS' => isset( $stats['lockout'] ) ? (int) $stats['lockout'] : 0,
			// Prefer Loginizer's stored URL; fall back to the current request.
			'LOGINIZER_FAILED_URL'      => ! empty( $stats['url'] ) ? (string) $stats['url'] : $this->item_helpers->request_url(),
		);
	}
}
