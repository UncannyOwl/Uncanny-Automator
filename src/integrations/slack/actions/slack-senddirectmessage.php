<?php

namespace Uncanny_Automator\Integrations\Slack;

/**
 * Class SLACK_SENDDIRECTMESSAGE
 *
 * @package Uncanny_Automator
 *
 * @property Slack_App_Helpers $helpers
 * @property Slack_Api_Caller $api
 */
class SLACK_SENDDIRECTMESSAGE extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup Action
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'SLACK' );
		$this->set_action_code( 'SLACKSENDDIRECTMESSAGE' );
		$this->set_action_meta( 'SLACKUSER' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/slack/' ) );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				// translators: slack username
				esc_html_x( 'Send a direct message to {{a Slack user:%1$s}}', 'Slack', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Send a direct message to {{a Slack user}}', 'Slack', 'uncanny-automator' ) );
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
				'option_code'            => $this->get_action_meta(),
				'label'                  => esc_attr_x( 'User', 'Slack', 'uncanny-automator' ),
				'input_type'             => 'select',
				'required'               => true,
				'supports_custom_value'  => true,
				'show_label_in_sentence' => true,
				'ajax'                   => array(
					'endpoint' => 'automator_slack_get_users',
					'event'    => 'on_load',
				),
			),
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
			'channel'  => $this->get_parsed_meta_value( 'SLACKUSER' ),
			'text'     => Automator()->parse->text( $action_data['meta']['SLACKMESSAGE'] ?? '', $recipe_id, $user_id, $args ),
			'username' => $this->get_parsed_meta_value( 'BOT_NAME' ),
			'icon_url' => $this->get_parsed_meta_value( 'BOT_ICON' ),
		);

		$response = $this->api->chat_post_message( $message, $action_data );

		if ( isset( $response['data']['error'] ) ) {
			throw new \Exception( esc_html( $response['data']['error'] ), 400 );
		}

		return true;
	}
}
