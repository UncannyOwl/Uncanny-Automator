<?php

namespace Uncanny_Automator\Integrations\Microsoft_Teams;

/**
 * Class MICROSOFT_TEAMS_CREATE_CHANNEL
 *
 * @package Uncanny_Automator
 *
 * @property Microsoft_Teams_App_Helpers $helpers
 * @property Microsoft_Teams_Api_Caller $api
 */
class MICROSOFT_TEAMS_CREATE_CHANNEL extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'MICROSOFT_TEAMS' );
		$this->set_action_code( 'CREATE_CHANNEL' );
		$this->set_action_meta( 'CHANNEL' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/microsoft-teams/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Create {{a channel}}', 'Microsoft Teams', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the channel name
				esc_attr_x( 'Create {{a channel:%1$s}}', 'Microsoft Teams', 'uncanny-automator' ),
				'NAME:' . $this->get_action_meta()
			)
		);

		$this->set_action_tokens(
			array(
				'CHANNEL_ID'  => array(
					'name' => esc_html_x( 'Channel ID', 'Microsoft Teams', 'uncanny-automator' ),
					'type' => 'text',
				),
				'CHANNEL_URL' => array(
					'name' => esc_html_x( 'Channel URL', 'Microsoft Teams', 'uncanny-automator' ),
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
			$this->get_channel_name_option_config(),
			$this->helpers->get_description_option_config(),
			$this->get_channel_type_option_config(),
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

		$channel = array(
			'displayName'           => mb_strimwidth( $this->helpers->get_text_value_from_parsed( $parsed, 'NAME', esc_html_x( 'Channel name', 'Microsoft Teams', 'uncanny-automator' ) ), 0, 50 ),
			'description'           => $this->get_parsed_meta_value( 'DESCRIPTION' ),
			'channelMembershipType' => $this->helpers->get_text_value_from_parsed( $parsed, 'CHANNEL_TYPE', esc_html_x( 'Privacy', 'Microsoft Teams', 'uncanny-automator' ) ),
		);

		$team_id = $this->helpers->get_team_id_from_parsed( $parsed );

		$body = array(
			'action'  => 'create_channel',
			'channel' => wp_json_encode( $channel ),
			'team_id' => $team_id,
		);

		$response = $this->api->api_request( $body, $action_data );
		$data     = $response['data'] ?? array();

		if ( ! isset( $data['id'] ) ) {
			throw new \Exception( esc_html_x( 'The channel was created but no ID was returned.', 'Microsoft Teams', 'uncanny-automator' ) );
		}

		$this->hydrate_tokens(
			array(
				'CHANNEL_ID'  => $data['id'] ?? '',
				'CHANNEL_URL' => $data['webUrl'] ?? '',
			)
		);

		// Invalidate the cached channels list for this team so it loads fresh in the UI.
		automator_delete_option( $this->helpers->get_option_key( 'channels_' . $team_id ) );

		return true;
	}

	////////////////////////////////////////////////////////////
	// Option configurations
	////////////////////////////////////////////////////////////

	/**
	 * Get the channel name option configuration.
	 *
	 * @return array
	 */
	private function get_channel_name_option_config() {
		return array(
			'option_code' => 'NAME',
			'input_type'  => 'text',
			'label'       => esc_html_x( 'Channel name', 'Microsoft Teams', 'uncanny-automator' ),
			'required'    => true,
		);
	}

	/**
	 * Get the channel type option configuration.
	 *
	 * @return array
	 */
	private function get_channel_type_option_config() {
		return array(
			'option_code'           => 'CHANNEL_TYPE',
			'label'                 => esc_html_x( 'Privacy', 'Microsoft Teams', 'uncanny-automator' ),
			'placeholder'           => esc_html_x( 'Select a privacy level', 'Microsoft Teams', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options'               => $this->get_channel_type_options(),
			'supports_custom_value' => false,
		);
	}

	/**
	 * Get channel type options.
	 *
	 * @return array
	 */
	private function get_channel_type_options() {

		$channel_types = array(
			'standard' => esc_html_x( 'Standard', 'Microsoft Teams', 'uncanny-automator' ),
			'private'  => esc_html_x( 'Private', 'Microsoft Teams', 'uncanny-automator' ),
			'shared'   => esc_html_x( 'Shared', 'Microsoft Teams', 'uncanny-automator' ),
		);

		$channel_types = apply_filters( 'automator_microsoft_teams_channel_types', $channel_types );

		return automator_array_as_options( $channel_types );
	}
}
