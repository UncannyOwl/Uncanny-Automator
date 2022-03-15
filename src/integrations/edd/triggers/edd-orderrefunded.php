<?php

namespace Uncanny_Automator;

/**
 * Class EDD_ORDERREFUNDED
 *
 * @package Uncanny_Automator
 */
class EDD_ORDERREFUNDED {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'EDD';

	private $trigger_code;
	private $trigger_meta;

	public function __construct() {
		$this->trigger_code = 'EDDORDERREFUND';
		$this->trigger_meta = 'EDDORDERREFUNDED';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object.
	 *
	 * @return void.
	 */
	public function define_trigger() {
		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/easy-digital-downloads/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Easy Digital Downloads */
			'sentence'            => sprintf( esc_attr__( "A user's Stripe payment is refunded", 'uncanny-automator' ) ),
			/* translators: Logged-in trigger - Easy Digital Downloads */
			'select_option_name'  => esc_attr__( "A user's Stripe payment is refunded", 'uncanny-automator' ),
			'action'              => 'edds_payment_refunded',
			'priority'            => 10,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'edd_order_refunded' ),
			'options'             => array(),
		);
		Automator()->register->trigger( $trigger );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $order_id
	 * @param $refund_id
	 * @param $all_refunded
	 */
	public function edd_order_refunded( $order_id ) {

		$order_detail   = edd_get_payment( $order_id );
		$total_discount = 0;

		if ( empty( $order_detail ) ) {
			return;
		}

		$post_id    = 0;
		$payment_id = $order_detail->ID;
		$user_id    = edd_get_payment_user_id( $payment_id );

		if ( ! $user_id ) {
			$user_id = wp_get_current_user()->ID;
		}

		$pass_args = array(
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => $post_id,
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

					$item_names = array();

					$order_items = edd_get_payment_meta_cart_details( $order_id );

					foreach ( $order_items as $item ) {
						$item_names[] = $item['name'];
						// Sum the discount.
						if ( is_numeric( $item['discount'] ) ) {
							$total_discount += $item['discount'];
						}
					}

					// Save the payment order info.
					$payment_info = array(
						'discount_codes'  => $order_detail->discounts,
						'order_discounts' => $total_discount,
						'order_subtotal'  => $order_detail->subtotal,
						'order_total'     => $order_detail->total,
						'order_tax'       => $order_detail->tax,
						'payment_method'  => $order_detail->gateway,
						'license_key'     => Automator()->helpers->recipe->edd->options->get_licenses( $payment_id ),
					);

					$trigger_meta['meta_key']   = 'EDD_DOWNLOAD_ORDER_PAYMENT_INFO';
					$trigger_meta['meta_value'] = maybe_serialize( wp_json_encode( $payment_info ) );
					Automator()->insert_trigger_meta( $trigger_meta );

					$trigger_meta['meta_key']   = 'EDDORDER_ITEMS';
					$trigger_meta['meta_value'] = maybe_serialize( implode( ',', $item_names ) );
					Automator()->insert_trigger_meta( $trigger_meta );

					$trigger_meta['meta_key']   = 'EDDCUSTOMER_EMAIL';
					$trigger_meta['meta_value'] = maybe_serialize( $order_detail->email );
					Automator()->insert_trigger_meta( $trigger_meta );

					$trigger_meta['meta_key']   = 'EDDORDER_SUBTOTAL';
					$trigger_meta['meta_value'] = maybe_serialize( $order_detail->subtotal );
					Automator()->insert_trigger_meta( $trigger_meta );

					$trigger_meta['meta_key']   = 'EDDORDER_TOTAL';
					$trigger_meta['meta_value'] = maybe_serialize( $order_detail->total );
					Automator()->insert_trigger_meta( $trigger_meta );

					$trigger_meta['meta_key']   = 'EDDORDER_ID';
					$trigger_meta['meta_value'] = maybe_serialize( $order_id );
					Automator()->insert_trigger_meta( $trigger_meta );

					Automator()->maybe_trigger_complete( $result['args'] );
				}
			}
		}
	}
}
