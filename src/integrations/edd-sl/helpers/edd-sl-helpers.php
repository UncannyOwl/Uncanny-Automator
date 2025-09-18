<?php

namespace Uncanny_Automator\Integrations\Edd_SL;

/**
 * Class Edd_Helpers
 *
 * @package Uncanny_Automator
 */
class Edd_Sl_Helpers {

	/**
	 * @return array
	 */
	public function get_all_downloads( $is_any = true ) {
		$all_downloads = array();
		if ( true === $is_any ) {
			$all_downloads[] = array(
				'text'  => esc_html_x( 'Any download', 'EDD - Software Licensing', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		$args = array(
			'post_type'      => 'download',
			'posts_per_page' => 9999, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
			'meta_key'       => '_edd_sl_enabled',
			'meta_value'     => 1,
		);

		$downloads = get_posts( $args );

		foreach ( $downloads as $download ) {
			$all_downloads[] = array(
				'text'  => $download->post_title,
				'value' => $download->ID,
			);
		}

		return $all_downloads;
	}

	/**
	 * @return array[]
	 */
	public function get_common_tokens() {
		return array(
			array(
				'tokenId'   => 'DOWNLOAD_ID',
				'tokenName' => esc_html_x( 'Download ID', 'EDD - Software Licensing', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'DOWNLOAD_NAME',
				'tokenName' => esc_html_x( 'Download name', 'EDD - Software Licensing', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DOWNLOAD_QTY',
				'tokenName' => esc_html_x( 'Download quantity', 'EDD - Software Licensing', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'DOWNLOAD_SUBTOTAL',
				'tokenName' => esc_html_x( 'Download subtotal', 'EDD - Software Licensing', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'DOWNLOAD_TAX',
				'tokenName' => esc_html_x( 'Download tax', 'EDD - Software Licensing', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'DOWNLOAD_PRICE',
				'tokenName' => esc_html_x( 'Download price', 'EDD - Software Licensing', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'LICENSE_KEY',
				'tokenName' => esc_html_x( 'License key', 'EDD - Software Licensing', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LICENSE_PURCHASE_DATE',
				'tokenName' => esc_html_x( 'License purchase date', 'EDD - Software Licensing', 'uncanny-automator' ),
				'tokenType' => 'date',
			),
			array(
				'tokenId'   => 'LICENSE_EXPIRATION_DATE',
				'tokenName' => esc_html_x( 'License expiration date', 'EDD - Software Licensing', 'uncanny-automator' ),
				'tokenType' => 'date',
			),
			array(
				'tokenId'   => 'LICENSE_TERM',
				'tokenName' => esc_html_x( 'License term', 'EDD - Software Licensing', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LICENSE_ACTIVATION_LIMIT',
				'tokenName' => esc_html_x( 'License activation limit', 'EDD - Software Licensing', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			// Customer information tokens
			array(
				'tokenId'   => 'CUSTOMER_ID',
				'tokenName' => esc_html_x( 'Customer ID', 'EDD - Software Licensing', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'CUSTOMER_EMAIL',
				'tokenName' => esc_html_x( 'Customer email', 'EDD - Software Licensing', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CUSTOMER_FIRST_NAME',
				'tokenName' => esc_html_x( 'Customer first name', 'EDD - Software Licensing', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CUSTOMER_LAST_NAME',
				'tokenName' => esc_html_x( 'Customer last name', 'EDD - Software Licensing', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CUSTOMER_FULL_NAME',
				'tokenName' => esc_html_x( 'Customer full name', 'EDD - Software Licensing', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CUSTOMER_ADDRESS',
				'tokenName' => esc_html_x( 'Customer address', 'EDD - Software Licensing', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CUSTOMER_CITY',
				'tokenName' => esc_html_x( 'Customer city', 'EDD - Software Licensing', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CUSTOMER_STATE',
				'tokenName' => esc_html_x( 'Customer state', 'EDD - Software Licensing', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CUSTOMER_COUNTRY',
				'tokenName' => esc_html_x( 'Customer country', 'EDD - Software Licensing', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CUSTOMER_ZIP',
				'tokenName' => esc_html_x( 'Customer ZIP code', 'EDD - Software Licensing', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Parse common token values for EDD Software Licensing
	 *
	 * @param int $license_id License ID
	 * @param int $download_id Download ID
	 *
	 * @return array Token values
	 */
	public function parse_common_token_values( $license_id, $download_id ) {
		$license = edd_software_licensing()->get_license( $license_id );

		// Return empty tokens if license doesn't exist
		if ( ! $license ) {
			$defaults = wp_list_pluck( $this->get_common_tokens(), 'tokenId' );
			return array_fill_keys( $defaults, '' );
		}

		// Generate array of empty default values.
		$defaults = wp_list_pluck( $this->get_common_tokens(), 'tokenId' );
		$tokens   = array_fill_keys( $defaults, '' );

		// Download information
		$tokens['DOWNLOAD_NAME']     = get_the_title( $download_id );
		$tokens['DOWNLOAD_ID']       = $download_id;
		$tokens['DOWNLOAD_QTY']      = edd_get_cart_item_quantity( $download_id );
		$tokens['DOWNLOAD_SUBTOTAL'] = edd_get_cart_items_subtotal( $download_id );
		$tokens['DOWNLOAD_TAX']      = edd_get_cart_item_tax( $download_id );
		$tokens['DOWNLOAD_PRICE']    = edd_get_cart_item_price( $download_id );

		// License information
		$tokens['LICENSE_KEY']              = $license->license_key;
		$tokens['LICENSE_TERM']             = $license->license_term();
		$tokens['LICENSE_ACTIVATION_LIMIT'] = $license->get_activation_limit();
		$tokens['LICENSE_PURCHASE_DATE']    = wp_date(
			sprintf( '%s %s', get_option( 'date_format' ), get_option( 'time_format' ) ),
			strtotime( $license->date_created )
		);
		$tokens['LICENSE_EXPIRATION_DATE']  = wp_date(
			get_option( 'date_format' ),
			$license->expiration
		);

		// Customer information
		$customer = new \EDD_Customer( $license->customer_id );

		$customer_tokens = $this->extract_customer_tokens( $customer );
		$tokens          = array_merge( $tokens, $customer_tokens );

		return $tokens;
	}

	/**
	 * Extract customer tokens from EDD customer object
	 *
	 * @param \EDD_Customer $customer EDD Customer object
	 *
	 * @return array Customer token values
	 */
	private function extract_customer_tokens( $customer ) {
		$customer_tokens = array(
			'CUSTOMER_ID'         => '',
			'CUSTOMER_EMAIL'      => '',
			'CUSTOMER_FIRST_NAME' => '',
			'CUSTOMER_LAST_NAME'  => '',
			'CUSTOMER_FULL_NAME'  => '',
			'CUSTOMER_ADDRESS'    => '',
			'CUSTOMER_CITY'       => '',
			'CUSTOMER_STATE'      => '',
			'CUSTOMER_COUNTRY'    => '',
			'CUSTOMER_ZIP'        => '',
		);

		if ( ! $customer || ! $customer->id ) {
			return $customer_tokens;
		}

		$customer_tokens['CUSTOMER_ID']    = $customer->id;
		$customer_tokens['CUSTOMER_EMAIL'] = $customer->email;

		$names                                  = $this->get_customer_names( $customer );
		$customer_tokens['CUSTOMER_FIRST_NAME'] = $names['first_name'];
		$customer_tokens['CUSTOMER_LAST_NAME']  = $names['last_name'];
		$customer_tokens['CUSTOMER_FULL_NAME']  = $names['full_name'];

		$address         = $this->get_customer_address_tokens( $customer );
		$customer_tokens = array_merge( $customer_tokens, $address );

		return $customer_tokens;
	}

	/**
	 * Get customer name tokens
	 *
	 * @param \EDD_Customer $customer EDD Customer object
	 *
	 * @return array Name tokens
	 */
	private function get_customer_names( $customer ) {
		$first_name = '';
		$last_name  = '';

		if ( $customer->user_id ) {
			$first_name = get_user_meta( $customer->user_id, 'first_name', true );
			$last_name  = get_user_meta( $customer->user_id, 'last_name', true );
		}

		return array(
			'first_name' => $first_name,
			'last_name'  => $last_name,
			'full_name'  => trim( $first_name . ' ' . $last_name ),
		);
	}

	/**
	 * Get customer address tokens
	 *
	 * @param \EDD_Customer $customer EDD Customer object
	 *
	 * @return array Address tokens
	 */
	private function get_customer_address_tokens( $customer ) {
		$address_tokens = array(
			'CUSTOMER_ADDRESS' => '',
			'CUSTOMER_CITY'    => '',
			'CUSTOMER_STATE'   => '',
			'CUSTOMER_COUNTRY' => '',
			'CUSTOMER_ZIP'     => '',
		);

		$address = $customer->get_address();
		if ( ! $address ) {
			return $address_tokens;
		}

		$address_map = array(
			'CUSTOMER_ADDRESS' => array( 'address', 'line1' ),
			'CUSTOMER_CITY'    => array( 'city' ),
			'CUSTOMER_STATE'   => array( 'state', 'region' ),
			'CUSTOMER_COUNTRY' => array( 'country' ),
			'CUSTOMER_ZIP'     => array( 'zip', 'postal_code' ),
		);

		foreach ( $address_map as $token_key => $property_names ) {
			$address_tokens[ $token_key ] = $this->get_address_property( $address, $property_names );
		}

		return $address_tokens;
	}

	/**
	 * Get address property value with fallback options
	 *
	 * @param mixed $address Address object or array
	 * @param array $property_names Property names to try
	 *
	 * @return string Property value or empty string
	 */
	private function get_address_property( $address, $property_names ) {
		foreach ( $property_names as $property ) {
			$value = is_object( $address ) ? ( $address->$property ?? null ) : ( $address[ $property ] ?? null );
			if ( ! empty( $value ) ) {
				return $value;
			}
		}
		return '';
	}

	/**
	 * Get user ID from license customer
	 *
	 * @param int $license_id License ID
	 *
	 * @return int|null User ID or null if not found
	 */
	public function get_user_id_from_license( $license_id ) {
		$license = edd_software_licensing()->get_license( $license_id );

		if ( ! $license ) {
			return null;
		}

		$customer = new \EDD_Customer( $license->customer_id );

		return $customer && $customer->user_id ? $customer->user_id : null;
	}
}
