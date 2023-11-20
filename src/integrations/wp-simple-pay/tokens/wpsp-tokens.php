<?php

namespace Uncanny_Automator;

use function SimplePay\Pro\Post_Types\Simple_Pay\Util\get_custom_fields;

/**
 * Class Wpsp_Tokens
 *
 * @package Uncanny_Automator
 */
class Wpsp_Tokens {

	/**
	 * __construct
	 */
	public function __construct() {
		add_filter(
			'automator_maybe_trigger_wpsimplepay_wpspanonpurchaforms_tokens',
			array(
				$this,
				'wpsp_possible_tokens',
			),
			30,
			2
		);
		add_filter(
			'automator_maybe_trigger_wpsimplepay_wpspanonsubscription_tokens',
			array(
				$this,
				'wpsp_possible_tokens',
			),
			30,
			2
		);

		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_wpsp_tokens' ), 9000, 6 );
	}

	/**
	 * @param $tokens
	 * @param $args
	 *
	 * @return array
	 */
	public function wpsp_possible_tokens( $tokens = array(), $args = array() ) {
		$plain   = true;
		$form_id = isset( $args['triggers_meta']['WPSPFORMS'] ) ? absint( $args['triggers_meta']['WPSPFORMS'] ) : null;
		if ( null === $form_id && isset( $args['triggers_meta']['WPSPFORMSUBSCRIPTION'] ) ) {
			$form_id = absint( $args['triggers_meta']['WPSPFORMSUBSCRIPTION'] );
			$plain   = false;
		}

		if ( null === $form_id ) {
			return $tokens;
		}
		$form_fields = array();
		if ( function_exists( 'SimplePay\Pro\Post_Types\Simple_Pay\Util\get_custom_fields' ) && intval( '-1' ) !== intval( $form_id ) ) {
			$form_fields = \SimplePay\Pro\Post_Types\Simple_Pay\Util\get_custom_fields( $form_id );
		}

		$fields = array(
			array(
				'tokenId'         => 'BILLING_NAME',
				'tokenName'       => __( 'Billing name', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPSPFORMFIELDS_BILLING_FIELDS',
			),
			array(
				'tokenId'         => 'BILLING_EMAIL',
				'tokenName'       => __( 'Billing email', 'uncanny-automator' ),
				'tokenType'       => 'email',
				'tokenIdentifier' => 'WPSPFORMFIELDS_BILLING_FIELDS',
			),
			array(
				'tokenId'         => 'BILLING_TELEPHONE',
				'tokenName'       => __( 'Billing phone', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPSPFORMFIELDS_BILLING_FIELDS',
			),
			//			array(
			//				'tokenId'         => 'BILLING_TAX_ID',
			//				'tokenName'       => __( 'Billing tax ID', 'uncanny-automator' ),
			//				'tokenType'       => 'text',
			//				'tokenIdentifier' => 'WPSPFORMFIELDS_BILLING_FIELDS',
			//			),
			array(
				'tokenId'         => 'BILLING_STREET_ADDRESS',
				'tokenName'       => __( 'Billing address', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPSPFORMFIELDS_BILLING_FIELDS',
			),
			array(
				'tokenId'         => 'BILLING_CITY',
				'tokenName'       => __( 'Billing city', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPSPFORMFIELDS_BILLING_FIELDS',
			),
			array(
				'tokenId'         => 'BILLING_STATE',
				'tokenName'       => __( 'Billing state', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPSPFORMFIELDS_BILLING_FIELDS',
			),
			array(
				'tokenId'         => 'BILLING_POSTAL_CODE',
				'tokenName'       => __( 'Billing postal code', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPSPFORMFIELDS_BILLING_FIELDS',
			),
			array(
				'tokenId'         => 'BILLING_COUNTRY',
				'tokenName'       => __( 'Billing country', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPSPFORMFIELDS_BILLING_FIELDS',
			),
			array(
				'tokenId'         => 'PRICE_OPTION',
				'tokenName'       => __( 'Price option', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPSPFORMFIELDS_BILLING_FIELDS',
			),
			array(
				'tokenId'         => 'QUANTITY_PURCHASED',
				'tokenName'       => __( 'Quantity', 'uncanny-automator' ),
				'tokenType'       => 'int',
				'tokenIdentifier' => 'WPSPFORMFIELDS_BILLING_FIELDS',
			),
		);

		$skip_types = apply_filters(
			'automator_wp_simpay_skip_field_types',
			array(
				'email',
				'tax_id',
				'address',
				'telephone',
				'customer_name',
				'plan_select',
				'card',
			)
		);
		if ( ! empty( $form_fields ) ) {
			foreach ( $form_fields as $field ) {
				if ( isset( $field['label'] ) && ! in_array( $field['type'], $skip_types, true ) ) {
					$input_id = $field['id'];
					$token_id = "simpay-form-$form_id-field-$input_id";

					if ( isset( $field['metadata'] ) && ! empty( $field['metadata'] ) ) {
						$token_id = $field['metadata'];
					}

					$existing_tokens = array_column( $tokens, 'tokenId' );
					if ( ! in_array( $token_id, $existing_tokens, true ) ) {
						$fields[] = array(
							'tokenId'         => $token_id,
							'tokenName'       => empty( $field['label'] ) ? sprintf( 'Field ID #%s (no label)', $field['uid'] ) : $field['label'],
							'tokenType'       => 'text',
							'tokenIdentifier' => 'WPSPFORMFIELDS_META',
						);
					}
				}
			}
		}
		// Non subscription forms
		if ( $plain ) {
			$fields[] = array(
				'tokenId'         => 'AMOUNT_PAID',
				'tokenName'       => __( 'Amount paid', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPSPFORMS',
			);
		}
		// Subscription forms
		if ( ! $plain ) {
			$fields[] = array(
				'tokenId'         => 'AMOUNT_DUE',
				'tokenName'       => __( 'Amount due', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPSPFORMFIELDS_PLAN_AMOUNT_DUE',
			);
			$fields[] = array(
				'tokenId'         => 'AMOUNT_PAID',
				'tokenName'       => __( 'Amount paid', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPSPFORMFIELDS_PLAN_AMOUNT_PAID',
			);
			$fields[] = array(
				'tokenId'         => 'AMOUNT_REMAINING',
				'tokenName'       => __( 'Amount remaining', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPSPFORMFIELDS_PLAN_AMOUNT_REMAINING',
			);
		}
		$tokens = array_merge( $tokens, $fields );

		return $tokens;

	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return false|int|mixed|string|\WP_Error
	 */
	public function parse_wpsp_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( empty( $pieces ) ) {
			return $value;
		}

		$trigger_metas = array(
			'WPSPFORMS',
			'WPSPFORMSUBSCRIPTION',
			'WPSPFORMFIELDS_META',
			'WPSPFORMFIELDS_BILLING_FIELDS',
			'WPSPFORMFIELDS_PLAN_AMOUNT_DUE',
			'WPSPFORMFIELDS_PLAN_AMOUNT_PAID',
			'WPSPFORMFIELDS_PLAN_AMOUNT_REMAINING',
		);

		if ( ! array_intersect( $trigger_metas, $pieces ) ) {
			return $value;
		}

		$meta_key = $pieces[2];
		// Form title
		if ( 'WPSPFORMS' === $meta_key || 'WPSPFORMSUBSCRIPTION' === $meta_key ) {
			$value = Automator()->db->token->get( "{$meta_key}_ID", $replace_args );
			$form  = simpay_get_form( $value );
			if ( $form ) {
				return $form->company_name;
			}

			return __( 'N/A', 'uncanny-automator' );
		}
		// Form meta
		if ( 'WPSPFORMFIELDS_META' === $pieces[1] ) {
			$meta_data = maybe_unserialize( Automator()->db->token->get( 'meta_data', $replace_args ) );

			return is_array( $meta_data ) && array_key_exists( $meta_key, $meta_data ) ? $meta_data[ $meta_key ] : '';
		}
		// Billing fields
		if ( 'WPSPFORMFIELDS_BILLING_FIELDS' === $pieces[1] ) {
			$customer_data = maybe_unserialize( Automator()->db->token->get( 'customer_data', $replace_args ) );
			$customer_data = json_decode( wp_json_encode( $customer_data ), false );
			switch ( $meta_key ) {
				case 'BILLING_NAME':
					$value = $customer_data->name;
					break;
				case 'BILLING_EMAIL':
					$value = $customer_data->email;
					break;
				case 'BILLING_TELEPHONE':
					$value = $customer_data->phone;
					break;
				case 'BILLING_STREET_ADDRESS':
					$value = $customer_data->address->line1 . ' ' . $customer_data->address->line2;
					break;
				case 'BILLING_CITY':
					$value = $customer_data->address->city;
					break;
				case 'BILLING_STATE':
					$value = $customer_data->address->state;
					break;
				case 'BILLING_POSTAL_CODE':
					$value = $customer_data->address->postal_code;
					break;
				case 'BILLING_COUNTRY':
					$value = $customer_data->address->country;
					break;
				case 'PRICE_OPTION':
					$value = $this->get_price_option_values( $replace_args );
					break;
				case 'QUANTITY_PURCHASED':
					$value = $this->get_price_option_values( $replace_args, 'qty' );
					break;
			}

			return $value;
		}
		// Other form tokens
		$value = Automator()->db->token->get( $meta_key, $replace_args );
		if ( preg_match( '/(AMOUNT)/', $meta_key ) ) {

			return simpay_format_currency( $value );
		}

		return $value;
	}

	/**
	 * @param $replace_args
	 * @param $type
	 *
	 * @return mixed|void
	 */
	private function get_price_option_values( $replace_args, $type = 'price' ) {
		$meta_data = maybe_unserialize( Automator()->db->token->get( 'meta_data', $replace_args ) );
		if ( 'qty' === $type ) {
			return $meta_data['simpay_quantity'];

		}

		$price_instance = explode( ':', $meta_data['simpay_price_instances'] );
		$price_meta_key = simpay_is_livemode() ? '_simpay_prices_live' : '_simpay_prices_test';
		$prices         = get_post_meta( $meta_data['simpay_form_id'], $price_meta_key, true );
		foreach ( $prices as $price_key => $price_details ) {
			if ( $price_key === $price_instance[0] ) {
				return $price_details['label'];
			}
		}
	}

}
