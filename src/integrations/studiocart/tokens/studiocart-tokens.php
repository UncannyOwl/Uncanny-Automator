<?php

namespace Uncanny_Automator;

/**
 * Studiocart Tokens file
 */
class Studiocart_Tokens {

	/**
	 * Studiocart_Tokens Constructor
	 */
	public function __construct() {
		add_filter( 'automator_maybe_trigger_studiocart_tokens', array( $this, 'studiocart_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'studiocart_token' ), 999999, 6 );
	}

	/**
	 * Studio cart tokens trigger specific.
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function studiocart_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		$trigger_meta = $args['meta'];

		if ( 'STUDIOCARTORDER' === $trigger_meta ) {
			$fields = array();

			$fields[] = array(
				'tokenId'         => 'product_title',
				'tokenName'       => __( 'Product title', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			);

			$fields[] = array(
				'tokenId'         => 'product_id',
				'tokenName'       => __( 'Product ID', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			);

			$fields[] = array(
				'tokenId'         => 'product_url',
				'tokenName'       => __( 'Product URL', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			);

			$fields[] = array(
				'tokenId'         => 'billing_address',
				'tokenName'       => __( 'Billing address', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			);

			$fields[] = array(
				'tokenId'         => 'billing_city',
				'tokenName'       => __( 'Billing city', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			);

			$fields[] = array(
				'tokenId'         => 'billing_state',
				'tokenName'       => __( 'Billing state', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			);

			$fields[] = array(
				'tokenId'         => 'billing_postcode',
				'tokenName'       => __( 'Billing postcode', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			);

			$fields[] = array(
				'tokenId'         => 'billing_phone',
				'tokenName'       => __( 'Billing phone', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			);

			$fields[] = array(
				'tokenId'         => 'order_id',
				'tokenName'       => __( 'Order ID', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			);

			$fields[] = array(
				'tokenId'         => 'order_amount',
				'tokenName'       => __( 'Order amount', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			);

			$fields[] = array(
				'tokenId'         => 'payment_option_label',
				'tokenName'       => __( 'Payment option label', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			);

			if ( ! empty( $fields ) ) {
				$tokens = array_merge( $tokens, $fields );
			}
		}

		return $tokens;
	}

	/**
	 * Parse the token.
	 *
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return null|string
	 */
	public function studiocart_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		$piece = 'STUDIOCARTORDER';
		if ( empty( $pieces ) ) {
			return $value;
		}

		if ( ! in_array( $piece, $pieces, true ) ) {
			return $value;
		}

		$trigger_id   = $pieces[0];
		$trigger_meta = $pieces[1];
		$parse        = $pieces[2];

		foreach ( $trigger_data as $trigger ) {
			if ( ! is_array( $trigger ) || empty( $trigger ) ) {
				continue;
			}

			if ( key_exists( $trigger_meta, $trigger['meta'] ) || ( isset( $trigger['meta']['code'] ) && $trigger_meta === $trigger['meta']['code'] ) ) {
				$trigger_id     = $trigger['ID'];
				$trigger_log_id = $replace_args['trigger_log_id'];
				$order_id       = Automator()->helpers->recipe->get_form_data_from_trigger_meta( 'sc_order_id', $trigger_id, $trigger_log_id, $user_id );

				if ( ! empty( $order_id ) ) {
					$order = sc_setup_order( $order_id, true );
					if ( ! empty( $order ) ) {
						switch ( $parse ) {
							case 'order_id':
								$value = $order['ID'];
								break;
							case 'order_amount':
								$value = $order['amount'];
								break;
							case 'billing_phone':
								$value = $this->get_order_field_value( $order, 'phone' );
								break;
							case 'billing_postcode':
								$value = $this->get_order_field_value( $order, 'zip' );
								break;
							case 'billing_state':
								$value = $this->get_order_field_value( $order, 'state' );
								break;
							case 'billing_city':
								$value = $this->get_order_field_value( $order, 'city' );
								break;
							case 'billing_address':
								$address1 = $this->get_order_field_value( $order, 'address1' );
								$address2 = $this->get_order_field_value( $order, 'address2' );
								$address  = '';
								if ( '' !== (string) $address1 ) {
									$address .= $address1;
								}

								if ( '' !== (string) $address2 ) {
									$address .= ' ' . $address2;
								}

								$value = $address;
								break;
							case 'product_url':
								$value = get_the_permalink( $order['product_id'] );
								break;
							case 'product_id':
								$value = $order['product_id'];
								break;
							case 'product_title':
								$value = get_the_title( $order['product_id'] );
								break;
							case 'payment_option_label':
								$scorder = new \ScrtOrder( $order_id );
								$value   = $scorder->item_name;
								break;
							default:
								$token        = $parse;
								$token_pieces = $pieces;
								$value        = apply_filters( 'automator_studiocart_order_token_parser', $value, $token, $token_pieces, $order );
						}
					}
				}
			}
		}

		return $value;
	}

	private function get_order_field_value( $order, $field ) {
		$value = '';
		if ( isset( $order[ $field ] ) ) {
			$value = $order[ $field ];
		}
		return $value;
	}
}
