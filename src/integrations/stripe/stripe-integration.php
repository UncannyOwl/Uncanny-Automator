<?php

namespace Uncanny_Automator\Integrations\Stripe;

/**
 * Class Stripe_Integration
 *
 * @package Uncanny_Automator
 */
class Stripe_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Stripe_Helpers();
		$this->set_integration( 'STRIPE' );

		$name = 'Stripe';

		if ( 'test' === $this->helpers->get_mode() ) {
			$name .= ' (' . __( 'Test mode', 'uncanny-automator' ) . ')';
		}

		$this->set_name( $name );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/stripe-icon.svg' );
		$this->set_connected( $this->helpers->integration_status() );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		new Stripe_Settings( $this->helpers );
		new Create_Payment_Link( $this->helpers );
		new Create_Customer( $this->helpers );
		new Customer_Created( $this->helpers );
		new Payment_Completed( $this->helpers );
		new Delete_Customer( $this->helpers );
		new Subscription_Cancelled( $this->helpers );
		new Charge_Refunded( $this->helpers );
		new Charge_Failed( $this->helpers );
	}
}
