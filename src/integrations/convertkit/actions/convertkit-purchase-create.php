<?php

namespace Uncanny_Automator\Integrations\ConvertKit;

/**
 * ConvertKit - Create a purchase (v4 only)
 *
 * @property ConvertKit_App_Helpers $helpers
 * @property ConvertKit_Api_Caller $api
 */
class CONVERTKIT_PURCHASE_CREATE extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup Action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'CONVERTKIT' );
		$this->set_action_code( 'CONVERTKIT_PURCHASE_CREATE' );
		$this->set_action_meta( 'CONVERTKIT_PURCHASE_CREATE_META' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/convertkit/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Create {{a purchase}}', 'ConvertKit', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the email address
				esc_attr_x( 'Create {{a purchase:%1$s}}', 'ConvertKit', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
	}

	/**
	 * Requires OAuth (v4) connection.
	 *
	 * @return bool
	 */
	public function requirements_met() {
		return ! $this->helpers->is_v3();
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_email_option_config( $this->get_action_meta() ),
			array(
				'option_code'     => 'TRANSACTION_ID',
				'label'           => esc_attr_x( 'Transaction ID', 'ConvertKit', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => true,
				'relevant_tokens' => array(),
			),
			array(
				'option_code'     => 'STATUS',
				'label'           => esc_attr_x( 'Status', 'ConvertKit', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'default_value'   => 'paid',
				'relevant_tokens' => array(),
				'options'         => array(
					array(
						'value' => 'paid',
						'text'  => esc_attr_x( 'Paid', 'ConvertKit', 'uncanny-automator' ),
					),
					array(
						'value' => 'refund',
						'text'  => esc_attr_x( 'Refund', 'ConvertKit', 'uncanny-automator' ),
					),
				),
			),
			array(
				'option_code'     => 'CURRENCY',
				'label'           => esc_attr_x( 'Currency', 'ConvertKit', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => true,
				'placeholder'     => 'USD',
				'description'     => esc_attr_x( '3-letter currency code (e.g. USD, EUR, GBP)', 'ConvertKit', 'uncanny-automator' ),
				'relevant_tokens' => array(),
			),
			array(
				'option_code'     => 'SUBTOTAL',
				'label'           => esc_attr_x( 'Subtotal', 'ConvertKit', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => true,
				'relevant_tokens' => array(),
			),
			array(
				'option_code'     => 'TAX',
				'label'           => esc_attr_x( 'Tax', 'ConvertKit', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => false,
				'default_value'   => '0',
				'relevant_tokens' => array(),
			),
			array(
				'option_code'     => 'SHIPPING',
				'label'           => esc_attr_x( 'Shipping', 'ConvertKit', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => false,
				'default_value'   => '0',
				'relevant_tokens' => array(),
			),
			array(
				'option_code'     => 'DISCOUNT',
				'label'           => esc_attr_x( 'Discount', 'ConvertKit', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => false,
				'default_value'   => '0',
				'relevant_tokens' => array(),
			),
			array(
				'option_code'     => 'TOTAL',
				'label'           => esc_attr_x( 'Total', 'ConvertKit', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => true,
				'relevant_tokens' => array(),
			),
			array(
				'option_code'     => 'TRANSACTION_TIME',
				'label'           => esc_attr_x( 'Transaction time', 'ConvertKit', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => false,
				'description'     => esc_attr_x( 'ISO 8601 datetime (e.g. 2025-06-01T09:00:00Z). Defaults to now if empty.', 'ConvertKit', 'uncanny-automator' ),
				'relevant_tokens' => array(),
			),
			$this->get_products_repeater_config(),
		);
	}

	/**
	 * Define tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'PURCHASE_ID'             => array(
				'name' => esc_html_x( 'Purchase ID', 'ConvertKit', 'uncanny-automator' ),
				'type' => 'int',
			),
			'PURCHASE_TRANSACTION_ID' => array(
				'name' => esc_html_x( 'Transaction ID', 'ConvertKit', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Get the repeater config for products.
	 *
	 * @return array
	 */
	private function get_products_repeater_config() {
		return array(
			'option_code'     => 'PRODUCTS',
			'input_type'      => 'repeater',
			'label'           => esc_html_x( 'Products', 'ConvertKit', 'uncanny-automator' ),
			'required'        => true,
			'hide_actions'    => false,
			'relevant_tokens' => array(),
			'fields'          => array(
				array(
					'option_code' => 'PRODUCT_NAME',
					'label'       => esc_html_x( 'Name', 'ConvertKit', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => true,
				),
				array(
					'option_code' => 'PRODUCT_PID',
					'label'       => esc_html_x( 'Product ID', 'ConvertKit', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => true,
				),
				array(
					'option_code' => 'PRODUCT_LID',
					'label'       => esc_html_x( 'Line item ID', 'ConvertKit', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => true,
				),
				array(
					'option_code'   => 'PRODUCT_QUANTITY',
					'label'         => esc_html_x( 'Quantity', 'ConvertKit', 'uncanny-automator' ),
					'input_type'    => 'text',
					'required'      => true,
					'default_value' => '1',
				),
				array(
					'option_code' => 'PRODUCT_UNIT_PRICE',
					'label'       => esc_html_x( 'Unit price', 'ConvertKit', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => true,
				),
				array(
					'option_code' => 'PRODUCT_SKU',
					'label'       => esc_html_x( 'SKU', 'ConvertKit', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => false,
				),
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$email          = $this->helpers->require_valid_email( $parsed[ $this->get_action_meta() ] ?? '' );
		$transaction_id = sanitize_text_field( $parsed['TRANSACTION_ID'] ?? '' );

		if ( empty( $transaction_id ) ) {
			throw new \Exception(
				esc_html_x( 'Please provide a transaction ID.', 'ConvertKit', 'uncanny-automator' )
			);
		}

		$products = $this->parse_products( $action_data );

		if ( empty( $products ) ) {
			throw new \Exception(
				esc_html_x( 'Please add at least one product.', 'ConvertKit', 'uncanny-automator' )
			);
		}

		$transaction_time = sanitize_text_field( $parsed['TRANSACTION_TIME'] ?? '' );

		// Build the purchase payload.
		$purchase = array(
			'email_address'    => $email,
			'transaction_id'   => $transaction_id,
			'status'           => sanitize_text_field( $parsed['STATUS'] ?? 'paid' ),
			'currency'         => strtoupper( sanitize_text_field( $parsed['CURRENCY'] ?? 'USD' ) ),
			'subtotal'         => (float) ( $parsed['SUBTOTAL'] ?? 0 ),
			'tax'              => (float) ( $parsed['TAX'] ?? 0 ),
			'shipping'         => (float) ( $parsed['SHIPPING'] ?? 0 ),
			'discount'         => (float) ( $parsed['DISCOUNT'] ?? 0 ),
			'total'            => (float) ( $parsed['TOTAL'] ?? 0 ),
			'transaction_time' => ! empty( $transaction_time ) ? $transaction_time : gmdate( 'c' ),
			'products'         => $products,
		);

		$response = $this->api->api_request(
			array(
				'action'   => 'create_purchase',
				'purchase' => wp_json_encode( $purchase ),
			),
			$action_data
		);

		$data = $response['data']['purchase'] ?? array();

		$this->hydrate_tokens(
			array(
				'PURCHASE_ID'             => $data['id'] ?? '',
				'PURCHASE_TRANSACTION_ID' => $data['transaction_id'] ?? $transaction_id,
			)
		);

		return true;
	}

	/**
	 * Parse products from repeater rows.
	 *
	 * @param array $action_data The raw action data.
	 *
	 * @return array Array of product objects for the Kit API.
	 */
	private function parse_products( $action_data ) {

		$rows = json_decode( $action_data['meta']['PRODUCTS'] ?? '[]', true );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$products = array();

		foreach ( $rows as $row ) {
			$name = sanitize_text_field( $row['PRODUCT_NAME'] ?? '' );
			$pid  = sanitize_text_field( $row['PRODUCT_PID'] ?? '' );

			if ( empty( $name ) || empty( $pid ) ) {
				continue;
			}

			$product = array(
				'name'       => $name,
				'pid'        => $pid,
				'lid'        => sanitize_text_field( $row['PRODUCT_LID'] ?? $pid ),
				'quantity'   => absint( $row['PRODUCT_QUANTITY'] ?? 1 ),
				'unit_price' => (float) ( $row['PRODUCT_UNIT_PRICE'] ?? 0 ),
			);

			$sku = sanitize_text_field( $row['PRODUCT_SKU'] ?? '' );

			if ( ! empty( $sku ) ) {
				$product['sku'] = $sku;
			}

			$products[] = $product;
		}

		return $products;
	}
}
