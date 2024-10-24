<?php

namespace Uncanny_Automator\Integrations\MemberMouse;

/**
 * Class MM_MEMBER_CREATED
 * @package Uncanny_Automator
 */
class MM_MEMBER_CREATED extends \Uncanny_Automator\Recipe\Trigger {

	protected $helpers;

	/**
	 * @return mixed|void
	 */
	protected function setup_trigger() {
		$this->helpers = array_shift( $this->dependencies );
		$this->set_integration( 'MEMBER_MOUSE' );
		$this->set_trigger_code( 'MM_MEMBER_CREATED' );
		$this->set_trigger_meta( 'MM_MEMBER' );
		$this->set_sentence( sprintf( esc_attr_x( 'A new member is created', 'MemberMouse', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'A new member is created', 'MemberMouse', 'uncanny-automator' ) );
		$this->add_action( 'mm_member_add', 10, 1 );
	}

	/**
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		if ( ! isset( $hook_args[0] ) ) {
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
		return array_merge( $this->helpers->get_all_member_tokens(), $tokens );
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

		return $this->helpers->parse_mm_token_values( $member_data );
	}
}
