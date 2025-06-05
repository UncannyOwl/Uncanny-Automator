<?php

namespace Uncanny_Automator\Integrations\Mailster;

/**
 * Class MAILSTER_SUBSCRIBER_ADDED_TO_LIST
 * @package Uncanny_Automator
 */
class MAILSTER_SUBSCRIBER_ADDED_TO_LIST extends \Uncanny_Automator\Recipe\Trigger {

	protected $helper;

	/**
	 * @return mixed|void
	 */
	protected function setup_trigger() {
		$this->helpers = array_shift( $this->dependencies );
		$this->set_integration( 'MAILSTER' );
		$this->set_trigger_code( 'SUBSCRIBER_ADDED_TO_LIST' );
		$this->set_trigger_meta( 'MAILSTER_LISTS' );
		$this->set_trigger_type( 'anonymous' );
		// translators: Mailster - Trigger
		$this->set_sentence( sprintf( esc_attr_x( 'A new subscriber is added to {{a Mailster list:%1$s}}', 'Mailster', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'A new subscriber is added to {{a Mailster list}}', 'Mailster', 'uncanny-automator' ) );
		$this->add_action( 'mailster_list_added', 10, 3 );
	}

	/**
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'input_type'      => 'select',
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'List', 'Mailster', 'uncanny-automator' ),
				'required'        => true,
				'options'         => $this->helpers->get_all_mailster_lists( true ),
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		if ( ! isset( $hook_args[0], $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return false;
		}

		$selected_list = $trigger['meta'][ $this->get_trigger_meta() ];

		return ( intval( '-1' ) === intval( $selected_list ) || absint( $hook_args[0] ) === absint( $selected_list ) );
	}

	/**
	 * define_tokens
	 *
	 * @param mixed $tokens
	 * @param mixed $trigger - options selected in the current recipe/trigger
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		$trigger_tokens = array(
			array(
				'tokenId'   => 'MAILSTER_LIST_ID',
				'tokenName' => esc_html_x( 'List ID', 'Mailster', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MAILSTER_LIST_TITLE',
				'tokenName' => esc_html_x( 'List title', 'Mailster', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MAILSTER_SUBSCRIBER_EMAIL',
				'tokenName' => esc_html_x( 'Subscriber email', 'Mailster', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'MAILSTER_SUBSCRIBER_STATUS',
				'tokenName' => esc_html_x( 'Subscriber status', 'Mailster', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);

		return array_merge( $trigger_tokens, $tokens );
	}

	/**
	 * hydrate_tokens
	 *
	 * @param $completed_trigger
	 * @param $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $completed_trigger, $hook_args ) {
		list( $list_id, $subscriber_id ) = $hook_args;
		$subscriber                      = mailster( 'subscribers' )->get( $subscriber_id );
		$lists                           = mailster( 'subscribers' )->get_lists( $subscriber_id );
		$status                          = mailster( 'subscribers' )->get_status( $subscriber->status, true );
		$list_name                       = '';

		foreach ( $lists as $list ) {
			if ( absint( $list->ID ) === absint( $list_id ) ) {
				$list_name = $list->name;
			}
		}

		return array(
			'MAILSTER_LIST_ID'           => $list_id,
			'MAILSTER_LIST_TITLE'        => $list_name,
			'MAILSTER_SUBSCRIBER_EMAIL'  => $subscriber->email,
			'MAILSTER_SUBSCRIBER_STATUS' => $status,
		);
	}
}
