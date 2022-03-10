<?php

namespace Uncanny_Automator;

/**
 * Class MAILPOET_ADDUSER_A
 *
 * @package Uncanny_Automator
 */
class MAILPOET_ADDUSER_A {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'MAILPOET';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'USERADDEDTOLIST';
		$this->action_meta = 'MAILPOETLISTS';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/mailpoet/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - MailPoet */
			'sentence'           => sprintf( esc_attr__( 'Add the user to {{a list:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - MailPoet */
			'select_option_name' => esc_attr__( 'Add the user to {{a list}}', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'mailpoet_add_user_to_list' ),
			'options'            => array(),
			'options_group'      => array(
				$this->action_meta =>
					array(
						Automator()->helpers->recipe->mailpoet->options->get_all_mailpoet_lists( esc_attr__( 'List', 'uncanny-automator' ), $this->action_meta ),
						Automator()->helpers->recipe->field->text_field( 'USERADDEDTOLIST_CONFIRMATIONEMAIL', esc_attr__( 'Add the user directly to the list - Do not send confirmation email', 'uncanny-automator' ), true, 'checkbox', '', false ),
					),
			),
		);

		Automator()->register->action( $action );
	}


	/**
	 * Validation function when the action is hit.
	 *
	 * @param string $user_id user id.
	 * @param array $action_data action data.
	 * @param string $recipe_id recipe id.
	 */
	public function mailpoet_add_user_to_list( $user_id, $action_data, $recipe_id, $args ) {

		if ( ! class_exists( '\MailPoet\API\API' ) ) {
			$error_message = 'The class \MailPoet\API\API does not exist';
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		try {
			$list_id = $action_data['meta'][ $this->action_meta ];

			// add user to a list.
			$mailpoet = \MailPoet\API\API::MP( 'v1' );

			$userdata = get_userdata( $user_id );

			$subscriber                 = $mailpoet->getSubscriber( $userdata->user_email );
			$disable_confirmation_email = true;
			if ( isset( $action_data['meta']['USERADDEDTOLIST_CONFIRMATIONEMAIL'] ) ) {
				$disable_confirmation_email = Automator()->parse->text( $action_data['meta']['USERADDEDTOLIST_CONFIRMATIONEMAIL'], $recipe_id, $user_id, $args );
				$disable_confirmation_email = 'true' === $disable_confirmation_email ? false : true;
			}

			$mailpoet->subscribeToList( $subscriber['id'], $list_id, array( 'send_confirmation_email' => $disable_confirmation_email ) );
			Automator()->complete_action( $user_id, $action_data, $recipe_id );
		} catch ( \MailPoet\API\MP\v1\APIException $e ) {
			$error_message                       = $e->getMessage();
			$recipe_log_id                       = $action_data['recipe_log_id'];
			$args['do-nothing']                  = true;
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args );
		}
	}
}
