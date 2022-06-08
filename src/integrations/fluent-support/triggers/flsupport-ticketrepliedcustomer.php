<?php

namespace Uncanny_Automator;

/**
 * Class FLSUPPORT_TICKETREPLIEDCUSTOMER
 *
 * @package Uncanny_Automator
 */
class FLSUPPORT_TICKETREPLIEDCUSTOMER {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'FLSUPPORT';

	/**
	 * @var string
	 */
	private $trigger_code;
	/**
	 * @var string
	 */
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'FLSTTICKETREPLIEDCUST';
		$this->trigger_meta = 'FLSTTICKETREPLIEDCUST_META';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/fluent-support/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Fluent Support */
			'sentence'            => esc_attr__( 'A ticket receives a reply from a customer', 'uncanny-automator' ),
			/* translators: Logged-in trigger - Fluent Support */
			'select_option_name'  => esc_attr__( 'A ticket receives a reply from a customer', 'uncanny-automator' ),
			'action'              => 'fluent_support/response_added_by_customer',
			'priority'            => 20,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'fsupport_ticket_customer_replied' ),
			'options'             => array(),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $response
	 * @param $ticket
	 * @param $person
	 */
	public function fsupport_ticket_customer_replied( $response, $ticket, $person ) {

		$user_id = get_current_user_id();

		// Logged in users only.
		if ( ! $user_id ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( ! strstr( $request_uri, '/customer-portal/tickets' ) ) {
			return; // Response not added by customer.
		}

		if ( ! is_object( $person ) || ! is_object( $ticket ) ) {
			return;
		}

		$pass_args = array(
			'code'           => $this->trigger_code,
			'meta'           => $this->trigger_meta,
			'user_id'        => $user_id,
			'ignore_post_id' => true,
			'is_signed_in'   => true,
		);

		$args = Automator()->maybe_add_trigger_entry( $pass_args, false );

		if ( $args ) {
			foreach ( $args as $result ) {
				if ( true === $result['result'] ) {

					$trigger_meta = array(
						'user_id'        => $user_id,
						'trigger_id'     => $result['args']['trigger_id'],
						'trigger_log_id' => $result['args']['get_trigger_id'],
						'run_number'     => $result['args']['run_number'],
					);

					$trigger_meta['meta_key']   = 'FLSUPPORTTICKETID';
					$trigger_meta['meta_value'] = $ticket->id;
					Automator()->insert_trigger_meta( $trigger_meta );

					$trigger_meta['meta_key']   = 'FLSUPPORTPERSONID';
					$trigger_meta['meta_value'] = $person->id;
					Automator()->insert_trigger_meta( $trigger_meta );

					$trigger_meta['meta_key']   = 'FLSUPPORTRESPONSEID';
					$trigger_meta['meta_value'] = $response->id;
					Automator()->insert_trigger_meta( $trigger_meta );

					Automator()->maybe_trigger_complete( $result['args'] );
					break;
				}
			}
		}
	}
}
