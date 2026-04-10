<?php

namespace Uncanny_Automator\Integrations\Microsoft_Teams;

/**
 * Class TEAMS_CREATE_TAG
 *
 * @package Uncanny_Automator
 *
 * @property Microsoft_Teams_App_Helpers $helpers
 * @property Microsoft_Teams_Api_Caller $api
 */
class TEAMS_CREATE_TAG extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'MICROSOFT_TEAMS' );
		$this->set_action_code( 'TEAMS_CREATE_TAG_CODE' );
		$this->set_action_meta( 'TEAMS_TAG_NAME' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/microsoft-teams/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Create a tag in {{a team}}', 'Microsoft Teams', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: 1: Tag name, 2: Team
				esc_attr_x( 'Create a tag {{a tag name:%1$s}} in {{a team:%2$s}}', 'Microsoft Teams', 'uncanny-automator' ),
				$this->get_action_meta(),
				'TEAMS_TAG_TEAM:' . $this->get_action_meta()
			)
		);
		$this->set_action_tokens(
			array(
				'TEAMS_TAG_ID' => array(
					'name' => esc_html_x( 'Tag ID', 'Microsoft Teams', 'uncanny-automator' ),
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
			$this->helpers->get_team_select_option_config( 'TEAMS_TAG_TEAM' ),
			$this->get_tag_name_option_config(),
			$this->get_initial_member_option_config(),
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
		$team_id = $this->helpers->get_team_id_from_parsed( $parsed, 'TEAMS_TAG_TEAM' );

		$body = array(
			'action'   => 'create_team_tag',
			'team_id'  => $team_id,
			'tag_name' => $this->helpers->get_text_value_from_parsed( $parsed, $this->get_action_meta(), esc_html_x( 'Tag name', 'Microsoft Teams', 'uncanny-automator' ) ),
			'member'   => $this->helpers->get_user_identifier_from_parsed( $parsed, 'TEAMS_TAG_INITIAL_MEMBER' ),
		);

		$response = $this->api->api_request( $body, $action_data );
		$data     = $response['data'] ?? array();

		$this->hydrate_tokens(
			array(
				'TEAMS_TAG_ID' => $data['id'] ?? '',
			)
		);

		// Invalidate the cached tags list for this team.
		automator_delete_option( $this->helpers->get_option_key( 'tags_' . $team_id ) );

		return true;
	}

	////////////////////////////////////////////////////////////
	// Option configurations
	////////////////////////////////////////////////////////////

	/**
	 * Get the tag name option configuration.
	 *
	 * @return array
	 */
	private function get_tag_name_option_config() {
		return array(
			'option_code' => $this->get_action_meta(),
			'input_type'  => 'text',
			'label'       => esc_html_x( 'Tag name', 'Microsoft Teams', 'uncanny-automator' ),
			'description' => esc_html_x( 'Maximum 40 characters.', 'Microsoft Teams', 'uncanny-automator' ),
			'required'    => true,
		);
	}

	/**
	 * Get the initial member option configuration.
	 *
	 * @return array
	 */
	private function get_initial_member_option_config() {
		return array(
			'option_code' => 'TEAMS_TAG_INITIAL_MEMBER',
			'input_type'  => 'text',
			'label'       => esc_html_x( 'Initial member (email or AAD Object ID)', 'Microsoft Teams', 'uncanny-automator' ),
			'description' => esc_html_x( 'At least one member is required to create a tag.', 'Microsoft Teams', 'uncanny-automator' ),
			'required'    => true,
		);
	}
}
