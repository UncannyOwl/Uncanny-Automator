<?php

namespace Uncanny_Automator\Integrations\Wordfence;

/**
 * Class Wordfence_Breached_Password_Login
 *
 * Matches the central `wordfence_security_event` hook with
 * $eventType === 'breachLogin' — a login blocked because the password appears
 * in a known data breach.
 *
 * @package Uncanny_Automator\Integrations\Wordfence
 *
 * @property Wordfence_Helpers $item_helpers
 */
class Wordfence_Breached_Password_Login extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 */
	public static function definition() {
		return self::new_definition( 'WORDFENCE_BREACHED_PASSWORD_LOGIN', 'WORDFENCE' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'WORDFENCE_BREACHED_PASSWORD_LOGIN_META' )
			->hook( 'wordfence_security_event', 10, 3 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_is_login_required( false );
		$this->set_sentence( esc_html_x( 'A login with a breached password is blocked', 'Wordfence Security', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A login with a breached password is blocked', 'Wordfence Security', 'uncanny-automator' ) );
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
	 * Validate trigger — only the 'breachLogin' security event.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		$event_type = isset( $hook_args[0] ) ? (string) $hook_args[0] : '';
		return 'breachLogin' === $event_type;
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
					'tokenId'   => 'WF_BREACH_USERNAME',
					'tokenName' => esc_html_x( 'Username', 'Wordfence Security', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'WF_BREACH_IP',
					'tokenName' => esc_html_x( 'IP address', 'Wordfence Security', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'WF_BREACH_RESET_URL',
					'tokenName' => esc_html_x( 'Reset password URL', 'Wordfence Security', 'uncanny-automator' ),
					'tokenType' => 'url',
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

		$data = isset( $hook_args[1] ) && is_array( $hook_args[1] ) ? $hook_args[1] : array();

		return array(
			'WF_BREACH_USERNAME'  => isset( $data['username'] ) ? (string) $data['username'] : '',
			'WF_BREACH_IP'        => isset( $data['ip'] ) ? (string) $data['ip'] : '',
			'WF_BREACH_RESET_URL' => isset( $data['resetPasswordURL'] ) ? (string) $data['resetPasswordURL'] : '',
		);
	}
}
