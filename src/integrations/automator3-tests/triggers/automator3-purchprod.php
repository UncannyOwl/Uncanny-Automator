<?php

namespace Uncanny_Automator;

/**
 * Class AUTOMATOR3_PURCHPROD
 * @package Uncanny_Automator
 */
class AUTOMATOR3_PURCHPROD {
	use Recipe\Triggers;

	/**
	 * @var
	 */
	private $order_id;

	/**
	 * AUTOMATOR3_PURCHPROD constructor.
	 */
	public function __construct() {
		if ( function_exists( 'WC' ) ) {
			$this->setup_trigger();
		}
	}

	/**
	 * @return mixed
	 */
	protected function setup_trigger() {
		$this->set_integration( 'AUTOMATOR3' );
		$this->set_trigger_code( 'AUTOMATOR3PURCHPROD' );
		$this->set_trigger_meta( 'WOOPRODUCT' );

		/* translators: */
		$this->set_sentence( sprintf( esc_attr__( 'A user purchases {{a product:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ) );
		$this->set_readable_sentence( esc_attr__( 'A user purchases {{a product}}', 'uncanny-automator' ) );
		$trigger_hooks = array(
			'woocommerce_order_status_completed',
			'woocommerce_thankyou',
			'woocommerce_payment_complete',
		);
		$this->add_action( $trigger_hooks, 90 );
		$wc_options            = Automator()->helpers->recipe->woocommerce->options->all_wc_products( esc_attr__( 'Product', 'uncanny-automator' ) );
		$wc_options['options'] = array( '-1' => esc_attr__( 'Any product', 'uncanny-automator' ) ) + $wc_options['options'];

		$options = array(
			Automator()->helpers->recipe->options->number_of_times(),
			$wc_options,
		);

		$this->set_options( $options );
		$tokens = array_merge( Automator3_Tokens::product_tokens(), Automator3_Tokens::order_tokens() );
		$this->set_trigger_tokens( $tokens );
		$this->set_token_parser( array( __NAMESPACE__ . '\Automator3_Tokens', 'parse_woo_tokens' ) );
		$this->register_trigger();
	}

	/**
	 * @param mixed ...$args
	 *
	 * @return bool
	 */
	protected function validate_trigger( ...$args ) {
		if ( empty( $args ) ) {
			return false;
		}

		$order_id = array_shift( $args );
		$order    = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			return false;
		}
		$this->order_id = $order->get_id();

		return true;
	}

	/**
	 * @param mixed ...$args
	 */
	protected function prepare_to_run( ...$args ) {
		$this->set_conditional_trigger( true );
	}

	/**
	 * @param mixed ...$args
	 *
	 * @return false
	 */
	protected function trigger_conditions( ...$args ) {
		$order_id = $this->order_id;
		$order    = wc_get_order( $order_id );

		if ( 'completed' !== $order->get_status() ) {
			return false;
		}

		$product_ids = Automator()->helpers->trigger->woo_order_items( $order, 'id' );
		$this->do_find_any( true );
		// Match specific condition
		$this->do_find_this( $this->get_trigger_meta() );
		$this->do_find_in( $product_ids );
	}
}
