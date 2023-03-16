<?php

namespace Uncanny_Automator;

/**
 * Class EDD_ANON_PRODUCT_PURCHASE
 *
 * @package Uncanny_Automator
 */
class EDD_ANON_PRODUCT_PURCHASE {
	use Recipe\Triggers;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->setup_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {
		$this->set_helper( new Edd_Helpers() );
		$this->set_integration( 'EDD' );
		$this->set_trigger_code( 'EDD_ANON_PURCHASE' );
		$this->set_trigger_meta( 'EDD_PRODUCTS' );
		$this->set_trigger_type( 'anonymous' );
		$this->set_is_login_required( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->trigger_code, 'integration/easy-digital-downloads/' ) );
		$this->set_sentence(
		/* Translators: Trigger sentence - Easy Digital Downloads */
			sprintf( esc_attr__( 'A customer purchases {{a download:%1$s}}', 'uncanny-automator' ), $this->get_trigger_meta() )
		);
		// Non-active state sentence to show
		$this->set_readable_sentence( esc_attr__( 'A customer purchases {{a download}}', 'uncanny-automator' ) );
		// Which do_action() fires this trigger.
		$this->add_action( 'edd_complete_purchase' );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->register_trigger();

	}

	/**
	 * @return array[]
	 */
	public function load_options() {
		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					$this->get_helper()->all_edd_downloads( esc_attr__( 'Download', 'uncanny-automator' ), $this->get_trigger_meta(), true, false ),
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
		$payment_id = array_shift( $args );
		$cart_items = edd_get_payment_meta_cart_details( $payment_id[0] );

		if ( ! class_exists( '\EDD_Payment' ) ) {
			return false;
		}

		if ( empty( $cart_items ) ) {
			return false;
		}

		return true;
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
	 * Check item id against the trigger meta
	 *
	 * @param $args
	 */
	public function validate_conditions( ...$args ) {
		$payment_id = $args[0];
		$cart_items = edd_get_payment_meta_cart_details( $payment_id[0] );
		foreach ( $cart_items as $item ) {
			// Find item ID
			$result = $this->find_all( $this->trigger_recipes() )
						   ->where( array( $this->get_trigger_meta() ) )
						   ->match( array( $item['id'] ) )
						   ->format( array( 'intval' ) )
						   ->get();

			if ( ! empty( $result ) ) {
				return $result;
			}
		}
	}

	/**
	 * @param ...$args
	 *
	 * @return bool
	 */
	public function do_continue_anon_trigger( ...$args ) {
		return true;
	}

}
