<?php

namespace Uncanny_Automator;

/**
 * Class Edd_Tokens
 *
 * @package Uncanny_Automator
 */
class Edd_Tokens {

	/** Integration code
	 *
	 * @var string
	 */
	public static $integration = 'EDD';

	public function __construct() {
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_edd_trigger_tokens' ), 20, 6 );
		add_filter(
			'automator_maybe_trigger_edd_eddorderrefund_tokens',
			array(
				$this,
				'edd_possible_tokens',
			),
			20,
			2
		);

	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	function edd_possible_tokens( $tokens = array(), $args = array() ) {

		$trigger_integration = $args['integration'];
		$trigger_meta        = $args['meta'];

		$fields = array(
			array(
				'tokenId'         => 'EDDORDER_ID',
				'tokenName'       => __( 'Order ID', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'EDDORDER_TOTAL',
				'tokenName'       => __( 'Order total', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'EDDORDER_SUBTOTAL',
				'tokenName'       => __( 'Order subtotal', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'EDDORDER_ITEMS',
				'tokenName'       => __( 'Ordered items', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'EDDCUSTOMER_EMAIL',
				'tokenName'       => __( 'Customer email', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
		);

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
	 * @return mixed
	 */
	public function parse_edd_trigger_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( $pieces ) {
			if ( in_array( 'EDDORDERREFUNDED', $pieces ) || in_array( 'EDDORDERREFUND', $pieces ) ) {
				global $wpdb;
				$trigger_id     = $pieces[0];
				$trigger_meta   = $pieces[2];
				$trigger_log_id = isset( $replace_args['trigger_log_id'] ) ? absint( $replace_args['trigger_log_id'] ) : 0;
				$entry          = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT meta_value
						FROM {$wpdb->prefix}uap_trigger_log_meta
						WHERE meta_key = %s
						AND automator_trigger_log_id = %d
						AND automator_trigger_id = %d",
						$trigger_meta,
						$trigger_log_id,
						$trigger_id
					)
				);
				$value          = maybe_unserialize( $entry );
			}
		}//end if

		return $value;
	}
}
