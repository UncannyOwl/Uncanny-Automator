<?php

namespace Uncanny_Automator;

/**
 * Class EC_REGISTER
 *
 * @package Uncanny_Automator
 */
class EC_REGISTER {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'EC';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'USERREGISTERED';
		$this->trigger_meta = 'ECEVENTS';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object.
	 *
	 * Listens to the normalized internal action fired by Event_Tickets_Helpers
	 * which bridges all ticket providers (RSVP, WooCommerce, PayPal, Tickets Commerce).
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/the-events-calendar/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - The Events Calendar */
			'sentence'            => sprintf( esc_attr__( 'A user registers for {{an event:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - The Events Calendar */
			'select_option_name'  => esc_attr__( 'A user registers for {{an event}}', 'uncanny-automator' ),
			'action'              => Event_Tickets_Helpers::USER_REGISTERED_ACTION,
			'priority'            => 10,
			'accepted_args'       => 4,
			'validation_function' => array( $this, 'user_registered' ),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * @return array[]
	 */
	public function load_options() {
		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					Automator()->helpers->recipe->event_tickets->options->all_ec_events(),
				),
			)
		);
	}

	/**
	 * Validation function when the normalized trigger action is hit.
	 *
	 * @param int        $event_id   The event post ID.
	 * @param int        $product_id The ticket product ID.
	 * @param int|string $order_id   The order ID.
	 * @param int        $user_id    The user ID.
	 */
	public function user_registered( $event_id, $product_id, $order_id, $user_id ) {

		if ( ! $order_id ) {
			return;
		}

		$pass_args = array(
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => absint( $event_id ),
			'user_id' => absint( $user_id ),
		);

		$args = Automator()->maybe_add_trigger_entry( $pass_args, false );

		if ( $args ) {
			foreach ( $args as $result ) {
				if ( true === $result['result'] ) {

					$trigger_meta = array(
						'user_id'        => absint( $user_id ),
						'trigger_id'     => $result['args']['trigger_id'],
						'trigger_log_id' => $result['args']['get_trigger_id'],
						'run_number'     => $result['args']['run_number'],
					);

					$trigger_meta['meta_key']   = 'ec_order_id';
					$trigger_meta['meta_value'] = $order_id;
					Automator()->insert_trigger_meta( $trigger_meta );

					Automator()->maybe_trigger_complete( $result['args'] );
				}
			}
		}
	}
}
