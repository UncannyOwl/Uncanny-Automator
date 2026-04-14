<?php

namespace Uncanny_Automator\Integrations\Microsoft_Teams;

/**
 * Class TEAMS_ADD_MEMBER
 *
 * @package Uncanny_Automator
 *
 * @property Microsoft_Teams_App_Helpers $helpers
 * @property Microsoft_Teams_Api_Caller $api
 */
class TEAMS_ADD_MEMBER extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'MICROSOFT_TEAMS' );
		$this->set_action_code( 'TEAMS_ADD_MEMBER_CODE' );
		$this->set_action_meta( 'TEAMS_ADD_MEMBER_TEAM' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/microsoft-teams/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Add {{a user}} to {{a team}} as {{a role}}', 'Microsoft Teams', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: 1: User, 2: Team, 3: Role
				esc_attr_x( 'Add {{a user:%1$s}} to {{a team:%2$s}} as {{a role:%3$s}}', 'Microsoft Teams', 'uncanny-automator' ),
				'TEAMS_ADD_MEMBER_USER:' . $this->get_action_meta(),
				$this->get_action_meta(),
				'TEAMS_ADD_MEMBER_ROLE:' . $this->get_action_meta()
			)
		);

		$this->set_action_tokens(
			array(
				'TEAMS_MEMBERSHIP_ID' => array(
					'name' => esc_html_x( 'Membership ID', 'Microsoft Teams', 'uncanny-automator' ),
					'type' => 'text',
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Define the options for the action.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_user_option_config( 'TEAMS_ADD_MEMBER_USER' ),
			$this->helpers->get_team_select_option_config( $this->get_action_meta() ),
			$this->get_role_option_config(),
			$this->helpers->get_invite_guest_option_config(),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$body = array(
			'action'  => 'add_team_member',
			'team_id' => $this->helpers->get_team_id_from_parsed( $parsed, $this->get_action_meta() ),
			'user'    => $this->helpers->get_user_identifier_from_parsed( $parsed, 'TEAMS_ADD_MEMBER_USER' ),
			'role'    => $this->helpers->get_text_value_from_parsed( $parsed, 'TEAMS_ADD_MEMBER_ROLE', esc_html_x( 'Role', 'Microsoft Teams', 'uncanny-automator' ) ),
		);

		if ( 'true' === $this->get_parsed_meta_value( 'TEAMS_INVITE_GUEST', false ) ) {
			$body['create_if_not_found'] = 'true';
		}

		$response = $this->api->api_request( $body, $action_data );
		$data     = $response['data'] ?? array();

		$this->hydrate_tokens(
			array(
				'TEAMS_MEMBERSHIP_ID' => $data['id'] ?? '',
			)
		);

		return true;
	}

	/**
	 * Get the role option configuration.
	 *
	 * @return array
	 */
	private function get_role_option_config() {
		$roles = array(
			'member' => esc_html_x( 'Member', 'Microsoft Teams', 'uncanny-automator' ),
			'owner'  => esc_html_x( 'Owner', 'Microsoft Teams', 'uncanny-automator' ),
		);
		return array(
			'option_code'           => 'TEAMS_ADD_MEMBER_ROLE',
			'label'                 => esc_html_x( 'Role', 'Microsoft Teams', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'default_value'         => 'member',
			'options'               => automator_array_as_options( $roles ),
			'supports_custom_value' => false,
		);
	}
}
