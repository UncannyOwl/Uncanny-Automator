<?php
/**
 * Created by PhpStorm.
 * User: Huma
 * Date: 9/16/2020
 * Time: 4:22 PM
 */

namespace Uncanny_Automator;

use MailPoet\Entities\SubscriberEntity;

/**
 * Class MAILPOET_ADDSUBSCRIBERTOLIST_A
 *
 * @package Uncanny_Automator
 */
class MAILPOET_ADDSUBSCRIBERTOLIST_A {


	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'MAILPOET';

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
		$this->action_code = 'SUBSCRIBERTOLIST';
		$this->action_meta = 'MAILPOETLISTS';
		$this->define_action();
		add_action(
			'automator_mailpoet_subscribe_to_list',
			array(
				$this,
				'subscribe_to_the_list',
			),
			99,
			6
		);
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
			'requires_user'      => false,
			/* translators: Action - MailPoet */
			'sentence'           => sprintf( esc_attr__( 'Add {{a subscriber:%1$s}} to {{a list:%2$s}}', 'uncanny-automator' ), 'ADDSUBSCRIBER', $this->action_meta ),
			/* translators: Action - MailPoet */
			'select_option_name' => esc_attr__( 'Add {{a subscriber}} to {{a list}}', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array(
				$this,
				'mailpoet_add_subscriber_to_list',
			),
			'options_callback'   => array( $this, 'load_options' ),

		);

