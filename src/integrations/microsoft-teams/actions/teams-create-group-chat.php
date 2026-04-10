<?php

namespace Uncanny_Automator\Integrations\Microsoft_Teams;

/**
 * Class TEAMS_CREATE_GROUP_CHAT
 *
 * @package Uncanny_Automator
 *
 * @property Microsoft_Teams_App_Helpers $helpers
 * @property Microsoft_Teams_Api_Caller $api
 */
class TEAMS_CREATE_GROUP_CHAT extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'MICROSOFT_TEAMS' );
		$this->set_action_code( 'TEAMS_CREATE_GROUP_CHAT_CODE' );
		$this->set_action_meta( 'TEAMS_GROUP_CHAT_TOPIC' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/microsoft-teams/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Create a group chat', 'Microsoft Teams', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: 1: Group chat
				esc_attr_x( 'Create {{a group chat:%1$s}}', 'Microsoft Teams', 'uncanny-automator' ),
				'CHAT:' . $this->get_action_meta()
			)
		);

		$this->set_action_tokens(
			array(
				'TEAMS_GROUP_CHAT_ID'  => array(
					'name' => esc_html_x( 'Chat ID', 'Microsoft Teams', 'uncanny-automator' ),
					'type' => 'text',
				),
				'TEAMS_GROUP_CHAT_URL' => array(
					'name' => esc_html_x( 'Chat URL', 'Microsoft Teams', 'uncanny-automator' ),
					'type' => 'url',
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
			$this->get_members_option_config(),
			$this->get_topic_option_config(),
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
			'action'  => 'create_group_chat',
			'topic'   => $this->get_parsed_meta_value( $this->get_action_meta() ),
			'members' => $this->get_validated_members(),
		);

		if ( 'true' === $this->get_parsed_meta_value( 'TEAMS_INVITE_GUEST', false ) ) {
			$body['create_if_not_found'] = 'true';
		}

		$response = $this->api->api_request( $body, $action_data );
		$data     = $response['data'] ?? array();

		$this->hydrate_tokens(
			array(
				'TEAMS_GROUP_CHAT_ID'  => $data['id'] ?? '',
				'TEAMS_GROUP_CHAT_URL' => $data['webUrl'] ?? '',
			)
		);

		return true;
	}

	////////////////////////////////////////////////////////////
	// Option configurations
	////////////////////////////////////////////////////////////

	/**
	 * Get the topic option configuration.
	 *
	 * @return array
	 */
	private function get_topic_option_config() {
		return array(
			'option_code' => $this->get_action_meta(),
			'input_type'  => 'text',
			'label'       => esc_html_x( 'Chat topic (name)', 'Microsoft Teams', 'uncanny-automator' ),
			'description' => esc_html_x( 'Optional display name for the group chat.', 'Microsoft Teams', 'uncanny-automator' ),
			'required'    => false,
		);
	}

	/**
	 * Get the members repeater option configuration.
	 *
	 * @return array
	 */
	private function get_members_option_config() {
		$role_options = array(
			'owner' => esc_html_x( 'Owner', 'Microsoft Teams', 'uncanny-automator' ),
			'guest' => esc_html_x( 'Guest', 'Microsoft Teams', 'uncanny-automator' ),
		);

		return array(
			'option_code' => 'TEAMS_GROUP_CHAT_MEMBERS',
			'input_type'  => 'repeater',
			'label'       => esc_html_x( 'Members', 'Microsoft Teams', 'uncanny-automator' ),
			'description' => esc_html_x( 'The connected Microsoft Teams account is automatically included in the chat. Add at least 1 additional member using their email address or AAD Object ID.', 'Microsoft Teams', 'uncanny-automator' ),
			'required'    => true,
			'fields'      => array(
				array(
					'option_code' => 'MEMBER_USER',
					'input_type'  => 'text',
					'label'       => esc_html_x( 'User (email or AAD Object ID)', 'Microsoft Teams', 'uncanny-automator' ),
					'required'    => true,
				),
				array(
					'option_code'     => 'MEMBER_ROLE',
					'input_type'      => 'select',
					'label'           => esc_html_x( 'Role', 'Microsoft Teams', 'uncanny-automator' ),
					'required'        => true,
					'default_value'   => 'owner',
					'options'         => automator_array_as_options( $role_options ),
					'options_show_id' => false,
				),
			),
		);
	}

	////////////////////////////////////////////////////////////
	// Parsed value validators
	////////////////////////////////////////////////////////////

	/**
	 * Extract, sanitize, and validate members from the repeater field.
	 *
	 * @return string JSON-encoded array of Graph API member objects.
	 * @throws \Exception If no valid members are provided.
	 */
	private function get_validated_members() {

		$members_raw  = $this->get_parsed_meta_value( 'TEAMS_GROUP_CHAT_MEMBERS' );
		$members_data = json_decode( $members_raw, true );

		if ( ! is_array( $members_data ) ) {
			throw new \Exception( esc_html_x( 'A group chat requires at least 1 member in addition to the connected account.', 'Microsoft Teams', 'uncanny-automator' ) );
		}

		$members = array();

		foreach ( $members_data as $row ) {
			$member_user = sanitize_text_field( $row['MEMBER_USER'] ?? '' );

			if ( empty( $member_user ) ) {
				continue;
			}

			$role = strtolower( trim( sanitize_text_field( $row['MEMBER_ROLE'] ?? 'owner' ) ) );

			$this->validate_member_role( $role );

			$members[] = array(
				'@odata.type'     => '#microsoft.graph.aadUserConversationMember',
				'roles'           => array( $role ),
				'user@odata.bind' => "https://graph.microsoft.com/v1.0/users('" . rawurlencode( $member_user ) . "')",
			);
		}

		if ( count( $members ) < 1 ) {
			throw new \Exception( esc_html_x( 'A group chat requires at least 1 member in addition to the connected account.', 'Microsoft Teams', 'uncanny-automator' ) );
		}

		return wp_json_encode( $members );
	}

	/**
	 * Validate a single member role.
	 *
	 * @param mixed $role The role value to validate.
	 *
	 * @return void
	 * @throws \Exception If the role is invalid.
	 */
	private function validate_member_role( $role ) {

		if ( 'owner' !== $role && 'guest' !== $role ) {
			throw new \Exception( esc_html_x( 'Invalid chat member role. Must be "owner" or "guest".', 'Microsoft Teams', 'uncanny-automator' ) );
		}
	}
}
