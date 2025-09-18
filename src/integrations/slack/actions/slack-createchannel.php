<?php

namespace Uncanny_Automator\Integrations\Slack;

/**
 * Class SLACK_CREATECHANNEL
 *
 * @package Uncanny_Automator
 *
 * @property Slack_App_Helpers $helpers
 * @property Slack_Api_Caller $api
 */
class SLACK_CREATECHANNEL extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup Action
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'SLACK' );
		$this->set_action_code( 'SLACKCREATECHANNEL' );
		$this->set_action_meta( 'SLACKCHANNEL' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/slack/' ) );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				// translators: slack channel
				esc_html_x( 'Create {{a channel:%1$s}}', 'Slack', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Create {{a channel}}', 'Slack', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * Define the action tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'CHANNEL_ID' => array(
				'name' => esc_html_x( 'Channel ID', 'Slack', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Define the action options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code' => 'SLACKCHANNELNAME',
				'label'       => esc_attr_x( 'Channel name', 'Slack', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
				'placeholder' => esc_attr_x( 'Enter channel name', 'Slack', 'uncanny-automator' ),
				'description' => esc_attr_x( 'Channel names can only contain lowercase letters, numbers, hyphens, and underscores, and must be 80 characters or less.', 'Slack', 'uncanny-automator' ),
			),
			// Temporary fix for the UI
			array(
				'input_type'      => 'text',
				'option_code'     => 'SLACKCHANNELHIDDEN',
				'is_hidden'       => true,
				'relevant_tokens' => array(),
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
		$parsed_channel_name = $this->get_parsed_meta_value( 'SLACKCHANNELNAME' );

		// Shorten the title and remove any special characters from it
		$channel_name = mb_strimwidth( sanitize_title( $parsed_channel_name ), 0, 80, '...' );

		$response = $this->api->conversations_create( $channel_name, $action_data );

		if ( isset( $response['data']['channel']['id'] ) ) {
			$this->hydrate_tokens(
				array(
					'CHANNEL_ID' => $response['data']['channel']['id'],
				)
			);
		}

		return true;
	}
}
