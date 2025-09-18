<?php

namespace Uncanny_Automator\Integrations\Slack;

/**
 * Class SLACK_SENDMESSAGE
 *
 * @package Uncanny_Automator
 *
 * @property Slack_App_Helpers $helpers
 * @property Slack_Api_Caller $api
 */
class SLACK_SENDMESSAGE extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup Action
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'SLACK' );
		$this->set_action_code( 'SLACKSENDMESSAGE' );
		$this->set_action_meta( 'SLACKCHANNEL' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/slack/' ) );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				// translators: slack channel name
				esc_html_x( 'Send a message to {{a channel:%1$s}}', 'Slack', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Send a message to {{a channel}}', 'Slack', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * Define the action options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_channel_select_config(),
			$this->helpers->get_bot_name_config(),
			$this->helpers->get_bot_icon_config(),
			$this->helpers->get_message_textarea_config(),
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
		$message = array(
			'channel' => $this->get_parsed_meta_value( 'SLACKCHANNEL' ),
			'text'    => $this->get_parsed_meta_value( 'SLACKMESSAGE' ),
		);

		$bot_name = $this->get_parsed_meta_value( 'BOT_NAME' );
		if ( ! empty( $bot_name ) ) {
			$message['username'] = $bot_name;
		}

		$bot_icon = $this->get_parsed_meta_value( 'BOT_ICON' );
		if ( ! empty( $bot_icon ) ) {
			$message['icon_url'] = $bot_icon;
		}

		$response = $this->api->chat_post_message( $message, $action_data );

		if ( isset( $response['data']['error'] ) ) {
			throw new \Exception( esc_html( $response['data']['error'] ), 400 );
		}

		return true;
	}
}
