<?php

namespace Uncanny_Automator;

/**
 * Class Advanced_Coupons_Tokens
 *
 * @package Uncanny_Automator
 */
class Advanced_Coupons_Tokens {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'ACFWC';

	/**
	 * @var array
	 */
	public $possible_order_fields = array();

	public function __construct() {

		add_filter(
			'automator_maybe_trigger_acfwc_acfwcspendscredit_tokens',
			array( $this, 'trigger_acfwc_trigger_tokens_func' ),
			20,
			2
		);

		add_filter(
			'automator_maybe_trigger_acfwc_acfwcreceivescredit_tokens',
			array( $this, 'trigger_acfwc_trigger_tokens_func' ),
			20,
			2
		);

		add_filter(
			'automator_maybe_parse_token',
			array(
				$this,
				'acfwc_parse_tokens',
			),
			20,
			6
		);

	}

	public function trigger_acfwc_trigger_tokens_func( $tokens = array(), $args = array() ) {
		/** @var Wc_Tokens $wc_tokens */
		$fields    = array();
		$fields[]  = array(
			'tokenId'         => 'USERTOTALCREDIT',
			'tokenName'       => __( "User's total store credit", 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => $args['meta'],
		);
		$fields[]  = array(
			'tokenId'         => 'USERLIFETIMECREDIT',
			'tokenName'       => __( "User's lifetime store credit", 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => $args['meta'],
		);
		$wc_tokens = Utilities::get_class_instance( 'Uncanny_Automator\WC_TOKENS' );
		if ( method_exists( $wc_tokens, 'wc_possible_tokens' ) ) {
			$new_tokens = $wc_tokens->wc_possible_tokens( $tokens, $args, 'order' );

			return array_merge( $fields, $new_tokens );
		}

		return array_merge( $fields, $tokens );
	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return mixed|string
	 */
	public function acfwc_parse_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( ! $pieces ) {
			return $value;
		}
		$to_match = array(
			'ACFWCUSERRECEIVESCREDIT',
			'ACFWCUSERSPENDSCREDIT',
			'ACFWCSPENDSCREDIT',
			'ACFWCRECEIVESCREDIT',
		);

		if ( array_intersect( $to_match, $pieces ) ) {
			/** @var Wc_Tokens $wc_tokens */
			$wc_tokens = Utilities::get_class_instance( 'Uncanny_Automator\WC_TOKENS' );
			if ( method_exists( $wc_tokens, 'replace_values' ) ) {
				$value = $wc_tokens->replace_values( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args );
			}
		}

		if ( in_array( 'ACFWC_AMOUNT', $pieces, true ) ) {
			return $this->get_token_value( $pieces );
		}

		if ( in_array( 'ACFWC_SPEND_AMOUNT', $pieces, true ) ) {
			return $this->get_token_value( $pieces );
		}

		if ( in_array( 'USERTOTALCREDIT', $pieces, true ) ) {
			return $this->get_token_value( $pieces );
		}

		if ( in_array( 'USERLIFETIMECREDIT', $pieces, true ) ) {
			return $this->get_token_value( $pieces );
		}

		return $value;
	}


	/**
	 * @param $pieces
	 *
	 * @return mixed
	 */
	public function get_token_value( $pieces = array() ) {
		$meta_field = $pieces[2];
		$trigger_id = absint( $pieces[0] );
		$meta_value = $this->get_form_data_from_trigger_meta( $meta_field, $trigger_id );

		if ( is_array( $meta_value ) ) {
			$value = join( ', ', $meta_value );
		} else {
			$value = $meta_value;
		}

		return $value;
	}

	/**
	 * @param $meta_key
	 * @param $trigger_id
	 *
	 * @return mixed|string
	 */
	public function get_form_data_from_trigger_meta( $meta_key, $trigger_id ) {
		global $wpdb;
		if ( empty( $meta_key ) || empty( $trigger_id ) ) {
			return '';
		}

		$meta_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}uap_trigger_log_meta WHERE meta_key = %s AND automator_trigger_id = %d ORDER BY ID DESC LIMIT 0,1", $meta_key, $trigger_id ) );
		if ( ! empty( $meta_value ) ) {
			return maybe_unserialize( $meta_value );
		}

		return '';
	}

}
