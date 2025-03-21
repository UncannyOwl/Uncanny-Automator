<?php

namespace Uncanny_Automator\Integrations\SureMembers;

/**
 * Class Remove_User_From_Group
 */
class Remove_User_From_Group extends \Uncanny_Automator\Recipe\Action {


	protected $helpers;
	protected $dependencies;

	/**
	 *
	 */
	protected function setup_action() {

		$this->helpers = array_shift( $this->dependencies );

		// Define the Actions's info
		$this->set_integration( 'SUREMEMBERS' );
		$this->set_action_code( 'REMOVE_USER_TO_GROUP' );
		$this->set_action_meta( 'GROUP' );

		// Define the Action's sentence
		/* translators: SureMembers access group name */
		$this->set_sentence( sprintf( esc_attr__( 'Remove the user from {{an access group:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr__( 'Remove the user from {{an access group}}', 'uncanny-automator' ) );
	}

	/**
	 * Define the Action's options
	 *
	 * @return void
	 */
	public function options() {

		$lists_dropdown = array(
			'input_type'            => 'select',
			'option_code'           => 'GROUP',
			'label'                 => esc_html__( 'Access groups', 'uncanny-automator' ),
			'token_name'            => _x( 'Access group ID', 'SureMembers', 'uncanny-automator' ),
			'required'              => true,
			'options'               => $this->helpers->get_access_groups_options( false ),
			'placeholder'           => esc_html__( 'Please select a group', 'uncanny-automator' ),
			'supports_custom_value' => true,
		);

		return array(
			$lists_dropdown,
		);
	}

	/**
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param $parsed
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$action_meta = $action_data['meta'];

		// Get the field values
		$group_id = absint( Automator()->parse->text( $action_meta['GROUP'], $recipe_id, $user_id, $args ) );

		$this->helpers->revoke_access( $user_id, $group_id );

		return true;
	}
}
