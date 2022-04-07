<?php

namespace Uncanny_Automator;

/**
 * Class SLACK_CREATECHANNEL
 *
 * @package Uncanny_Automator
 */
class SLACK_CREATECHANNEL {
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
		$this->action_code = 'SLACKCREATECHANNEL';
		$this->action_meta = 'SLACKCHANNEL';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object.
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'knowledge-base/slack/' ),
			'is_pro'             => false,
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			'requires_user'      => false,
			'sentence'           => sprintf( __( 'Create {{a channel:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			'select_option_name' => __( 'Create {{a channel}}', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'create_channel' ),
			'options_group'      => array(
				$this->action_meta => array(

					Automator()->helpers->recipe->field->text_field( 'SLACKCHANNELNAME', esc_attr__( 'Channel name', 'uncanny-automator' ) ),
					//Temporary fix for the UI
					array(
						'input_type'  => 'text',
						'option_code' => 'SLACKCHANNELHIDDEN',
						'is_hidden'   => true,
					),
				),
			),
		);

		Automator()->register->action( $action );
	}

	/**
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 */
	public function create_channel( $user_id, $action_data, $recipe_id, $args ) {

		$channel = array();

		$parsed_channel_name = Automator()->parse->text( $action_data['meta']['SLACKCHANNELNAME'], $recipe_id, $user_id, $args );

		//Shorten the title and remove any special characters from it
		$channel_name = mb_strimwidth( sanitize_title( $parsed_channel_name ), 0, 80, '...' );

		$error_msg = '';

		try {
			$response = Automator()->helpers->recipe->slack->conversations_create( $channel_name, $action_data );
		} catch ( \Exception $e ) {
			$error_msg                           = $e->getMessage();
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
		}

		Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_msg );
		return;
	}
}
