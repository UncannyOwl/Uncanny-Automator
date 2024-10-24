<?php

namespace Uncanny_Automator\Integrations\MemberMouse;

/**
 * Class MM_BUNDLE_ADDED_TO_MEMBERS_ACCOUNT
 * @package Uncanny_Automator
 */
class MM_BUNDLE_ADDED_TO_MEMBERS_ACCOUNT extends \Uncanny_Automator\Recipe\Trigger {

	protected $helpers;

	/**
	 * @return mixed|void
	 */
	protected function setup_trigger() {
		$this->helpers = array_shift( $this->dependencies );
		$this->set_integration( 'MEMBER_MOUSE' );
		$this->set_trigger_code( 'MM_ADD_BUNDLE_TO_ACCOUNT' );
		$this->set_trigger_meta( 'MM_BUNDLE' );
		$this->set_sentence( sprintf( esc_attr_x( "{{A bundle:%1\$s}} is added to a member's account", 'MemberMouse', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_attr_x( "{{A bundle}} is added to a member's account", 'MemberMouse', 'uncanny-automator' ) );
		$this->add_action( 'mm_bundles_add', 10, 1 );
	}

	/**
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'input_type'  => 'select',
				'option_code' => $this->get_trigger_meta(),
				'label'       => _x( 'Bundle', 'MemberMouse', 'uncanny-automator' ),
				'required'    => true,
				'token_name'  => _x( 'Selected bundle', 'MemberMouse', 'uncanny-automator' ),
				'options'     => $this->helpers->get_all_available_bundles( true ),
			),
		);
	}

	/**
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		if ( ! isset( $hook_args[0], $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return false;
		}

		$selected_bundle_id = $trigger['meta'][ $this->get_trigger_meta() ];

		return ( intval( '-1' ) === intval( $selected_bundle_id ) || absint( $selected_bundle_id ) === absint( $hook_args[0]['bundle_id'] ) );
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
		return array_merge( $this->helpers->get_all_member_tokens(), $this->helpers->get_all_bundle_tokens(), $tokens );
	}

	/**
	 * hydrate_tokens
	 *
	 * @param $completed_trigger
	 * @param $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $completed_trigger, $hook_args ) {
		$member_data = $hook_args[0];

		$specific_tokens = array(
			$this->get_trigger_meta() => $completed_trigger['meta'][ $this->get_trigger_meta() . '_readable' ],
		);
		$member_tokens   = $this->helpers->parse_mm_token_values( $member_data );
		$bundle_tokens   = $this->helpers->parse_mm_bundle_token_values( $member_data );

		return array_merge( $member_tokens, $bundle_tokens, $specific_tokens );
	}

}
