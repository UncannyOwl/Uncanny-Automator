<?php

namespace Uncanny_Automator\Integrations\Fluent_Cart;

use Uncanny_Automator\Integration;

/**
 * Class Fluent_Cart_Integration
 *
 * @package Uncanny_Automator\Integrations\Fluent_Cart
 */
class Fluent_Cart_Integration extends Integration {

	/**
	 * Setup integration identity + helpers.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Fluent_Cart_Helpers();
		$this->set_integration( 'FLUENT_CART' );
		$this->set_name( 'FluentCart' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/fluent-cart-icon.svg' );
	}

	/**
	 * Load triggers and actions.
	 *
	 * @return void
	 */
	public function load() {

		// Triggers.
		new Fluent_Cart_Order_Paid( $this->helpers );
		new Fluent_Cart_Subscription_Activated( $this->helpers );
		new Fluent_Cart_Subscription_Cancelled( $this->helpers );
		new Fluent_Cart_Subscription_Renewed( $this->helpers );
		new Fluent_Cart_Order_Refunded( $this->helpers );
		new Fluent_Cart_Order_Status_Changed( $this->helpers );
		new Fluent_Cart_Subscription_Status_Changed( $this->helpers );

		// Actions.
		new Fluent_Cart_Set_Order_Status( $this->helpers );
		new Fluent_Cart_Cancel_Subscription( $this->helpers );
		new Fluent_Cart_Add_Order_Note( $this->helpers );
	}

	/**
	 * Whether FluentCart is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'FLUENTCART_PLUGIN_PATH' );
	}
}
