<?php

namespace Uncanny_Automator\Integrations\Cartflows;

use Uncanny_Automator\Integrations\Cartflows\Dispatchers\Cartflows_Checkout_Dispatcher;

/**
 * Class Cartflows_Integration
 *
 * @package Uncanny_Automator
 */
class Cartflows_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Cartflows_Helpers();
		$this->set_integration( 'CARTFLOWS' );
		$this->set_name( 'CartFlows' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/cart-flows-icon.svg' );

		$this->set_plugin_file_path( 'cartflows/cartflows.php' );
		$this->set_developer_name( 'Brainstorm Force' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * Always-on upstream listeners — registered in targeted mode too.
	 *
	 * The checkout dispatcher listens on WooCommerce's checkout-completion hooks,
	 * which fire on the front end. In targeted mode the framework never calls
	 * load(); only this method runs. Booting the dispatcher here is what makes
	 * both checkout triggers fire at all outside the recipe builder.
	 *
	 * @return void
	 */
	protected function load_shared_hooks() {
		Cartflows_Checkout_Dispatcher::boot();
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {

		// Register the always-on listeners in full-load mode too — the parent
		// only calls load_shared_hooks() in targeted mode, never alongside load().
		$this->load_shared_hooks();

		// Triggers.
		new Cartflows_User_Completes_Checkout( $this->helpers );
		new Cartflows_User_Purchases_Product( $this->helpers );

		// Actions.
		new Cartflows_Create_Flow( $this->helpers );
		new Cartflows_Create_Step( $this->helpers );
	}

	/**
	 * Check if CartFlows is active.
	 *
	 * WooCommerce is NOT gated here — the flow and step actions work on a
	 * CartFlows install without it. The two checkout triggers gate themselves
	 * through requirements_met().
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'CARTFLOWS_FILE' );
	}
}
