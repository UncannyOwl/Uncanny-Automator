<?php

namespace Uncanny_Automator\Integrations\SureCart;

/**
 * Class SureCart_Integration
 *
 * @package Uncanny_Automator
 */
class SureCart_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Integration Set-up.
	 */
	protected function setup() {
		$this->set_integration( 'SURECART' );
		$this->set_name( 'SureCart' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/surecart-icon.svg' );
	}

	/**
	 * Bootstrap actions, triggers, settings page, etc.
	 *
	 * @return void
	 */
	public function load() {
		$helpers = new SureCart_Helpers();
		// Triggers with dependencies - the Trigger abstract class DOES accept constructor parameters
		// The dependencies are passed to the parent constructor and used by get_item_helpers()
		new SURECART_ORDER_CONFIRMED( $helpers );
		new SURECART_ORDER_SHIPPED( $helpers );
		new SURECART_PURCHASE_PRODUCT( $helpers );

		// Add webhook events for order shipped and fulfilled
		add_filter( 'surecart/webhook_endpoint/set_attribute', array( $this, 'add_surecart_webhook_events' ), 10, 3 );
	}

	/**
	 * Add custom webhook events to SureCart.
	 *
	 * @param mixed  $value The current value.
	 * @param string $key   The attribute key.
	 * @param mixed  $model The model object.
	 *
	 * @return mixed
	 */
	public function add_surecart_webhook_events( $value, $key, $model ) {
		if ( 'webhook_events' !== $key ) {
			return $value;
		}

		// Add custom webhook events
		if ( is_array( $value ) ) {
			$value[] = 'order.shipped';
			$value[] = 'order.fulfilled'; // optional.
		}

		return $value;
	}

	/**
	 * Explicitly return true because it doesn't depend on any 3rd-party plugin.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'SURECART_PLUGIN_FILE' );
	}
}
