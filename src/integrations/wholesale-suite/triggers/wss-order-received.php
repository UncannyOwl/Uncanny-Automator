<?php

namespace Uncanny_Automator;

/**
 * Class WSS_ORDER_RECEIVED
 *
 * @package Uncanny_Automator
 */
class WSS_ORDER_RECEIVED {

	use Recipe\Triggers;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->setup_trigger();
		$this->set_helper( new Wholesale_Suite_Helpers() );
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {
		$this->set_integration( 'WHOLESALESUITE' );
		$this->set_trigger_code( 'WSS_ORDER_RECEIVED' );
		$this->set_trigger_meta( 'WSS_CUSTOMER_ROLE' );
		$this->set_support_link( Automator()->get_author_support_link( $this->trigger_code, 'integration/wholesale-suite/' ) );
		$this->set_sentence(
		/* Translators: Trigger sentence */
			sprintf( esc_html__( 'A wholesale order is received from a user with {{a specific role:%1$s}}', 'uncanny-automator' ), $this->get_trigger_meta() )
		);
		// Non-active state sentence to show
		$this->set_readable_sentence( esc_attr__( 'A wholesale order is received from a user with {{a specific role}}', 'uncanny-automator' ) );
		// Which do_action() fires this trigger.
		$this->set_action_hook( '_wwp_add_order_meta' );
		$this->set_action_args_count( 3 );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->register_trigger();

	}

	/**
	 * @return array
	 */
	public function load_options() {
		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					$this->get_helper()->get_all_wss_roles( null, $this->get_trigger_meta(), true ),
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
		list( $order_id, $posted_data, $user_wholesale_role ) = array_shift( $args );

		if ( empty( $order_id ) ) {
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
	 * Check contact status against the trigger meta
	 *
	 * @param $args
	 */
	public function validate_conditions( ...$args ) {
		list( $order_id, $posted_data, $user_wholesale_role ) = $args[0];
		$this->actual_where_values                            = array(); // Fix for when not using the latest Trigger_Recipe_Filters version. Newer integration can omit this line.
		// Get user role.
		$role = $user_wholesale_role[0];

		return $this->find_all( $this->trigger_recipes() )
					->where( array( $this->get_trigger_meta() ) )
					->match( array( $role ) )
					->format( array( 'sanitize_text_field' ) )
					->get();
	}

}
