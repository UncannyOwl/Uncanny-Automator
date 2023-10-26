<?php

namespace Uncanny_Automator;

/**
 * Class FLSUPPORT_ANON_TICKET_OPENED
 *
 * @package Uncanny_Automator
 */
class FLSUPPORT_ANON_TICKET_OPENED extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * @return mixed|void
	 */
	protected function setup_trigger() {
		$this->set_integration( 'FLSUPPORT' );
		$this->set_trigger_code( 'FLST_TICKET_OPENED' );
		$this->set_trigger_meta( 'TICKET_OPENED_META' );
		$this->set_trigger_type( 'anonymous' );
		// Trigger sentence - Fluent Support
		$this->set_sentence( esc_attr_x( 'A ticket is opened', 'Fluent Support', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_attr_x( 'A ticket is opened', 'Fluent Support', 'uncanny-automator' ) );
		$this->add_action( 'fluent_support/ticket_created', 22, 2 );
	}

	/**
	 * @param $trigger
	 * @param $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		return ! ( ! is_object( $hook_args[0] ) || ! is_object( $hook_args[1] ) );
	}

	/**
	 * Define Tokens.
	 *
	 * @param array $tokens
	 * @param array $trigger - options selected in the current recipe/trigger
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		$trigger_tokens = array(
			array(
				'tokenId'   => 'TICKET_ID',
				'tokenName' => __( 'Ticket ID', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'TICKET_TITLE',
				'tokenName' => __( 'Ticket subject', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'TICKET_CONTENT',
				'tokenName' => __( 'Ticket details', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'TICKET_PRIORITY',
				'tokenName' => __( 'Ticket priority', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'TICKET_PRODUCT_TITLE',
				'tokenName' => __( 'Ticket product', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'TICKET_ADMIN_URL',
				'tokenName' => __( 'Ticket admin URL', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CUSTOMER_EMAIL',
				'tokenName' => __( 'Customer email', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);

		return array_merge( $tokens, $trigger_tokens );
	}

	/**
	 * Hydrate Tokens.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		list( $ticket, $person ) = $hook_args;

		$token_values = array(
			'TICKET_ID'            => $ticket->id,
			'TICKET_TITLE'         => $ticket->title,
			'TICKET_CONTENT'       => $ticket->content,
			'TICKET_PRIORITY'      => $ticket->priority,
			'TICKET_PRODUCT_TITLE' => $ticket->product->title,
			'TICKET_ADMIN_URL'     => admin_url( "admin.php?page=fluent-support#/tickets/{$ticket->id}/view" ),
			'CUSTOMER_EMAIL'       => $person->email,
		);

		return $token_values;
	}
}
