<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Recipe\Tokens;

/**
 * Class Automator3_Tokens
 * @package Uncanny_Automator
 */
class Automator3_Tokens {
	use Tokens;

	/**
	 * @var string
	 */
	public static $integration = 'AUTOMATOR3';

	/**
	 * Automator3_Tokens constructor.
	 */
	public function __construct() {
		if ( function_exists( 'WC' ) ) {
			add_action( 'automator_before_trigger_completed', array( __CLASS__, 'save_woo_meta' ), 10, 2 );
		}
	}

	/**
	 * @param $args
	 * @param $object
	 */
	public static function save_woo_meta( $args, $object ) {
		$order_id = absint( $args['trigger_args'] );
		$order    = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$trigger_entry = $args['trigger_entry'];
		Automator()->db->trigger->add_token_meta( 'order_id', $order_id, $trigger_entry );
	}

	/**
	 * @param $return
	 * @param $args
	 */
	public static function parse_woo_tokens( $return, $args ) {
		$replace_args = $args['replace_args'];

		$order_id = Automator()->db->trigger->get_token_meta( 'order_id', $replace_args );
		if ( empty( $order_id ) ) {
			return $return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return $return;
		}

		$pieces = $replace_args['pieces'];

		if ( ! array( $pieces ) ) {
			return $return;
		}

		$trigger_meta = $pieces[1];

		if ( 'WOOPRODUCT' === $trigger_meta ) {
			return self::parse_product_token( $pieces, $order );
		}
		if ( 'WOOORDER' === $trigger_meta ) {
			return self::parse_order_token( $pieces, $order );
		}

	}

	/**
	 * @param $pieces
	 * @param $order
	 *
	 * @return string
	 */
	public static function parse_product_token( $pieces, $order ) {
		$token    = $pieces[2];
		$items    = $order->get_items();
		$products = array();
		/** @var \WC_Order_Item_Product $item */
		foreach ( $items as $item ) {
			$product = $item->get_product();
			$func    = self::reverse_token_id( $token );
			if ( $product->$func() ) {

				$products[] = $product->$func();
			}
		}

		return join( ',', $products );
	}

	/**
	 * @param $pieces
	 * @param $order
	 *
	 * @return mixed|string
	 */
	public static function parse_order_token( $pieces, $order ) {
		$token = $pieces[2];
		$value = '';
		$func  = self::reverse_token_id( $token );
		if ( $order->$func() ) {
			$value = $order->$func();
			if ( is_array( $value ) ) {
				return join( ',', $value );
			}

			return $value;
		}

		return $value;
	}


	/**
	 * @param string $trigger_code
	 * @param mixed $data
	 * @param string $prefix
	 * @param string $parse_from
	 *
	 * @return mixed|void
	 */
	public static function woo_build( string $trigger_code, $data, string $prefix = '', string $parse_from = '' ) {
		$tokens = array();
		foreach ( $data as $token_id => $token_value ) {
			if ( is_array( $token_value ) ) {
				foreach ( $token_value as $t_id => $t_value ) {
					$arr      = array(
						'trigger_code' => $trigger_code,
						'token_id'     => "{$token_id}_{$t_id}",
						'type'         => 'text',
						'prefix'       => $prefix,
						//'parse_from'   => $parse_from,
					);
					$tokens[] = self::build( $arr );
				}
			} else {
				$arr      = array(
					'trigger_code' => $trigger_code,
					'token_id'     => $token_id,
					'type'         => 'text',
					'prefix'       => $prefix,
					//'parse_from'   => $parse_from,
				);
				$tokens[] = self::build( $arr );
			}
		}

		return apply_filters( 'automator_trigger_' . self::$integration . '_' . $trigger_code . '_tokens', $tokens, $trigger_code, $data );
	}

	public static function single_token() {
		$arr = array(
			'trigger_code' => 'SAMPLETOKEN',
			'token_id'     => 'date_time',
			'type'         => 'text',
		);

		return array( self::build( $arr ) );
	}

	public static function parse_single_token( $return, $args ) {
		$replace_args = $args['replace_args'];

		$pieces = $replace_args['pieces'];

		if ( ! array( $pieces ) ) {
			return $return;
		}

		$trigger_meta = $pieces[1];

		if ( 'SAMPLETOKEN' === $trigger_meta ) {
			return gmdate( 'r' );
		}
		return $return;
	}

	/**
	 * @param int $product_id
	 * @param string $trigger_code
	 *
	 * @return array
	 */
	public static function product_tokens( $product_id = 0, $trigger_code = 'WOOPRODUCT' ): array {
		if ( ! class_exists( 'WC_Product' ) ) {
			return array();
		}
		$product = new \WC_Product( $product_id );
		if ( ! $product instanceof \WC_Product ) {
			return array();
		}

		$data = $product->get_data();
		if ( empty( $data ) ) {
			return array();
		}

		return self::woo_build( $trigger_code, $data, __( 'Product', 'uncanny-automator' ), '\WC_Product' );
	}


	/**
	 * @param int $order_id
	 * @param string $trigger_code
	 *
	 * @return array
	 */
	public static function order_tokens( $order_id = 0, $trigger_code = 'WOOORDER' ): array {
		if ( ! class_exists( 'WC_Order' ) ) {
			return array();
		}
		$order = new \WC_Order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return array();
		}

		$data = $order->get_data();
		if ( empty( $data ) ) {
			return array();
		}

		return self::woo_build( $trigger_code, $data, __( 'Order', 'uncanny-automator' ), '\WC_Order' );
	}

	/**
	 *
	 */
	public static function review_tokens() {

	}


	/**
	 * @param int $subscription_id
	 * @param string $trigger_code
	 *
	 * @return array
	 */
	public static function subscription_tokens( $subscription_id = 0, $trigger_code = 'WOOSUBSCRIPTION' ): array {
		if ( ! class_exists( 'WC_Subscription' ) ) {
			return array();
		}
		$subscription = new \WC_Subscription( $subscription_id );
		if ( ! $subscription instanceof \WC_Subscription ) {
			return array();
		}

		$data = $subscription->get_data();
		if ( empty( $data ) ) {
			return array();
		}

		return self::woo_build( $trigger_code, $data, __( 'Subscription', 'uncanny-automator' ) );
	}


	/**
	 * @param int $product_id
	 * @param string $trigger_code
	 *
	 * @return array
	 */
	public static function subscription_product_tokens( $product_id = 0, $trigger_code = 'WOOSUBSCRIPTIONPRODUCT' ): array {
		$metas = array(
			'subscription_price',
			'subscription_period',
			'subscription_period_interval',
			'subscription_length',
			'subscription_trial_length',
			'subscription_trial_period',
			'subscription_sign_up_fee',
			'subscription_one_time_shipping',
		);

		$tokens = array();
		foreach ( $metas as $token_id ) {
			$tokens[] = self::woo_build( $trigger_code, $token_id, __( 'Subscription product', 'uncanny-automator' ) );
		}

		$tokens = $tokens + self::product_tokens( 0, $trigger_code );

		return apply_filters( 'automator_trigger_' . self::$integration . '_' . $trigger_code . '_tokens', $tokens, $trigger_code, $metas );
	}
}
