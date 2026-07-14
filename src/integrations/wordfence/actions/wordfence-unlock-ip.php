<?php

namespace Uncanny_Automator\Integrations\Wordfence;

use Uncanny_Automator\Recipe\Action;

/**
 * Class Wordfence_Unlock_Ip
 *
 * Removes login lockouts (TYPE_LOCKOUT) for an IP via wfBlock::unlockOutIP(
 * $ip ). Unlike Unblock, this only clears lockouts, not manual blocks or rate
 * limits. No-op if the IP has no lockouts.
 *
 * @package Uncanny_Automator\Integrations\Wordfence
 *
 * @property Wordfence_Helpers $item_helpers
 */
class Wordfence_Unlock_Ip extends Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WORDFENCE' );
		$this->set_requires_user( false );
		$this->set_action_code( 'WORDFENCE_UNLOCK_IP' );
		$this->set_action_meta( 'WORDFENCE_IP' );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: IP address.
				esc_html_x( 'Remove {{an IP lockout:%1$s}}', 'Wordfence Security', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Remove {{an IP lockout}}', 'Wordfence Security', 'uncanny-automator' ) );
	}

	/**
	 * Options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_action_meta(),
				'label'           => esc_html_x( 'IP address', 'Wordfence Security', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => true,
				'supports_tokens' => true,
			),
		);
	}

	/**
	 * Define output tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'WORDFENCE_UNLOCKED_IP' => array(
				'name' => esc_html_x( 'Unlocked IP address', 'Wordfence Security', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Process action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        The args.
	 * @param array $parsed      The parsed options.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		if ( ! class_exists( '\wfBlock' ) ) {
			$this->add_log_error( 'Wordfence is not active.' );
			return false;
		}

		$ip = trim( (string) ( $parsed[ $this->get_action_meta() ] ?? '' ) );

		if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$this->add_log_error( sprintf( 'Invalid IP address: [%s].', $ip ) );
			return false;
		}

		\wfBlock::unlockOutIP( $ip );

		$this->hydrate_tokens(
			array(
				'WORDFENCE_UNLOCKED_IP' => $ip,
			)
		);

		return true;
	}
}
