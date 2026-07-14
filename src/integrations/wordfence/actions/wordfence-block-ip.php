<?php

namespace Uncanny_Automator\Integrations\Wordfence;

use Uncanny_Automator\Recipe\Action;

/**
 * Class Wordfence_Block_Ip
 *
 * Blocks an IP address via wfBlock::createIP( $reason, $ip, $duration ). A
 * duration of 0 blocks forever. Wordfence silently skips whitelisted IPs, so we
 * pre-check and surface that as a logged error.
 *
 * @package Uncanny_Automator\Integrations\Wordfence
 *
 * @property Wordfence_Helpers $item_helpers
 */
class Wordfence_Block_Ip extends Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WORDFENCE' );
		$this->set_requires_user( false );
		$this->set_action_code( 'WORDFENCE_BLOCK_IP' );
		$this->set_action_meta( 'WORDFENCE_IP' );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: IP address.
				esc_html_x( 'Block {{an IP address:%1$s}}', 'Wordfence Security', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Block {{an IP address}}', 'Wordfence Security', 'uncanny-automator' ) );
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
			array(
				'option_code'     => 'WORDFENCE_BLOCK_REASON',
				'label'           => esc_html_x( 'Reason', 'Wordfence Security', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => true,
				'supports_tokens' => true,
			),
			array(
				'option_code'     => 'WORDFENCE_BLOCK_DURATION',
				'label'           => esc_html_x( 'Block duration in seconds', 'Wordfence Security', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => false,
				'default_value'   => '0',
				'supports_tokens' => true,
				'description'     => esc_html_x( 'Enter 0 to block forever.', 'Wordfence Security', 'uncanny-automator' ),
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
			'WORDFENCE_BLOCKED_IP'       => array(
				'name' => esc_html_x( 'Blocked IP address', 'Wordfence Security', 'uncanny-automator' ),
				'type' => 'text',
			),
			'WORDFENCE_BLOCKED_REASON'   => array(
				'name' => esc_html_x( 'Reason', 'Wordfence Security', 'uncanny-automator' ),
				'type' => 'text',
			),
			'WORDFENCE_BLOCKED_DURATION' => array(
				'name' => esc_html_x( 'Duration (seconds)', 'Wordfence Security', 'uncanny-automator' ),
				'type' => 'int',
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

		$ip     = trim( (string) ( $parsed[ $this->get_action_meta() ] ?? '' ) );
		$reason = trim( (string) ( $parsed['WORDFENCE_BLOCK_REASON'] ?? '' ) );

		if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$this->add_log_error( sprintf( 'Invalid IP address: [%s].', $ip ) );
			return false;
		}

		if ( '' === $reason ) {
			$this->add_log_error( 'A block reason is required.' );
			return false;
		}

		if ( \wfBlock::isWhitelisted( $ip ) ) {
			$this->add_log_error( sprintf( 'IP %s is whitelisted and cannot be blocked.', $ip ) );
			return false;
		}

		$duration = absint( $parsed['WORDFENCE_BLOCK_DURATION'] ?? 0 );

		\wfBlock::createIP( $reason, $ip, $duration );

		$this->hydrate_tokens(
			array(
				'WORDFENCE_BLOCKED_IP'       => $ip,
				'WORDFENCE_BLOCKED_REASON'   => $reason,
				'WORDFENCE_BLOCKED_DURATION' => $duration,
			)
		);

		return true;
	}
}
