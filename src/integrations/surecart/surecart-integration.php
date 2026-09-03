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
		$this->helpers = new SureCart_Helpers();
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
		$this->load_shared_hooks();

		new SURECART_ORDER_CONFIRMED( $this->helpers );
		new SURECART_ORDER_SHIPPED( $this->helpers );
		new SURECART_PURCHASE_PRODUCT( $this->helpers );
	}

	/**
	 * Hooks that must run whenever the integration is needed, in both load modes.
	 *
	 * Targeted loading skips load(); binding the webhook-events filter here keeps
	 * SureCart's endpoint sync including order.shipped and order.fulfilled.
	 *
	 * @return void
	 */
	protected function load_shared_hooks() {
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
	 * Check if SureCart is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'SURECART_PLUGIN_FILE' );
	}
}
