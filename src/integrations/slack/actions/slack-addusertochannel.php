<?php

namespace Uncanny_Automator\Integrations\Slack;

/**
 * Class SLACK_ADDUSERTOCHANNEL
 *
 * @package Uncanny_Automator
 *
 * @property Slack_App_Helpers $helpers
 * @property Slack_Api_Caller $api
 */
class SLACK_ADDUSERTOCHANNEL extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup Action
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'SLACK' );
		$this->set_action_code( 'SLACKADDUSERTOCHANNEL' );
		$this->set_action_meta( 'SLACKCHANNEL' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/slack/' ) );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				// translators: slack channel and user
				esc_html_x( 'Add {{users:%1$s}} to {{a channel:%2$s}}', 'Slack', 'uncanny-automator' ),
				'INVALID:' . $this->get_action_meta(),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Add {{users}} to {{a channel}}', 'Slack', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * Define the action options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'            => 'SLACKUSERS',
				'label'                  => esc_attr_x( 'Users', 'Slack', 'uncanny-automator' ),
				'input_type'             => 'select',
				'required'               => true,
				'supports_custom_value'  => true,
				'show_label_in_sentence' => true,
				'supports_multiple_values' => true,
				'description'            => esc_attr_x( 'Select one or more users to add to the channel.', 'Slack', 'uncanny-automator' ),
				'ajax'                   => array(
					'endpoint' => 'automator_slack_get_users',
					'event'    => 'on_load',
				),
			),
			array(
				'option_code'            => $this->get_action_meta(),
				'label'                  => esc_attr_x( 'Channel', 'Slack', 'uncanny-automator' ),
				'input_type'             => 'select',
				'required'               => true,
				'supports_custom_value'  => true,
				'show_label_in_sentence' => true,
				'description'            => esc_attr_x( 'Select the channel to add the user to. The bot must be a member of this channel.', 'Slack', 'uncanny-automator' ),
				'ajax'                   => array(
					'endpoint' => 'automator_slack_get_channels',
					'event'    => 'on_load',
				),
			),
		);
	}

	/**
	 * Process the Slack action.
	 *
	 * @param int    $user_id
	 * @param array  $action_data
	 * @param int    $recipe_id
	 * @param array  $args
	 * @param array  $parsed
	 *
	 * @return bool
	 * @throws \Exception When the action fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$slack_users = $this->get_parsed_meta_value( 'SLACKUSERS' );
		$channel_id  = $this->get_parsed_meta_value( 'SLACKCHANNEL' );

		// Validate required fields
		if ( empty( $slack_users ) || '-1' === $slack_users ) {
			throw new \Exception( esc_html_x( 'Please select one or more users to add to the channel.', 'Slack', 'uncanny-automator' ), 400 );
		}

		if ( empty( $channel_id ) || '-1' === $channel_id ) {
			throw new \Exception( esc_html_x( 'Please select a channel to add the users to.', 'Slack', 'uncanny-automator' ), 400 );
		}

		// Handle multiple users - convert to comma-separated string if it's an array
		if ( is_array( $slack_users ) ) {
			$slack_users = implode( ',', $slack_users );
		} elseif ( is_string( $slack_users ) && strpos( $slack_users, '[' ) === 0 ) {
			// Handle JSON array string like '["U123","U456"]'
			$decoded = json_decode( $slack_users, true );
			if ( is_array( $decoded ) ) {
				$slack_users = implode( ',', $decoded );
			}
		}

		$response = $this->api->conversations_invite( $channel_id, $slack_users, $action_data );

		if ( isset( $response['data']['error'] ) ) {
			$error_message = $response['data']['error'];

			// Provide user-friendly error messages
			switch ( $error_message ) {
				case 'channel_not_found':
					throw new \Exception( esc_html_x( 'Channel not found. Please check the channel ID and try again.', 'Slack', 'uncanny-automator' ), 400 );
				case 'user_not_found':
					throw new \Exception( esc_html_x( 'One or more users not found. Please check the user IDs and try again.', 'Slack', 'uncanny-automator' ), 400 );
				case 'not_in_channel':
					throw new \Exception( esc_html_x( 'The bot is not a member of this channel. Please join the channel first or use a different channel.', 'Slack', 'uncanny-automator' ), 400 );
				case 'already_in_channel':
					throw new \Exception( esc_html_x( 'One or more users are already members of this channel.', 'Slack', 'uncanny-automator' ), 400 );
				case 'cant_invite_self':
					throw new \Exception( esc_html_x( 'Cannot invite the bot to join itself.', 'Slack', 'uncanny-automator' ), 400 );
				case 'cant_invite':
					throw new \Exception( esc_html_x( 'Cannot invite one or more users to the channel. The users may not have permission to join or the channel may be restricted.', 'Slack', 'uncanny-automator' ), 400 );
				default:
					throw new \Exception( esc_html_x( 'Slack API returned an error: ', 'Slack', 'uncanny-automator' ) . esc_html( $error_message ), 400 );
			}
		}

		return true;
	}
}
