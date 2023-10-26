<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Recipe;

/**
 * Class SURECART_ORDER_CONFIRMED
 *
 * @package Uncanny_Automator
 */
class SURECART_ORDER_CONFIRMED {

	use Recipe\Triggers;

	/**
	 * @var SureCart_Tokens
	 */
	public $surecart_tokens;

	/**
	 * @var SureCart_Helpers
	 */
	public $helpers;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {

		$this->helpers         = new SureCart_Helpers();
		$this->surecart_tokens = new SureCart_Tokens();
		$this->setup_trigger();
		//$this->register_trigger();

	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {

		$this->set_integration( 'SURECART' );
		$this->set_trigger_code( 'ORDER_CONFIRMED' );
		$this->set_support_link( $this->helpers->support_link( $this->trigger_code ) );

		/* Translators: Product name */
		$this->set_sentence( "A user's order status is changed to confirmed" );

		$this->set_readable_sentence( "A user's order status is changed to confirmed" );

		$this->add_action( 'surecart/checkout_confirmed' );

		$this->set_action_args_count( 2 );

		if ( method_exists( $this, 'set_tokens' ) ) {
			$this->set_tokens(
				$this->surecart_tokens->common_tokens() +
				$this->surecart_tokens->order_tokens()
			);
		}
	}

	/**
	 *  Validation function when the trigger action is hit
	 *
	 * @param $data
	 */
	public function validate_trigger( ...$args ) {

		list( $checkout ) = $args[0];

		if ( 'paid' === $checkout->status ) {
			return true;
		}

		return false;
	}

	/**
	 * Method prepare_to_run
	 *
	 * @param $data
	 */
	public function prepare_to_run( $data ) {
	}


	/**
	 * Method parse_additional_tokens.
	 *
	 * @param $parsed
	 * @param $args
	 * @param $trigger
	 *
	 * @return array
	 */
	public function parse_additional_tokens( $parsed, $args, $trigger ) {

		return $this->surecart_tokens->hydrate_order_tokens( $parsed, $args, $trigger );

	}

}
