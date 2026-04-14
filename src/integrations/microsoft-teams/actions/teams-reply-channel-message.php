<?php

namespace Uncanny_Automator\Integrations\Microsoft_Teams;

/**
 * Class TEAMS_REPLY_CHANNEL_MESSAGE
 *
 * @package Uncanny_Automator
 *
 * @property Microsoft_Teams_App_Helpers $helpers
 * @property Microsoft_Teams_Api_Caller $api
 */
class TEAMS_REPLY_CHANNEL_MESSAGE extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'MICROSOFT_TEAMS' );
		$this->set_action_code( 'TEAMS_REPLY_CHANNEL_MSG_CODE' );
		$this->set_action_meta( 'TEAMS_REPLY_MESSAGE_ID' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/microsoft-teams/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Reply to a channel message', 'Microsoft Teams', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: 1: Message ID, 2: Channel
				esc_attr_x( 'Reply to message {{a message ID:%1$s}} in {{a channel:%2$s}}', 'Microsoft Teams', 'uncanny-automator' ),
				$this->get_action_meta(),
				'TEAMS_REPLY_CHANNEL:' . $this->get_action_meta()
			)
		);

		$this->set_action_tokens(
			array(
				'TEAMS_REPLY_ID' => array(
					'name' => esc_html_x( 'Reply message ID', 'Microsoft Teams', 'uncanny-automator' ),
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
			$this->helpers->get_channel_select_option_config( 'TEAMS_REPLY_CHANNEL' ),
			$this->get_message_id_option_config(),
			$this->get_content_type_option_config(),
			$this->get_reply_body_option_config(),
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
			'action'       => 'reply_channel_message',
			'team_id'      => $this->helpers->get_team_id_from_parsed( $parsed ),
			'channel_id'   => $this->helpers->get_channel_id_from_parsed( $parsed, 'TEAMS_REPLY_CHANNEL' ),
			'message_id'   => $this->helpers->get_text_value_from_parsed( $parsed, $this->get_action_meta(), esc_html_x( 'Message ID', 'Microsoft Teams', 'uncanny-automator' ) ),
			'body'         => $this->helpers->get_message_from_parsed( $parsed, 'TEAMS_REPLY_BODY' ),
			'content_type' => $this->helpers->get_text_value_from_parsed( $parsed, 'TEAMS_REPLY_CONTENT_TYPE', esc_html_x( 'Content type', 'Microsoft Teams', 'uncanny-automator' ) ),
		);

		$response = $this->api->api_request( $body, $action_data );
		$data     = $response['data'] ?? array();

		$this->hydrate_tokens(
			array(
				'TEAMS_REPLY_ID' => $data['id'] ?? '',
			)
		);

		return true;
	}

	////////////////////////////////////////////////////////////
	// Option configurations
	////////////////////////////////////////////////////////////

	/**
	 * Get the message ID option configuration.
	 *
	 * @return array
	 */
	private function get_message_id_option_config() {
		return array(
			'option_code' => $this->get_action_meta(),
			'input_type'  => 'text',
			'label'       => esc_html_x( 'Message ID', 'Microsoft Teams', 'uncanny-automator' ),
			'description' => esc_html_x( 'The numeric message ID, e.g. 1616990032035', 'Microsoft Teams', 'uncanny-automator' ) . $this->helpers->get_kb_learn_more_link(),
			'required'    => true,
		);
	}

	/**
	 * Get the content type option configuration.
	 *
	 * @return array
	 */
	private function get_content_type_option_config() {
		$types = array(
			'text' => esc_html_x( 'Plain text', 'Microsoft Teams', 'uncanny-automator' ),
			'html' => esc_html_x( 'HTML', 'Microsoft Teams', 'uncanny-automator' ),
		);
		return array(
			'option_code'           => 'TEAMS_REPLY_CONTENT_TYPE',
			'label'                 => esc_html_x( 'Content type', 'Microsoft Teams', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'default_value'         => 'text',
			'options'               => automator_array_as_options( $types ),
			'supports_custom_value' => false,
		);
	}

	/**
	 * Get the reply body option configuration.
	 *
	 * @return array
	 */
	private function get_reply_body_option_config() {
		return array(
			'option_code' => 'TEAMS_REPLY_BODY',
			'input_type'  => 'textarea',
			'label'       => esc_html_x( 'Reply content', 'Microsoft Teams', 'uncanny-automator' ),
			'required'    => true,
		);
	}
}
