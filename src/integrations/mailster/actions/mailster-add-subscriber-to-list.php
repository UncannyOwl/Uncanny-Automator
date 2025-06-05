<?php

namespace Uncanny_Automator\Integrations\Mailster;

/**
 * Class MAILSTER_ADD_SUBSCRIBER_TO_LIST
 * @package Uncanny_Automator
 */
class MAILSTER_ADD_SUBSCRIBER_TO_LIST extends \Uncanny_Automator\Recipe\Action {

	protected $helpers;

	/**
	 * @return mixed
	 */
	protected function setup_action() {
		$this->helpers = array_shift( $this->dependencies );
		$this->set_integration( 'MAILSTER' );
		$this->set_action_code( 'SUBSCRIBER_ADDED_TO_LIST' );
		$this->set_action_meta( 'MAILSTER_SUBSCRIBER_LIST' );
		$this->set_requires_user( false );
		// translators: Mailster - Add subscriber to list
		$this->set_sentence( sprintf( esc_attr_x( 'Add a subscriber to {{a Mailster list:%1$s}}', 'Mailster', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'Add a subscriber to {{a Mailster list}}', 'Mailster', 'uncanny-automator' ) );
	}

	/**
	 * @return array[]
	 */
	public function define_tokens() {
		return array(
			'MAILSTER_SUBSCRIBER_LIST' => array(
				'name' => esc_html_x( 'List', 'Mailster', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'input_type'     => 'email',
				'option_code'    => 'MAILSTER_SUBSCRIBER_EMAIL',
				'required'       => true,
				'supports_token' => true,
				'label'          => esc_html_x( 'Subscriber email', 'Mailster', 'uncanny-automator' ),
			),
			array(
				'input_type'            => 'select',
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_html_x( 'List', 'Mailster', 'uncanny-automator' ),
				'required'              => true,
				'options'               => $this->helpers->get_all_mailster_lists(),
				'supports_custom_value' => true,
				'relevant_tokens'       => array(),
			),
		);
	}

	/**
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return mixed
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$email   = isset( $parsed['MAILSTER_SUBSCRIBER_EMAIL'] ) ? sanitize_email( $parsed['MAILSTER_SUBSCRIBER_EMAIL'] ) : '';
		$list_id = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : '';
		$list    = mailster( 'lists' )->get( $list_id );
		if ( empty( $list ) ) {
			// translators: %s is a Mailster list ID
			$this->add_log_error( sprintf( esc_attr_x( 'The list(%s) does not exists.', 'Mailster', 'uncanny-automator' ), $list_id ) );

			return false;
		}

		if ( ! mailster_is_email( $email ) ) {
			$this->add_log_error( esc_attr_x( 'The email is invalid', 'Mailster', 'uncanny-automator' ) );

			return false;
		}

		$list_ids   = array( $list_id );
		$subscriber = mailster_get_subscriber( $email, 'email' );

		if ( is_object( $subscriber ) ) {
			$lists = mailster( 'subscribers' )->get_lists( $subscriber->ID );
			if ( ! empty( $lists ) ) {
				foreach ( $lists as $list ) {
					if ( absint( $list->ID ) === absint( $list_id ) ) {
						// translators: %1s is a subscriber email & %2s is a Mailster list name
						$this->add_log_error( sprintf( esc_attr_x( 'The subscriber (%1$s) is already subscribed to the list (%2$s).', 'Mailster', 'uncanny-automator' ), $subscriber->email, $list->name ) );

						return false;
					}

					$list_ids[] = $list->ID;
				}
			}
		}

		$this->hydrate_tokens( array( 'MAILSTER_SUBSCRIBER_LIST' => $list->name ) );
		mailster_subscribe( $email, array(), $list_ids );

		return true;
	}
}
