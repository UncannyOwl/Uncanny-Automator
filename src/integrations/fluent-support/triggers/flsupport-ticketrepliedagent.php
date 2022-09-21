<?php

namespace Uncanny_Automator;

/**
 * Class FLSUPPORT_TICKETREPLIEDAGENT
 *
 * @package Uncanny_Automator
 */
class FLSUPPORT_TICKETREPLIEDAGENT {

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
		$this->trigger_code = 'FLSTTICKETREPLIEDAGENT';
		$this->trigger_meta = 'FLSTTICKETREPLIEDAGENT_META';
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
			'sentence'            => esc_attr__( "A user's ticket receives a reply from an agent", 'uncanny-automator' ),
			/* translators: Logged-in trigger - Fluent Support */
			'select_option_name'  => esc_attr__( "A user's ticket receives a reply from an agent", 'uncanny-automator' ),
			'action'              => 'fluent_support/response_added_by_agent',
			'priority'            => 20,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'fsupport_ticket_agent_replied' ),
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
	public function fsupport_ticket_agent_replied( $response, $ticket, $person ) {

		$user_id = get_current_user_id();

		// Logged in users only.
		if ( ! $user_id ) {
			return;
		}

		if ( ! is_object( $person ) || ! is_object( $ticket ) || ! is_object( $response ) ) {
			return;
		}

		$customer = \FluentSupport\App\Models\Person::where( 'id', $ticket->customer_id )->first();
		if ( ! is_object( $customer ) ) {
			return;
		}

		if ( 0 === intval( $customer->user_id ) ) {
			returm;
		}

		$trigger_user_id = $customer->user_id;

		$pass_args = array(
			'code'           => $this->trigger_code,
			'meta'           => $this->trigger_meta,
			'user_id'        => $trigger_user_id,
			'ignore_post_id' => true,
			'is_signed_in'   => true,
		);

		$args = Automator()->maybe_add_trigger_entry( $pass_args, false );

		if ( $args ) {
			foreach ( $args as $result ) {
				if ( true === $result['result'] ) {

					$trigger_meta = array(
						'user_id'        => $trigger_user_id,
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
				}
			}
		}
	}
}
