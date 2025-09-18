<?php

namespace Uncanny_Automator\Integrations\Easy_Digital_Downloads;

use Uncanny_Automator\Integration;

/**
 * Class Edd_Integration
 *
 * @package Uncanny_Automator
 */
class Edd_Integration extends Integration {

	/**
	 * Set up the integration.
	 */
	protected function setup() {
		$this->helpers = new EDD_Helpers();
		$this->set_integration( 'EDD' );
		$this->set_name( 'Easy Digital Downloads' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/easy-digital-downloads-icon.svg' );
	}

	/**
	 * Load the triggers and actions.
	 */
	protected function load() {
		// Load tokens
		new EDD_Tokens();

		// Load universal tokens
		new EDD_User_Spent_Token();
		new EDD_User_Address_Line_1_Token();
		new EDD_User_Address_Line_2_Token();
		new EDD_User_City_Token();
		new EDD_User_State_Token();
		new EDD_User_Country_Token();

		// Load universal loopable tokens (only user orders should be loopable)
		$user_orders_token = new \Uncanny_Automator\Integrations\Easy_Digital_Downloads\Tokens\Loopable\Universal\User_Orders( 'EDD' );
		$user_orders_token->register_hooks();

		//triggers
		new EDD_ANON_PRODUCT_PURCHASE( $this->helpers );
		new EDD_ORDERDONE( $this->helpers );
		new EDD_ORDERREFUNDED( $this->helpers );
		new EDD_PRODUCTPURCHASE( $this->helpers );
		new EDD_PRODUCTPURCHASEWITHPRICE( $this->helpers );
		new EDD_USER_SUBSCRIBES_TO_DOWNLOAD( $this->helpers );

		//actions
		new EDD_CANCEL_USERS_SUBSCRIPTION( $this->helpers );

		// Register AJAX handlers
		add_action( 'wp_ajax_automator_edd_price_options_handler', array( $this->helpers, 'get_download_price_options_ajax_handler' ) );
	}



	/**
	 * @return bool
	 */
	public function plugin_active() {
		return function_exists( 'EDD' );
	}
}
