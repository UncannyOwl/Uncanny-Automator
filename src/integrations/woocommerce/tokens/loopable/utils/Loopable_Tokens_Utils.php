<?php
namespace Uncanny_Automator\Integrations\Woocommerce\Tokens\Loopable\Utils;

/**
 * Loopable_Tokens_Utils
 *
 * @package Uncanny_Automator\Integrations\Woocommerce\Tokens\Loopable\Utils
 */
class Loopable_Tokens_Utils {

	/**
	 * Returns the orders related tokens.
	 *
	 * @return mixed[]
	 */
	public static function get_timed_orders_loopable_tokens() {

		return array(
			'ORDER_ID'     => array(
				'name'       => _x( 'Order ID', 'Woo', 'uncanny-automator' ),
				'token_type' => 'integer',
			),
			'DATE_CREATED' => array(
				'name'       => _x( 'Date created', 'Woo', 'uncanny-automator' ),
				'token_type' => 'date',
			),
			'TOTAL'        => array(
				'name'       => _x( 'Total', 'Woo', 'uncanny-automator' ),
				'token_type' => 'float',
			),
			'STATUS'       => array(
				'name'       => _x( 'Status', 'Woo', 'uncanny-automator' ),
				'token_type' => 'float',
			),
			'ITEMS'        => array(
				'name' => _x( 'Items', 'Woo', 'uncanny-automator' ),
			),
		);

	}
}