		Automator()->register->action( $action );
	}

	/**
	 * @return array[]
	 */
	public function load_options() {
		$mailpoet  = \MailPoet\API\API::MP( 'v1' );
		$all_lists = $mailpoet->getLists();
		$options   = array();
		foreach ( $all_lists as $list ) {
			$options[ $list['id'] ] = $list['name'];
		}

		$subscriber_status = array(
			'subscribed'   => 'Subscribed',
			'unconfirmed'  => 'Unconfirmed',
			'unsubscribed' => 'Unsubscribed',
			'inactive'     => 'Inactive',
			'bounced'      => 'Bounced',
		);

		return Automator()->utilities->keep_order_of_options(
			array(
				'options'       => array(),
				'options_group' =>
					array(
						'ADDSUBSCRIBER'    =>
							array(
								Automator()->helpers->recipe->field->text_field( 'ADDSUBSCRIBER', esc_attr__( 'Email', 'uncanny-automator' ), true, 'text', '', true, '' ),
								Automator()->helpers->recipe->field->text_field( 'ADDSUBSCRIBER_FIRSTNAME', esc_attr__( 'First name', 'uncanny-automator' ), true, 'text', '', false, '' ),
								Automator()->helpers->recipe->field->text_field( 'ADDSUBSCRIBER_LASTNAME', esc_attr__( 'Last name', 'uncanny-automator' ), true, 'text', '', false, '' ),
								Automator()->helpers->recipe->field->select_field( 'ADDSUBSCRIBER_STATUS', esc_attr__( 'Subscriber Status', 'uncanny-automator' ), $subscriber_status ),
								Automator()->helpers->recipe->field->text_field( 'ADDSUBSCRIBER_CONFIRMATIONEMAIL', esc_attr__( 'Add the user directly to the list - Do not send confirmation email', 'uncanny-automator' ), true, 'checkbox', '', false ),
							),
						$this->action_meta => array(
							array(
								'option_code'              => $this->action_meta,
								'label'                    => esc_attr__( 'List', 'uncanny-automator' ),
								'input_type'               => 'select',
								'supports_multiple_values' => true,
								'required'                 => true,
								'options'                  => $options,
							),
						),
					),
			)
		);
	}

	/**
	 * Validation function when the action is hit.
	 *
	 * @param string $user_id user id.
	 * @param array $action_data action data.
	 * @param string $recipe_id recipe id.
	 * @param array $args arguments.
	 */
	public function mailpoet_add_subscriber_to_list( $user_id, $action_data, $recipe_id, $args ) {

		if ( ! class_exists( '\MailPoet\API\API' ) ) {
			$error_message = 'The class \MailPoet\API\API does not exist';
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		$list_id = $action_data['meta'][ $this->action_meta ];
		// add subscriber to a list.
		$mailpoet = \MailPoet\API\API::MP( 'v1' );

		if ( isset( $action_data['meta']['ADDSUBSCRIBER'] ) && ! empty( $action_data['meta']['ADDSUBSCRIBER'] ) ) {
			$subscriber['email'] = Automator()->parse->text( $action_data['meta']['ADDSUBSCRIBER'], $recipe_id, $user_id, $args );
		}

		if ( isset( $action_data['meta']['ADDSUBSCRIBER_FIRSTNAME'] ) && ! empty( $action_data['meta']['ADDSUBSCRIBER_FIRSTNAME'] ) ) {
			$subscriber['first_name'] = Automator()->parse->text( $action_data['meta']['ADDSUBSCRIBER_FIRSTNAME'], $recipe_id, $user_id, $args );
		}

		if ( isset( $action_data['meta']['ADDSUBSCRIBER_LASTNAME'] ) && ! empty( $action_data['meta']['ADDSUBSCRIBER_LASTNAME'] ) ) {
			$subscriber['last_name'] = Automator()->parse->text( $action_data['meta']['ADDSUBSCRIBER_LASTNAME'], $recipe_id, $user_id, $args );
		}

		if ( isset( $action_data['meta']['ADDSUBSCRIBER_STATUS'] ) && ! empty( $action_data['meta']['ADDSUBSCRIBER_STATUS'] ) ) {
			$subscriber['status'] = Automator()->parse->text( $action_data['meta']['ADDSUBSCRIBER_STATUS'], $recipe_id, $user_id, $args );
		}

		$disable_confirmation_email = true;
		if ( isset( $action_data['meta']['ADDSUBSCRIBER_CONFIRMATIONEMAIL'] ) ) {
			$disable_confirmation_email = Automator()->parse->text( $action_data['meta']['ADDSUBSCRIBER_CONFIRMATIONEMAIL'], $recipe_id, $user_id, $args );
			$disable_confirmation_email = 'true' === $disable_confirmation_email ? false : true;
		}

		$options = array(
			'send_confirmation_email' => $disable_confirmation_email,
			'schedule_welcome_email'  => true,
		);

		try {
			// try to find if user is already a subscriber
			$existing_subscriber = \MailPoet\Models\Subscriber::findOne( $subscriber['email'] );
			if ( ! $existing_subscriber ) {
				$new_subscriber = $mailpoet->addSubscriber( $subscriber );
				$subscriber_id  = $new_subscriber['id'];
			} else {
				$subscriber_id = $existing_subscriber->id;
			}
			if ( false === $disable_confirmation_email ) {
				$this->update_status_manually( $subscriber['status'], $subscriber_id );
			}
			/**
			 * Adding a cron here so that the
			 * status 'subscribed' is properly delegated.
			 * Else Welcome Emails linked to the list aren't sent.
			 * @since 4.7
			 */
			$rr = wp_schedule_single_event(
				time() + 5,
				'automator_mailpoet_subscribe_to_list',
				array(
					$subscriber['email'],
					maybe_serialize( json_decode( $list_id ) ),
					maybe_serialize( $options ),
					$user_id,
					maybe_serialize( $action_data ),
					$recipe_id,
				),
				true
			);

			// Complete the action with notice while Cron is processing it.
			$action_data['complete_with_notice'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, _x( 'Waiting for the action to be completed', 'MailPoet action status', 'uncanny-automator' ) );

		} catch ( \MailPoet\API\MP\v1\APIException $e ) {
			$error_message                       = $e->getMessage();
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );
		}

	}

	/**
	 * @param $status
	 * @param $subscriber_id
	 *
	 * @return void
	 */
	public function update_status_manually( $status, $subscriber_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'mailpoet_subscribers';
		$wpdb->update( $table_name, array( 'status' => $status ), array( 'id' => $subscriber_id ) );
	}

	/**
	 * @param $email
	 * @param $list_id
	 * @param $options
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function subscribe_to_the_list( $email, $list_id, $options, $user_id, $action_data, $recipe_id ) {
		try {
			$list_id             = maybe_unserialize( $list_id );
			$options             = maybe_unserialize( $options );
			$action_data         = maybe_unserialize( $action_data );
			$mailpoet            = \MailPoet\API\API::MP( 'v1' );
			$existing_subscriber = $mailpoet->getSubscriber( $email );
			$r                   = $mailpoet->subscribeToLists( $existing_subscriber['id'], $list_id, $options );
			Automator()->complete->action( $user_id, $action_data, $recipe_id );
		} catch ( \MailPoet\API\MP\v1\APIException $e ) {
			$error_message                       = $e->getMessage();
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );
		}
	}

}
