<?php

namespace Uncanny_Automator\Integrations\Microsoft_Teams;

/**
 * Class TEAMS_REMOVE_MEMBER
 *
 * @package Uncanny_Automator
 *
 * @property Microsoft_Teams_App_Helpers $helpers
 * @property Microsoft_Teams_Api_Caller $api
 */
class TEAMS_REMOVE_MEMBER extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'MICROSOFT_TEAMS' );
		$this->set_action_code( 'TEAMS_REMOVE_MEMBER_CODE' );
		$this->set_action_meta( 'TEAMS_REMOVE_MEMBER_TEAM' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/microsoft-teams/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Remove {{a user}} from {{a team}}', 'Microsoft Teams', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: 1: User, 2: Team
				esc_attr_x( 'Remove {{a user:%1$s}} from {{a team:%2$s}}', 'Microsoft Teams', 'uncanny-automator' ),
				'TEAMS_REMOVE_MEMBER_USER:' . $this->get_action_meta(),
				$this->get_action_meta()
			)
		);
	}

	/**
	 * Define the options for the action.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_user_option_config( 'TEAMS_REMOVE_MEMBER_USER' ),
			$this->helpers->get_team_select_option_config( $this->get_action_meta() ),
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
			'action'  => 'remove_team_member',
			'team_id' => $this->helpers->get_team_id_from_parsed( $parsed, $this->get_action_meta() ),
			'user'    => $this->helpers->get_user_identifier_from_parsed( $parsed, 'TEAMS_REMOVE_MEMBER_USER' ),
		);

		$this->api->api_request( $body, $action_data );

		return true;
	}
}
