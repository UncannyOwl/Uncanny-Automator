<?php

namespace Uncanny_Automator\Integrations\Wordfence;

/**
 * Class Wordfence_Ip_Locked_Out
 *
 * Wordfence routes most security events through the central hook:
 *   do_action( 'wordfence_security_event', $eventType, $data, $alertCallback )
 * This trigger matches $eventType === 'loginLockout'. The lockout reason is a
 * fully dynamic string (Wordfence interpolates the limit and attempted
 * username), so it is exposed as an output token rather than a filter.
 *
 * @package Uncanny_Automator\Integrations\Wordfence
 *
 * @property Wordfence_Helpers $item_helpers
 */
class Wordfence_Ip_Locked_Out extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 */
	public static function definition() {
		return self::new_definition( 'WORDFENCE_IP_LOCKED_OUT', 'WORDFENCE' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'WORDFENCE_IP_LOCKED_OUT_META' )
			->hook( 'wordfence_security_event', 10, 3 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_is_login_required( false );
		$this->set_sentence( esc_html_x( 'An IP is locked out', 'Wordfence Security', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'An IP is locked out', 'Wordfence Security', 'uncanny-automator' ) );
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
	 * Validate trigger — only the 'loginLockout' security event.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		$event_type = isset( $hook_args[0] ) ? (string) $hook_args[0] : '';
		return 'loginLockout' === $event_type;
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
					'tokenId'   => 'WF_LOCKOUT_IP',
					'tokenName' => esc_html_x( 'IP address', 'Wordfence Security', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'WF_LOCKOUT_REASON',
					'tokenName' => esc_html_x( 'Reason', 'Wordfence Security', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'WF_LOCKOUT_DURATION',
					'tokenName' => esc_html_x( 'Duration (seconds)', 'Wordfence Security', 'uncanny-automator' ),
					'tokenType' => 'int',
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
			'WF_LOCKOUT_IP'       => isset( $data['ip'] ) ? (string) $data['ip'] : '',
			'WF_LOCKOUT_REASON'   => isset( $data['reason'] ) ? (string) $data['reason'] : '',
			'WF_LOCKOUT_DURATION' => isset( $data['duration'] ) ? (int) $data['duration'] : 0,
		);
	}
}
