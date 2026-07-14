<?php

namespace Uncanny_Automator\Integrations\Wordfence;

/**
 * Class Wordfence_Block_Deleted
 *
 * Fires when an administrator removes a blocking rule, via the dedicated
 * `wordfence_deleted_block` hook ( $type, $reason, $parameters ). The block
 * type is an int wfBlock::TYPE_* constant; for IP blocks $parameters is the IP
 * string, for pattern/country blocks it is an array.
 *
 * @package Uncanny_Automator\Integrations\Wordfence
 *
 * @property Wordfence_Helpers $item_helpers
 */
class Wordfence_Block_Deleted extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 */
	public static function definition() {
		return self::new_definition( 'WORDFENCE_BLOCK_DELETED', 'WORDFENCE' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'WORDFENCE_BLOCK_DELETED_META' )
			->hook( 'wordfence_deleted_block', 10, 3 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_is_login_required( false );
		$this->set_sentence( esc_html_x( 'A blocking rule is deleted', 'Wordfence Security', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A blocking rule is deleted', 'Wordfence Security', 'uncanny-automator' ) );
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
	 * Validate trigger.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		// The hook always fires with a block type; this trigger has no filter
		// options, so any deleted block matches.
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
					'tokenId'   => 'WF_DELETED_BLOCK_TYPE',
					'tokenName' => esc_html_x( 'Block type', 'Wordfence Security', 'uncanny-automator' ),
					'tokenType' => 'int',
				),
				array(
					'tokenId'   => 'WF_DELETED_BLOCK_REASON',
					'tokenName' => esc_html_x( 'Reason', 'Wordfence Security', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'WF_DELETED_BLOCK_TARGET',
					'tokenName' => esc_html_x( 'IP address or parameters', 'Wordfence Security', 'uncanny-automator' ),
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

		$type       = isset( $hook_args[0] ) ? (int) $hook_args[0] : 0;
		$reason     = isset( $hook_args[1] ) ? (string) $hook_args[1] : '';
		$parameters = isset( $hook_args[2] ) ? $hook_args[2] : '';

		return array(
			'WF_DELETED_BLOCK_TYPE'   => $type,
			'WF_DELETED_BLOCK_REASON' => $reason,
			'WF_DELETED_BLOCK_TARGET' => is_scalar( $parameters ) ? (string) $parameters : wp_json_encode( $parameters ),
		);
	}
}
