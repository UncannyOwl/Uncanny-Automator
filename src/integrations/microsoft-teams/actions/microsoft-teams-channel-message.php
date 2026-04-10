<?php

namespace Uncanny_Automator\Integrations\Microsoft_Teams;

/**
 * Class MICROSOFT_TEAMS_CHANNEL_MESSAGE
 *
 * @package Uncanny_Automator
 *
 * @property Microsoft_Teams_App_Helpers $helpers
 * @property Microsoft_Teams_Api_Caller $api
 */
class MICROSOFT_TEAMS_CHANNEL_MESSAGE extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'MICROSOFT_TEAMS' );
		$this->set_action_code( 'CHANNEL_MESSAGE' );
		$this->set_action_meta( 'CHANNEL' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/microsoft-teams/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Send a message to {{a channel}}', 'Microsoft Teams', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the channel name
				esc_attr_x( 'Send a message to {{a channel:%1$s}}', 'Microsoft Teams', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_action_tokens(
			array(
				'TEAMS_CHANNEL_MESSAGE_ID' => array(
					'name' => esc_html_x( 'Message ID', 'Microsoft Teams', 'uncanny-automator' ),
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
			$this->helpers->get_team_select_option_config(),
			$this->helpers->get_channel_select_option_config( $this->get_action_meta(), 'TEAM', false ),
			$this->helpers->get_message_option_config(),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 * @throws Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$body = array(
			'action'     => 'channel_message',
			'team_id'    => $this->helpers->get_team_id_from_parsed( $parsed ),
			'channel_id' => $this->helpers->get_channel_id_from_parsed( $parsed, $this->get_action_meta() ),
			'message'    => $this->helpers->get_message_from_parsed( $parsed ),
		);

		$response = $this->api->api_request( $body, $action_data );
		$data     = $response['data'] ?? array();

		$this->hydrate_tokens(
			array(
				'TEAMS_CHANNEL_MESSAGE_ID' => $data['id'] ?? '',
			)
		);

		return true;
	}
}
