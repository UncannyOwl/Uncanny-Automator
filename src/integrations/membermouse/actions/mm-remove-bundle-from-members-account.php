<?php

namespace Uncanny_Automator\Integrations\MemberMouse;

/**
 * Class MM_REMOVE_BUNDLE_FROM_MEMBERS_ACCOUNT
 * @package Uncanny_Automator
 */
class MM_REMOVE_BUNDLE_FROM_MEMBERS_ACCOUNT extends \Uncanny_Automator\Recipe\Action {

	protected $helpers;

	/**
	 * @return mixed|void
	 */
	protected function setup_action() {
		$this->helpers = array_shift( $this->dependencies );
		$this->set_integration( 'MEMBER_MOUSE' );
		$this->set_action_code( 'MM_REMOVE_BUNDLE' );
		$this->set_action_meta( 'MM_BUNDLE' );
		$this->set_requires_user( true );
		$this->set_sentence( sprintf( esc_attr_x( "Remove {{a bundle:%1\$s}} from the member's account", 'MemberMouse', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr_x( "Remove {{a bundle}} from the member's account", 'MemberMouse', 'uncanny-automator' ) );
	}

	/**
	 * Define the Action's options
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'input_type'            => 'select',
				'option_code'           => $this->get_action_meta(),
				'label'                 => _x( 'Bundle', 'MemberMouse', 'uncanny-automator' ),
				'required'              => true,
				'supports_custom_value' => false,
				'relevant_tokens'       => array(),
				'options'               => $this->helpers->get_all_available_bundles(),
			),
		);
	}

	/**
	 * @return array
	 */
	public function define_tokens() {

		return array(
			'MM_BUNDLE_NAME' => array(
				'name' => _x( 'Bundle', 'MemberMouse', 'uncanny-automator' ),
				'type' => 'text',
			),
		);

	}

	/**
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param       $parsed
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$bundle_id = isset( $parsed[ $this->get_action_meta() ] ) ? absint( sanitize_text_field( $parsed[ $this->get_action_meta() ] ) ) : '';

		if ( empty( $bundle_id ) ) {
			$this->add_log_error( 'Please select bundle.' );

			return false;
		}

		$member_id     = get_current_user_id();
		$remove_bundle = \MM_AppliedBundle::removeBundleFromUser( $member_id, $bundle_id );
		$this->hydrate_tokens(
			array(
				'MM_BUNDLE_NAME' => $parsed['MM_BUNDLE_readable'],
			)
		);

		if ( $remove_bundle->type === 'error' ) {
			$this->add_log_error( $remove_bundle->message );

			return false;
		}

		return true;
	}

}
