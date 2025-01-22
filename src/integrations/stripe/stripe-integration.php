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

		// Settings
		new Stripe_Settings( $this->helpers );

		// Actions
		new Create_Payment_Link( $this->helpers );
		new Create_Customer( $this->helpers );
		new Delete_Customer( $this->helpers );

		// Triggers
		new Customer_Created( $this->helpers );
		new Product_Refunded( $this->helpers );
		new Subcription_Created( $this->helpers );
		new Subcription_Cancelled( $this->helpers );
		new Subcription_Paid( $this->helpers );
		new Subcription_Payment_Failed( $this->helpers );
		new Onetime_Payment_Completed( $this->helpers );

		// Deprecated since Nov 2024
		new Payment_Completed( $this->helpers );
		new Subscription_Cancelled_Deprecated( $this->helpers );
		new Charge_Failed( $this->helpers );
		new Charge_Refunded( $this->helpers );
		new Customer_Created_Deprecated( $this->helpers );
	}
}
