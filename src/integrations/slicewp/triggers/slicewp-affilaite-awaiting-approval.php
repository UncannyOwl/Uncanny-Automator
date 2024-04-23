<?php

namespace Uncanny_Automator\Integrations\SliceWP;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class SLICEWP_AFFILAITE_AWAITING_APPROVAL
 *
 * @pacakge Uncanny_Automator
 */
class  SLICEWP_AFFILAITE_AWAITING_APPROVAL extends Trigger {

	protected $helpers;

	/**
	 * @return mixed|void
	 */
	protected function setup_trigger() {
		$this->helpers = array_shift( $this->dependencies );
		$this->set_integration( 'SLICEWP' );
		$this->set_trigger_code( 'AFFILIATE_AWAITING_APPROVAL' );
		$this->set_trigger_meta( 'SLICEWP_AFFILIATE' );
		$this->set_sentence( esc_attr_x( 'A new affiliate is awaiting approval', 'SliceWP', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_attr_x( 'A new affiliate is awaiting approval', 'SliceWP', 'uncanny-automator' ) );
		$this->add_action( array( 'slicewp_insert_affiliate' ), 10, 2 );
	}

	/**
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		if ( ! isset( $hook_args[0], $hook_args[1] ) ) {
			return false;
		}

		if ( 'pending' !== $hook_args[1]['status'] ) {
			return false;
		}

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
		$booking_tokens = $this->helpers->sliceWP_get_common_tokens();

		return array_merge( $tokens, $booking_tokens );
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

		list( $affiliate_id, $affiliate_data ) = $hook_args;

		return $this->helpers->sliceWP_parse_common_token_values( $affiliate_id, $affiliate_data );
	}

}
