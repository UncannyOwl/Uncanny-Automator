<?php

namespace Uncanny_Automator;

use TEC\Tickets\Commerce\Module;
use TEC\Tickets\Commerce\Order;
use WP_Post;

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
	 * Define and register the trigger by pushing it into the Automator object
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
			'action'              => array(
				'event_tickets_rsvp_tickets_generated_for_product',
				'event_tickets_woocommerce_tickets_generated_for_product',
				'event_tickets_tpp_tickets_generated_for_product',
			),
			'priority'            => 10,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'user_registered' ),
			'options'             => array(
				Automator()->helpers->recipe->event_tickets->options->all_ec_events(),
			),
		);

		Automator()->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $order_id
	 */
	public function user_registered( $product_id, $order_id, $qty ) {

		if ( ! $order_id ) {
			return;
		}

		$event    = tribe_events_get_ticket_event( $product_id );
		$event_id = ( $event instanceof WP_Post ) ? $event->ID : false;
		$user_id  = get_current_user_id();

		$pass_args = array(
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => $event_id,
			'user_id' => $user_id,
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

					$trigger_meta['meta_key']   = 'ec_order_id';
					$trigger_meta['meta_value'] = $order_id;
					Automator()->insert_trigger_meta( $trigger_meta );

					Automator()->maybe_trigger_complete( $result['args'] );
				}
			}
		}
	}
}
