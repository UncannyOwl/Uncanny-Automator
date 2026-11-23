<?php

namespace Uncanny_Automator\Integrations\Asgaros_Forum;

use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;
use Uncanny_Automator\Recipe\Action;

/**
 * Class Asgaros_Forum_Add_User_To_Usergroup
 *
 * Action: Add the user to {{a usergroup}}.
 *
 * Writes through `AsgarosForumUserGroups::insertUserGroupsOfUsers()`, so
 * Asgaros fires `asgarosforum_usergroup_{id}_add_user` and "A user is added
 * to {{a usergroup}}" runs. A member already in the group needs no write.
 *
 * @property Asgaros_Forum_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 * @since 8.1.0
 */
class Asgaros_Forum_Add_User_To_Usergroup extends Action {

	/**
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'ASGAROS_FORUM' );
		$this->set_action_code( 'ASGAROS_FORUM_ADD_USER_TO_USERGROUP' );
		$this->set_action_meta( 'ASGAROS_FORUM_USERGROUP' );
		$this->set_is_pro( false );
		$this->set_requires_user( true );

		$this->set_sentence(
			sprintf(
				/* translators: 1: Usergroup */
				esc_html_x( 'Add the user to {{a usergroup:%1$s}}', 'Asgaros Forum', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Add the user to {{a usergroup}}', 'Asgaros Forum', 'uncanny-automator' ) );
	}

	/**
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_html_x( 'Usergroup', 'Asgaros Forum', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => array(),
				'relevant_tokens'       => array(),
				'supports_custom_value' => true,
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'usergroups_strict' ),
			),
		);
	}

	/**
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'USERGROUP_ID'   => array(
				'name' => esc_html_x( 'Usergroup ID', 'Asgaros Forum', 'uncanny-automator' ),
				'type' => 'int',
			),
			'USERGROUP_NAME' => array(
				'name' => esc_html_x( 'Usergroup name', 'Asgaros Forum', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * @param int   $user_id     The recipe user's ID.
	 * @param array $action_data The action configuration.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional arguments.
	 * @param array $parsed      Parsed field values.
	 *
	 * @return bool
	 *
	 * @throws Action_Error_Exception When the action cannot complete.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		if ( null === $this->item_helpers->get_forum() ) {
			throw Action_Error_Exception::execution_failed( esc_html_x( 'Asgaros Forum is not available.', 'Asgaros Forum', 'uncanny-automator' ) );
		}

		$user_id         = absint( $user_id );
		$usergroup_value = (string) ( $parsed[ $this->get_action_meta() ] ?? '' );
		$usergroup_id    = $this->item_helpers->parse_id( $usergroup_value );

		if ( 0 === $user_id ) {
			throw Action_Error_Exception::validation_failed( esc_html_x( 'The recipe has no user to add to the usergroup.', 'Asgaros Forum', 'uncanny-automator' ) );
		}

		$usergroup = $this->item_helpers->get_usergroup( $usergroup_id );

		if ( '' === trim( $usergroup_value ) ) {
			throw Action_Error_Exception::validation_failed( esc_html_x( 'The Usergroup field is empty.', 'Asgaros Forum', 'uncanny-automator' ) );
		}

		if ( null === $usergroup ) {
			throw Action_Error_Exception::not_found(
				sprintf(
					/* translators: %s: The Usergroup field's value */
					esc_html_x( '%s is not an Asgaros Forum usergroup.', 'Asgaros Forum', 'uncanny-automator' ),
					esc_html( sanitize_text_field( $usergroup_value ) )
				)
			);
		}

		$this->item_helpers->set_user_usergroup( $user_id, $usergroup_id, true );

		if ( ! $this->item_helpers->is_user_in_usergroup( $user_id, $usergroup_id ) ) {
			throw Action_Error_Exception::execution_failed(
				sprintf(
					/* translators: 1: User ID, 2: Usergroup name */
					esc_html_x( 'User %1$d could not be added to the usergroup %2$s.', 'Asgaros Forum', 'uncanny-automator' ),
					absint( $user_id ),
					esc_html( $usergroup->name )
				)
			);
		}

		$this->hydrate_tokens(
			array(
				'USERGROUP_ID'   => $usergroup_id,
				'USERGROUP_NAME' => $usergroup->name,
			)
		);

		return true;
	}
}
