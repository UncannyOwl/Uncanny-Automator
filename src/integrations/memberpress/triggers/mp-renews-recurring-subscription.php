<?php

namespace Uncanny_Automator;

/**
 * Class MP_RENEWS_RECURRING_SUBSCRIPTION
 *
 * @package Uncanny_Automator
 */
class MP_RENEWS_RECURRING_SUBSCRIPTION {

	use Recipe\Triggers;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->setup_trigger();
		$this->set_helper( new Memberpress_Helpers() );
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {
		$this->set_integration( 'MP' );
		$this->set_trigger_code( 'MP_RENEW_SUBSCRIPTION' );
		$this->set_trigger_meta( 'MPPRODUCT' );
		//$this->set_is_pro( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_trigger_code(), 'integration/memberpress/' ) );
		$this->set_sentence(
		/* Translators: Trigger sentence - Memberpress */
			sprintf( esc_html__( 'A user renews {{a recurring subscription product:%1$s}}', 'uncanny-automator' ), $this->get_trigger_meta() )
		);
		// Non-active state sentence to show
		$this->set_readable_sentence( esc_attr__( 'A user renews {{a recurring subscription product}}', 'uncanny-automator' ) );
		// Which do_action() fires this trigger.
		$this->add_action( 'mepr-event-renewal-transaction-completed', 999, 1 );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->register_trigger();

	}

	/**
	 * callback load_options
	 *
	 * @return array
	 */
	public function load_options() {
		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					$this->get_helper()->all_memberpress_products_recurring( null, $this->get_trigger_meta(), array( 'uo_include_any' => true ) ),
				),
			)
		);
	}

	/**
	 * Validate the trigger.
	 *
	 * @param $args
	 *
	 * @return bool
	 */
	protected function validate_trigger( ...$args ) {
		list( $event ) = array_shift( $args );

		/** @var \MeprTransaction $transaction */
		$transaction = $event->get_data();
		/** @var \MeprProduct $product */
		$product                   = $transaction->product();
		$subscription              = $transaction->subscription();
		$is_not_first_real_payment = apply_filters(
			'automator_mepr_renewal_completed_is_not_first_real_payment',
			Automator()->helpers->recipe->memberpress->check_if_is_renewal_or_first_payment( $subscription ),
			$event
		);

		if ( 'lifetime' !== (string) $product->period_type && false === $is_not_first_real_payment ) {
			return true;
		}

		return false;
	}

	/**
	 * Prepare to run the trigger.
	 *
	 * @param $data
	 *
	 * @return void
	 */
	public function prepare_to_run( $data ) {
		$this->set_conditional_trigger( true );
	}

	/**
	 * Check product_id against the trigger meta
	 *
	 * @param $args
	 */
	public function validate_conditions( ...$args ) {
		list( $event ) = $args[0];
		/** @var \MeprTransaction $transaction */
		$transaction = $event->get_data();

		return $this->find_all( $this->trigger_recipes() )
					->where( array( $this->get_trigger_meta() ) )
					->match( array( absint( $transaction->rec->product_id ) ) )
					->format( array( 'intval' ) )
					->get();

	}
}
