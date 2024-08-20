<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Integrations\Woocommerce\Tokens\Loopable\Universal\All_Orders_24_Hours;
use Uncanny_Automator\Integrations\Woocommerce\Tokens\Loopable\Universal\All_Orders_Monthly;
use Uncanny_Automator\Integrations\Woocommerce\Tokens\Loopable\Universal\All_Orders_Weekly;
use Uncanny_Automator\Integrations\Woocommerce\Tokens\Loopable\Universal\All_Orders_Yearly;
use Uncanny_Automator\Integrations\Woocommerce\Tokens\Loopable\Universal\Product_Categories;
use Uncanny_Automator\Integrations\Woocommerce\Tokens\Loopable\Universal\User_Order_24_Hours;
use Uncanny_Automator\Integrations\Woocommerce\Tokens\Loopable\Universal\User_Orders_Monthly;
use Uncanny_Automator\Integrations\Woocommerce\Tokens\Loopable\Universal\User_Orders_Weekly;
use Uncanny_Automator\Integrations\Woocommerce\Tokens\Loopable\Universal\User_Orders_Yearly;
use Uncanny_Automator\Integrations\Woocommerce\Tokens\Loopable\Universal\User_Purchase_Products;

/**
 * Class Add_Wc_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Wc_Integration {

	use Recipe\Integrations;

	const integration = 'WC';

	public function __construct() {

		$this->setup();
		$this->create_loopable_tokens();

	}

	/**
	 * @return mixed
	 */
	protected function setup() {
		$this->set_integration( self::integration );
		$this->set_name( 'Woo' );
		$this->set_icon( 'woocommerce-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'woocommerce/woocommerce.php' );
		$this->set_loopable_tokens( $this->create_loopable_tokens() );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Create loopable tokens.
	 *
	 * @return array
	 */
	public function create_loopable_tokens() {

		$loopable_token_classes = array(
			'PURCHASED_PRODUCT'             => User_Purchase_Products::class,
			'USER_ORDERS_TWENTY_FOUR_HOURS' => User_Order_24_Hours::class,
			'USER_ORDERS_WEEKLY'            => User_Orders_Weekly::class,
			'USER_ORDERS_MONTHLY'           => User_Orders_Monthly::class,
			'USER_ORDERS_YEARLY'            => User_Orders_Yearly::class,
			'ALL_ORDERS_TWENTY_FOUR_HOURS'  => All_Orders_24_Hours::class,
			'ALL_ORDERS_WEEKLY'             => All_Orders_Weekly::class,
			'ALL_ORDERS_MONTHLY'            => All_Orders_Monthly::class,
			'ALL_ORDERS_YEARLY'             => All_Orders_Yearly::class,
			'WOO_PRODUCT_CATEGORIES'        => Product_Categories::class,
		);

		return $loopable_token_classes;

	}

}
