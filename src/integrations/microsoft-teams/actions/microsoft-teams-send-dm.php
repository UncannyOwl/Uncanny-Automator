<?php

namespace Uncanny_Automator\Integrations\Microsoft_Teams;

/**
 * Class MICROSOFT_TEAMS_SEND_DM
 *
 * @package Uncanny_Automator
 *
 * @property Microsoft_Teams_App_Helpers $helpers
 * @property Microsoft_Teams_Api_Caller $api
 */
class MICROSOFT_TEAMS_SEND_DM extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'MICROSOFT_TEAMS' );
		$this->set_action_code( 'SEND_DIRECT_MESSAGE' );
		$this->set_action_meta( 'MEMBER' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/microsoft-teams/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Send a direct message to {{a team member}}', 'Microsoft Teams', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the team member
				esc_attr_x( 'Send a direct message to {{a team member:%1$s}}', 'Microsoft Teams', 'uncanny-automator' ),
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
			$this->helpers->get_team_select_option_config(),
			$this->get_member_select_option_config(),
			$this->helpers->get_message_option_config(),
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
			'action'    => 'member_message',
			'member_id' => $this->helpers->get_text_value_from_parsed( $parsed, $this->get_action_meta(), esc_html_x( 'Member', 'Microsoft Teams', 'uncanny-automator' ) ),
			'message'   => $this->helpers->get_message_from_parsed( $parsed ),
		);

		$this->api->api_request( $body, $action_data );

		return true;
	}

	/**
	 * Get the member select option configuration.
	 *
	 * @return array
	 */
	private function get_member_select_option_config() {
		return array(
			'option_code'           => $this->get_action_meta(),
			'label'                 => esc_html_x( 'Member', 'Microsoft Teams', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options'               => array(),
			'supports_custom_value' => false,
			'ajax'                  => array(
				'endpoint'      => 'automator_microsoft_teams_get_team_members',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( 'TEAM' ),
			),
		);
	}
}
