<?php

namespace Uncanny_Automator\Integrations\Microsoft_Teams;

/**
 * Class TEAMS_DELETE_CHANNEL
 *
 * @package Uncanny_Automator
 *
 * @property Microsoft_Teams_App_Helpers $helpers
 * @property Microsoft_Teams_Api_Caller $api
 */
class TEAMS_DELETE_CHANNEL extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'MICROSOFT_TEAMS' );
		$this->set_action_code( 'TEAMS_DELETE_CHANNEL_CODE' );
		$this->set_action_meta( 'TEAMS_DELETE_CHANNEL_ID' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/microsoft-teams/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Delete {{a channel}} from {{a team}}', 'Microsoft Teams', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: 1: Channel, 2: Team
				esc_attr_x( 'Delete {{a channel:%1$s}} from {{a team:%2$s}}', 'Microsoft Teams', 'uncanny-automator' ),
				$this->get_action_meta(),
				'TEAMS_DELETE_CHANNEL_TEAM:' . $this->get_action_meta()
			)
		);
	}

	/**
	 * Define the options for the action.
	 *
	 * @return array
	 */
	public function options() {

		$channel_config                = $this->helpers->get_channel_select_option_config( $this->get_action_meta(), 'TEAMS_DELETE_CHANNEL_TEAM' );
		$channel_config['description'] = esc_html_x( 'Note: the General channel cannot be deleted.', 'Microsoft Teams', 'uncanny-automator' );

		return array(
			$this->helpers->get_team_select_option_config( 'TEAMS_DELETE_CHANNEL_TEAM', true ),
			$channel_config,
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
		$team_id = $this->helpers->get_team_id_from_parsed( $parsed, 'TEAMS_DELETE_CHANNEL_TEAM' );

		$body = array(
			'action'     => 'delete_channel',
			'team_id'    => $team_id,
			'channel_id' => $this->helpers->get_channel_id_from_parsed( $parsed, $this->get_action_meta() ),
		);

		$this->api->api_request( $body, $action_data );

		// Invalidate the cached channels list for this team.
		automator_delete_option( $this->helpers->get_option_key( 'channels_' . $team_id ) );

		return true;
	}
}
