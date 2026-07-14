<?php

namespace Uncanny_Automator\Integrations\Wordfence;

/**
 * Class Wordfence_Ip_Blocked_Throttled
 *
 * Matches the central `wordfence_security_event` hook with $eventType of
 * 'block' or 'throttle' (rate limiter). The slash-selector lets the recipe
 * match either action or any. The reason is a dynamic free-text string, exposed
 * as an output token.
 *
 * @package Uncanny_Automator\Integrations\Wordfence
 *
 * @property Wordfence_Helpers $item_helpers
 */
class Wordfence_Ip_Blocked_Throttled extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 */
	public static function definition() {
		return self::new_definition( 'WORDFENCE_IP_BLOCKED_THROTTLED', 'WORDFENCE' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'WORDFENCE_IP_ACTION' )
			->hook( 'wordfence_security_event', 10, 3 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_is_login_required( false );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Action taken (blocked or throttled).
				esc_html_x( 'An IP is {{blocked or throttled:%1$s}}', 'Wordfence Security', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'An IP is {{blocked or throttled}}', 'Wordfence Security', 'uncanny-automator' ) );
	}

	/**
	 * Trigger options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Action taken', 'Wordfence Security', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'relevant_tokens' => array(),
				'options'         => array(
					array(
						'value' => Wordfence_Helpers::ANY,
						'text'  => esc_html_x( 'any', 'Wordfence Security', 'uncanny-automator' ),
					),
					array(
						'value' => 'block',
						'text'  => esc_html_x( 'blocked', 'Wordfence Security', 'uncanny-automator' ),
					),
					array(
						'value' => 'throttle',
						'text'  => esc_html_x( 'throttled', 'Wordfence Security', 'uncanny-automator' ),
					),
				),
			),
		);
	}

	/**
	 * Validate trigger — a 'block' or 'throttle' event matching the selection.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$event_type = isset( $hook_args[0] ) ? (string) $hook_args[0] : '';

		if ( 'block' !== $event_type && 'throttle' !== $event_type ) {
			return false;
		}

		$selected = (string) ( $trigger['meta'][ $this->get_trigger_meta() ] ?? Wordfence_Helpers::ANY );

		return Wordfence_Helpers::ANY === $selected || $selected === $event_type;
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
					'tokenId'   => 'WF_IP',
					'tokenName' => esc_html_x( 'IP address', 'Wordfence Security', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'WF_ACTION',
					'tokenName' => esc_html_x( 'Action taken', 'Wordfence Security', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'WF_REASON',
					'tokenName' => esc_html_x( 'Reason', 'Wordfence Security', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'WF_DURATION',
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

		$event_type = isset( $hook_args[0] ) ? (string) $hook_args[0] : '';
		$data       = isset( $hook_args[1] ) && is_array( $hook_args[1] ) ? $hook_args[1] : array();

		return array(
			'WF_IP'       => isset( $data['ip'] ) ? (string) $data['ip'] : '',
			'WF_ACTION'   => $event_type,
			'WF_REASON'   => isset( $data['reason'] ) ? (string) $data['reason'] : '',
			'WF_DURATION' => isset( $data['duration'] ) ? (int) $data['duration'] : 0,
		);
	}
}
