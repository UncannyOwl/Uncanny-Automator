<?php

namespace Uncanny_Automator;

/**
 * Class SLACK_SENDMESSAGE
 *
 * @package Uncanny_Automator
 */
class SLACK_SENDMESSAGE {
	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'SLACK';

	/**
	 * @var string
	 */
	private $action_code;
	/**
	 * @var string
	 */
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'SLACKSENDMESSAGE';
		$this->action_meta = 'SLACKCHANNEL';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object.
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name(),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'knowledge-base/slack/' ),
			'is_pro'             => false,
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			'requires_user'      => false,
			'sentence'           => sprintf( __( 'Send a message to {{a channel:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			'select_option_name' => __( 'Send a message to {{a channel}}', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'send_message' ),
			'options_callback'   => array( $this, 'load_options' ),
		);

		Automator()->register->action( $action );
	}

	/**
	 * load_options
	 *
	 * @return void
	 */
	public function load_options() {
		return array(
			'options_group' => array(
				$this->action_meta => array(
					Automator()->helpers->recipe->slack->options->get_slack_channels( esc_attr__( 'Slack Channel', 'uncanny-automator' ), 'SLACKCHANNEL' ),
					Automator()->helpers->recipe->slack->textarea_field( 'SLACKMESSAGE', esc_attr__( 'Message', 'uncanny-automator' ), true, 'textarea', '', true, esc_attr__( '* Markdown is supported', 'uncanny-automator' ), __( 'Enter the message', 'uncanny-automator' ) ),
				),
			),
		);
	}

	/**
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 */
	public function send_message( $user_id, $action_data, $recipe_id, $args ) {

		$message            = array();
		$message['channel'] = $action_data['meta']['SLACKCHANNEL'];
		$message['text']    = Automator()->parse->text( $action_data['meta']['SLACKMESSAGE'], $recipe_id, $user_id, $args );
		
		$error_msg = '';
		
		try {
			$response = Automator()->helpers->recipe->slack->chat_post_message( $message, $action_data );
		} catch ( \Exception $e ) {
			$error_msg                           = $e->getMessage();
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
		}

		Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_msg );
		return;
	}
}
