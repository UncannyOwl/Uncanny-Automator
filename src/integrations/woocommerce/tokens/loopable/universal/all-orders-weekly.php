<?php
namespace Uncanny_Automator\Integrations\Woocommerce\Tokens\Loopable\Universal;

use Uncanny_Automator\Integrations\Woocommerce\Tokens\Loopable\Utils\Loopable_Tokens_Utils;
use Uncanny_Automator\Services\Loopable\Loopable_Token_Collection;
use Uncanny_Automator\Services\Loopable\Universal_Loopable_Token;
use Uncanny_Automator\Woocommerce_Helpers;

/**
 * All_Orders_Weekly
 *
 * @package Uncanny_Automator\Integrations\Woocommerce\Tokens\Loopable
 */
class All_Orders_Weekly extends Universal_Loopable_Token {

	/**
	 * Register loopable token.
	 *
	 * @return void
	 */
	public function register_loopable_token() {

		$child_tokens = Loopable_Tokens_Utils::get_timed_orders_loopable_tokens();

		$this->set_id( 'ALL_ORDERS_WEEKLY' );
		$this->set_name( _x( 'All orders in the past week', 'Woo', 'uncanny-automator' ) );
		$this->set_log_identifier( '#Order ID: {{ORDER_ID}} - {{DATE_CREATED}}' );
		$this->set_child_tokens( $child_tokens );
		$this->set_requires_user( false );

	}

	/**
	 * Hydrate the tokens.
	 *
	 * @param mixed $args
	 * @return Loopable_Token_Collection
	 */
	public function hydrate_token_loopable( $args ) {

		$loopable = new Loopable_Token_Collection();

		$orders = Woocommerce_Helpers::get_user_orders( null, 'weekly' );

		foreach ( $orders as $order ) {
			$loopable->create_item(
				array(
					'ORDER_ID'     => $order['order_id'],
					'DATE_CREATED' => $order['date_created'],
					'TOTAL'        => $order['total'],
					'STATUS'       => $order['status'],
					'ITEMS'        => $order['items'],
				)
			);
		}

		return $loopable;

	}

}
