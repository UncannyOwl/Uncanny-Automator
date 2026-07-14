<?php

namespace Uncanny_Automator\Integrations\Wordfence;

use Uncanny_Automator\Recipe\Action;

/**
 * Class Wordfence_Unblock_Ip
 *
 * Removes all blocks for an IP via wfBlock::unblockIP( $ip ). No-op if the IP
 * is not blocked.
 *
 * @package Uncanny_Automator\Integrations\Wordfence
 *
 * @property Wordfence_Helpers $item_helpers
 */
class Wordfence_Unblock_Ip extends Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WORDFENCE' );
		$this->set_requires_user( false );
		$this->set_action_code( 'WORDFENCE_UNBLOCK_IP' );
		$this->set_action_meta( 'WORDFENCE_IP' );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: IP address.
				esc_html_x( 'Remove {{an IP block:%1$s}}', 'Wordfence Security', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Remove {{an IP block}}', 'Wordfence Security', 'uncanny-automator' ) );
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
			'WORDFENCE_UNBLOCKED_IP' => array(
				'name' => esc_html_x( 'Unblocked IP address', 'Wordfence Security', 'uncanny-automator' ),
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

		\wfBlock::unblockIP( $ip );

		$this->hydrate_tokens(
			array(
				'WORDFENCE_UNBLOCKED_IP' => $ip,
			)
		);

		return true;
	}
}
