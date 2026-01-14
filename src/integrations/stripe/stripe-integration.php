<?php

namespace Uncanny_Automator\Integrations\Stripe;

use Uncanny_Automator\App_Integrations\App_Integration;
use Exception;

/**
 * Class Stripe_Integration
 *
 * @package Uncanny_Automator
 */
class Stripe_Integration extends App_Integration {

	/**
	 * Get the integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'STRIPE',    // Integration code.
			'name'         => 'Stripe',    // Integration name.
			'api_endpoint' => 'v2/stripe', // Automator API server endpoint.
			'settings_id'  => 'stripe',    // Settings ID ( Settings url / tab id ).
		);
	}

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {

		$config = self::get_config();

		// Define helpers with common config values.
		$this->helpers = new Stripe_App_Helpers( $config );

		// Add test mode to the integration name if needed.
		if ( 'test' === $this->helpers->get_mode() ) {
			$config['name'] .= ' (' . esc_html_x( 'Test mode', 'Stripe', 'uncanny-automator' ) . ')';
		}

		// Set the icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/stripe-icon.svg' );

		// Finalize setup via the parent class with the common config.
		$this->setup_app_integration( $config );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {

		// Settings
		new Stripe_Settings( $this->dependencies, $this->get_settings_config() );

		// Actions
		new Create_Payment_Link( $this->dependencies );
		new Create_Customer( $this->dependencies );
		new Delete_Customer( $this->dependencies );

		// Triggers
		new Customer_Created( $this->dependencies );
		new Product_Refunded( $this->dependencies );
		new Subscription_Created( $this->dependencies );
		new Subscription_Cancelled( $this->dependencies );
		new Subscription_Paid( $this->dependencies );
		new Subscription_Payment_Failed( $this->dependencies );
		new Onetime_Payment_Completed( $this->dependencies );
	}

	/**
	 * Check if app is connected.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		try {
			$this->helpers->get_credentials();
			return true;
		} catch ( Exception $e ) {
			return false;
		}
	}
}
