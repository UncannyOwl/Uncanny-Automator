<?php

namespace Uncanny_Automator\Integrations\MemberMouse;

/**
 * Class MM_RENEWAL_RECEIVED
 * @package Uncanny_Automator
 */
class MM_RENEWAL_RECEIVED extends \Uncanny_Automator\Recipe\Trigger {

	protected $helpers;

	/**
	 * @return mixed|void
	 */
	protected function setup_trigger() {
		$this->helpers = array_shift( $this->dependencies );
		$this->set_integration( 'MEMBER_MOUSE' );
		$this->set_trigger_code( 'MM_ORDER_RENEWAL_RECEIVED' );
		$this->set_trigger_meta( 'MM_RENEWAL' );
		$this->set_sentence( sprintf( esc_attr_x( 'A renewal payment is received', 'MemberMouse', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'A renewal payment is received', 'MemberMouse', 'uncanny-automator' ) );
		$this->add_action( 'mm_payment_rebill', 10, 1 );
	}

	/**
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		if ( ! isset( $hook_args[0] ) ) {
			return false;
		}

		$this->set_user_id( $hook_args[0]['member_id'] );

		return true;
	}

	/**
	 * define_tokens
	 *
	 * @param mixed $tokens
	 * @param mixed $trigger - options selected in the current recipe/trigger
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge( $this->helpers->get_all_member_tokens(), $this->helpers->get_all_order_tokens(), $tokens );
	}

	/**
	 * hydrate_tokens
	 *
	 * @param $trigger
	 * @param $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		$member_data = $hook_args[0];

		$mm_tokens       = $this->helpers->parse_mm_token_values( $member_data );
		$mm_order_tokens = $this->helpers->parse_mm_order_token_values( $member_data );

		return array_merge( $mm_tokens, $mm_order_tokens );
	}

}
