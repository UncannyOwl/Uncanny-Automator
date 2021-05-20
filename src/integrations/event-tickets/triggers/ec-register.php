<?php

namespace Uncanny_Automator;

use WP_Post;

/**
 * Class EC_REGISTER
 * @package Uncanny_Automator
 */
class EC_REGISTER {

	/**
	 * Integration code
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
			'action'              => [
				'event_tickets_rsvp_tickets_generated_for_product',
				'event_tickets_woocommerce_tickets_generated_for_product',
				'event_tickets_tpp_tickets_generated_for_product',
			],
			'priority'            => 10,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'user_registered' ),
			'options'             => [
				Automator()->helpers->recipe->event_tickets->options->all_ec_events(),
			],
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

		$user_id = get_current_user_id();

		$args = [
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => $event_id,
			'user_id' => $user_id,
		];

		Automator()->maybe_add_trigger_entry( $args );
	}
}
