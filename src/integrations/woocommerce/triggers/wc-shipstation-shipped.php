<?php

namespace Uncanny_Automator;

/**
 * Class WC_SHIPSTATION_SHIPPED
 *
 * @package Uncanny_Automator
 */
class WC_SHIPSTATION_SHIPPED {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WC';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		if ( function_exists( 'woocommerce_shipstation_init' ) ) {
			$this->trigger_code = 'WCSHIPSTATIONSHIPPED';
			$this->trigger_meta = 'WOOORDER';
			$this->define_trigger();
		}
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/woocommerce-shipstation/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			'trigger_meta'        => $this->trigger_code,
			/* translators: Logged-in trigger - WooCommerce */
			'sentence'            => esc_attr__( 'An order is shipped', 'uncanny-automator' ),
			/* translators: Logged-in trigger - WooCommerce */
			'select_option_name'  => esc_attr__( 'An order is shipped', 'uncanny-automator' ),
			'action'              => 'woocommerce_shipstation_shipnotify',
			'priority'            => 99,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'shipping_completed' ),
			'options'             => array(),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $order
	 * @param $argu
	 */

	public function shipping_completed( $order, $argu ) {

		if ( ! $order ) {
			return;
		}

		$user_id = $order->get_user_id();
		if ( 0 === $user_id ) {
			// Its a logged in recipe and
			// user ID is 0. Skip process
			return;
		}
		// Get real order ID from order object.
		$order_id = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->id : $order->get_id();

		$pass_args = array(
			'code'           => $this->trigger_code,
			'meta'           => $this->trigger_meta,
			'user_id'        => $user_id,
			'post_id'        => $order_id,
			'ignore_post_id' => true,
			'is_signed_in'   => $user_id,
		);

		$args = Automator()->maybe_add_trigger_entry( $pass_args, false );

		if ( $args ) {
			foreach ( $args as $result ) {
				if ( true === $result['result'] ) {
					// Add token for options
					Automator()->insert_trigger_meta(
						array(
							'user_id'        => $user_id,
							'trigger_id'     => $result['args']['trigger_id'],
							'meta_key'       => $this->trigger_meta . '_TRACKING_NUMBER',
							'meta_value'     => $argu['tracking_number'],
							'trigger_log_id' => $result['args']['get_trigger_id'],
							'run_number'     => $result['args']['run_number'],
						)
					);
					// Add token for options
					Automator()->insert_trigger_meta(
						array(
							'user_id'        => $user_id,
							'trigger_id'     => $result['args']['trigger_id'],
							'meta_key'       => $this->trigger_meta . '_CARRIER',
							'meta_value'     => $argu['carrier'],
							'trigger_log_id' => $result['args']['get_trigger_id'],
							'run_number'     => $result['args']['run_number'],
						)
					);
					// Add token for options
					Automator()->insert_trigger_meta(
						array(
							'user_id'        => $user_id,
							'trigger_id'     => $result['args']['trigger_id'],
							'meta_key'       => $this->trigger_meta . '_SHIP_DATE',
							'meta_value'     => $argu['ship_date'],
							'trigger_log_id' => $result['args']['get_trigger_id'],
							'run_number'     => $result['args']['run_number'],
						)
					);
					// Add token for options
					Automator()->insert_trigger_meta(
						array(
							'user_id'        => $user_id,
							'trigger_id'     => $result['args']['trigger_id'],
							'meta_key'       => 'order_id',
							'meta_value'     => $order_id,
							'trigger_log_id' => $result['args']['get_trigger_id'],
							'run_number'     => $result['args']['run_number'],
						)
					);

					Automator()->maybe_trigger_complete( $result['args'] );
				}
			}
		}

		return;
	}
}
