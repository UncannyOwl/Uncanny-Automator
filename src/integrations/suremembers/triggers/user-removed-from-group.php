<?php

namespace Uncanny_Automator\Integrations\SureMembers;

/**
 * Class User_Removed_From_Group
 */
class User_Removed_From_Group extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * helpers
	 *
	 * @var mixed
	 */
	protected $helpers;

	/**
	 * This is a logged-in trigger example that requires a user and allows counting/limiting how many times a user can
	 * trigger the recipe. Logged-in recipes also allow using multiple triggers in a single recipe.
	 *
	 */
	protected function setup_trigger() {

		// Store a dependency (optional)
		$this->helpers = array_shift( $this->dependencies );

		// Define the Trigger's info
		$this->set_integration( 'SUREMEMBERS' );
		$this->set_trigger_code( 'USER_REMOVED_FROM_GROUP' );
		$this->set_trigger_meta( 'GROUP' );
		$this->set_is_login_required( false );

		// Trigger sentence
		/* translators: SureMembers access group name */
		$this->set_sentence( sprintf( esc_attr__( 'A user is removed from {{an access group:%1$s}}', 'uncanny-automator' ), 'GROUP' ) );
		$this->set_readable_sentence( esc_attr__( 'A user is removed from {{an access group}}', 'uncanny-automator' ) );

		// Trigger wp hook
		$this->add_action( 'automator_suremembers_after_access_revoke', 10, 2 );
	}

	/**
	 * options
	 *
	 * @return array
	 */
	public function options() {

		$groups_dropdown = array(
			'input_type'  => 'select',
			'option_code' => 'GROUP',
			'label'       => _x( 'Access group', 'SureMembers', 'uncanny-automator' ),
			'token_name'  => _x( 'Access group ID', 'SureMembers', 'uncanny-automator' ),
			'required'    => true,
			'options'     => $this->helpers->get_access_groups_options(),
			'placeholder' => esc_html__( 'Please select a group', 'uncanny-automator' ),
		);

		return array(
			$groups_dropdown,
		);
	}

	/**
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		list( $user_id, $group_id ) = $hook_args;

		$this->set_user_id( $user_id );

		// Make sure the trigger has some value selected in the options
		if ( ! isset( $trigger['meta']['GROUP'] ) ) {
			//Something is wrong, the trigger doesn't have the required option value.
			return false;
		}

		// Get the dropdown value
		$selected_group = $trigger['meta']['GROUP'];

		// Any group was selected
		if ( intval( '-1' ) === intval( $selected_group ) ) {
			return true;
		}

		if ( absint( $selected_group ) === absint( $group_id ) ) {
			return true;
		}

		return false;
	}

	/**
	 * define_tokens
	 *
	 * Alter this method if you want to add some additional tokens.
	 *
	 * @param  mixed $tokens
	 * @param  mixed $trigger - options selected in the current recipe/trigger
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {

		$tokens[] = array(
			'tokenId'   => 'GROUP_NAME',
			'tokenName' => _x( 'Access group title', 'SureMembers', 'uncanny-automator' ),
			'tokenType' => 'text',
		);

		return $tokens;
	}

	/**
	 * hydrate_tokens
	 *
	 * Here you need to pass the values for the trigger tokens.
	 * Note that each token field also has a token that has to be populated in this method.
	 *
	 * @param  mixed $trigger
	 * @param  mixed $hook_args
	 * @return void
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $user_id, $group_id ) = $hook_args;

		$token_values = array(
			'GROUP'      => $group_id,
			'GROUP_NAME' => $this->helpers->get_group_name_by_id( $group_id ),
		);

		return $token_values;
	}
}
