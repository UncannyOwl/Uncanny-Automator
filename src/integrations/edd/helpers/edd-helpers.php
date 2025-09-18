<?php

namespace Uncanny_Automator\Integrations\Easy_Digital_Downloads;

use Uncanny_Automator_Pro\Edd_Pro_Helpers;

// Create class alias for backward compatibility with Pro plugin
class_alias( 'Uncanny_Automator\Integrations\Easy_Digital_Downloads\Edd_Helpers', 'Uncanny_Automator\Edd_Helpers' );


/**
 * Class Edd_Helpers
 *
 * @package Uncanny_Automator
 */
class Edd_Helpers {

	/**
	 * EDD helpers.
	 *
	 * @var Edd_Helpers
	 */
	public $options;

	/**
	 * Load options.
	 *
	 * @var bool
	 */
	public $load_options = true;


	/**
	 * Get user address data from EDD with standardized format.
	 *
	 * @param int $user_id The user ID.
	 * @return array Standardized address data array.
	 */
	public function get_user_address_data( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return $this->get_empty_address_array();
		}

		// Try EDD customer address function first
		if ( function_exists( 'edd_get_customer_address' ) ) {
			$address = edd_get_customer_address( $user_id );
			if ( ! empty( $address ) && is_array( $address ) ) {
				return $this->normalize_address_data( $address );
			}
		}

		// Try EDD customer object
		if ( function_exists( 'edd_get_customer_by' ) ) {
			$customer = edd_get_customer_by( 'user_id', $user_id );
			if ( $customer && method_exists( $customer, 'get_address' ) ) {
				$customer_address = $customer->get_address();
				if ( $customer_address ) {
					return array(
						'line1'   => isset( $customer_address->address ) ? $customer_address->address : '',
						'line2'   => isset( $customer_address->address2 ) ? $customer_address->address2 : '',
						'city'    => isset( $customer_address->city ) ? $customer_address->city : '',
						'state'   => isset( $customer_address->region ) ? $customer_address->region : '',
						'country' => isset( $customer_address->country ) ? $customer_address->country : '',
						'zip'     => isset( $customer_address->postal_code ) ? $customer_address->postal_code : '',
						'phone'   => isset( $customer_address->phone ) ? $customer_address->phone : '',
					);
				}
			}
		}

		// Try recent orders
		if ( function_exists( 'edd_get_orders' ) ) {
			$orders = edd_get_orders(
				array(
					'user_id' => $user_id,
					'number'  => 1,
					'orderby' => 'date_created',
					'order'   => 'DESC',
				)
			);

			if ( ! empty( $orders ) && isset( $orders[0]->address ) ) {
				$address = $orders[0]->address;
				return array(
					'line1'   => isset( $address->address ) ? $address->address : '',
					'line2'   => isset( $address->address2 ) ? $address->address2 : '',
					'city'    => isset( $address->city ) ? $address->city : '',
					'state'   => isset( $address->region ) ? $address->region : '',
					'country' => isset( $address->country ) ? $address->country : '',
					'zip'     => isset( $address->postal_code ) ? $address->postal_code : '',
					'phone'   => isset( $address->phone ) ? $address->phone : '',
				);
			}
		}

		// Fall back to EDD user meta fields
		return array(
			'line1'   => get_user_meta( $user_id, '_edd_user_address', true ),
			'line2'   => get_user_meta( $user_id, '_edd_user_address_2', true ),
			'city'    => get_user_meta( $user_id, '_edd_user_city', true ),
			'state'   => get_user_meta( $user_id, '_edd_user_state', true ),
			'country' => get_user_meta( $user_id, '_edd_user_country', true ),
			'zip'     => get_user_meta( $user_id, '_edd_user_zip', true ),
			'phone'   => get_user_meta( $user_id, '_edd_user_phone', true ),
		);
	}

	/**
	 * Normalize address data to standard format.
	 *
	 * @param array $address_data Raw address data.
	 * @return array Normalized address data.
	 */
	private function normalize_address_data( $address_data ) {
		return array(
			'line1'   => isset( $address_data['line1'] ) ? $address_data['line1'] : ( isset( $address_data['address_line_1'] ) ? $address_data['address_line_1'] : '' ),
			'line2'   => isset( $address_data['line2'] ) ? $address_data['line2'] : ( isset( $address_data['address_line_2'] ) ? $address_data['address_line_2'] : '' ),
			'city'    => isset( $address_data['city'] ) ? $address_data['city'] : '',
			'state'   => isset( $address_data['state'] ) ? $address_data['state'] : '',
			'country' => isset( $address_data['country'] ) ? $address_data['country'] : '',
			'zip'     => isset( $address_data['zip'] ) ? $address_data['zip'] : '',
			'phone'   => isset( $address_data['phone'] ) ? $address_data['phone'] : '',
		);
	}

	/**
	 * Get empty address array with all required keys.
	 *
	 * @return array Empty address array.
	 */
	private function get_empty_address_array() {
		return array(
			'line1'   => '',
			'line2'   => '',
			'city'    => '',
			'state'   => '',
			'country' => '',
			'zip'     => '',
			'phone'   => '',
		);
	}

	/**
	 * All EDD downloads options.
	 *
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_edd_downloads( $label = null, $option_code = 'EDDPRODUCTS', $any_option = true, $is_relevant_tokens = true, $is_recurring = false ) {

		if ( ! $label ) {
			$label = esc_html_x( 'Download', 'Easy Digital Downloads', 'uncanny-automator' );
		}

		$args = array(
			'post_type'      => 'download',
			'posts_per_page' => 9999, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$downloads = get_posts( $args );

		$all_downloads = array();

		if ( $any_option ) {
			$all_downloads[] = array(
				'text'  => esc_html_x( 'Any download', 'Easy Digital Downloads', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		if ( $downloads ) {
			foreach ( $downloads as $download ) {

				$download_id = $download->ID;

				// Just list everything
				if ( ! $is_recurring ) {
					$all_downloads[] = array(
						'text'  => $download->post_title,
						'value' => $download_id,
					);
					continue;
				}

				// Check if the product has a recurring option
				if ( edd_recurring()->is_recurring( $download_id ) ) {
					$all_downloads[] = array(
						'text'  => $download->post_title,
						'value' => $download_id,
					);
					continue;
				}

				if ( ! edd_has_variable_prices( $download_id ) ) {
					continue;
				}

				$variable_prices = edd_get_variable_prices( $download_id );

				if ( $variable_prices ) {
					foreach ( $variable_prices as $price ) {
						if ( ! isset( $price['recurring'] ) || 'yes' !== $price['recurring'] ) {
							continue;
						}
						$all_downloads[] = array(
							'text'  => $download->post_title,
							'value' => $download_id,
						);
					}
				}
			}
		}

		$options = $all_downloads;

		$relevant_tokens = array(
			$option_code . '_DISCOUNT_CODES'  => esc_html_x( 'Discount codes used', 'Easy Digital Downloads', 'uncanny-automator' ),
			$option_code                      => esc_html_x( 'Download title', 'Easy Digital Downloads', 'uncanny-automator' ),
			$option_code . '_ID'              => esc_html_x( 'Download ID', 'Easy Digital Downloads', 'uncanny-automator' ),
			$option_code . '_URL'             => esc_html_x( 'Download URL', 'Easy Digital Downloads', 'uncanny-automator' ),
			$option_code . '_THUMB_ID'        => esc_html_x( 'Download featured image ID', 'Easy Digital Downloads', 'uncanny-automator' ),
			$option_code . '_THUMB_URL'       => esc_html_x( 'Download featured image URL', 'Easy Digital Downloads', 'uncanny-automator' ),
			$option_code . '_LICENSE_KEY'     => esc_html_x( 'License key', 'Easy Digital Downloads', 'uncanny-automator' ),
			$option_code . '_ORDER_DISCOUNTS' => esc_html_x( 'Order discounts', 'Easy Digital Downloads', 'uncanny-automator' ),
			$option_code . '_ORDER_SUBTOTAL'  => esc_html_x( 'Order subtotal', 'Easy Digital Downloads', 'uncanny-automator' ),
			$option_code . '_ORDER_TAX'       => esc_html_x( 'Order tax', 'Easy Digital Downloads', 'uncanny-automator' ),
			$option_code . '_ORDER_TOTAL'     => esc_html_x( 'Order total', 'Easy Digital Downloads', 'uncanny-automator' ),
			$option_code . '_PAYMENT_METHOD'  => esc_html_x( 'Payment method', 'Easy Digital Downloads', 'uncanny-automator' ),
		);

		// Disable the token if software licensing plugin is not active.
		if ( ! class_exists( '\EDD_Software_Licensing' ) ) {
			unset( $relevant_tokens[ $option_code . '_LICENSE_KEY' ] );
		}

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => ( false === $is_relevant_tokens ) ? array() : $relevant_tokens,
		);

		return apply_filters( 'uap_option_all_edd_downloads', $option );
	}

	/**
	 * Get price options for a specific download
	 *
	 * @param int|string $download_id The download ID or -1 for any download.
	 * @param bool $include_any Whether to include "Any price option".
	 * @return array Array of price options in modern format.
	 */
	public function get_download_price_options( $download_id, $include_any = true ) {
		$options = array();

		// Handle special cases: -1 or 'automator_custom_value'
		if ( intval( '-1' ) === intval( $download_id ) || 'automator_custom_value' === $download_id ) {
			if ( $include_any ) {
				$options[] = array(
					'value' => -1,
					'text'  => esc_html_x( 'Any price option', 'Easy Digital Downloads', 'uncanny-automator' ),
				);
			}
			return $options;
		}

		// Only proceed if we have a valid positive download ID
		$download_id = absint( $download_id );
		if ( $download_id <= 0 ) {
			return $options;
		}

		$download = get_post( $download_id );
		if ( ! $download ) {
			return $options;
		}

		// Always include "Any price option" for specific downloads
		if ( $include_any ) {
			$options[] = array(
				'value' => -1,
				'text'  => esc_html_x( 'Any price option', 'Easy Digital Downloads', 'uncanny-automator' ),
			);
		}

		// Check if the download has variable prices
		if ( edd_has_variable_prices( $download_id ) ) {
			$variable_prices = edd_get_variable_prices( $download_id );

			if ( $variable_prices ) {
				foreach ( $variable_prices as $price_id => $price ) {
					$price_name = isset( $price['name'] ) ? $price['name'] : sprintf(
						// translators: %d is the price option ID
						esc_html_x( 'Price option %d', 'Easy Digital Downloads', 'uncanny-automator' ),
						$price_id
					);
					$options[] = array(
						'value' => $price_id,
						'text'  => $price_name,
					);
				}
			}
		}

		return $options;
	}

	/**
	 * AJAX handler for price options (following Thrive Apprentice pattern).
	 */
	public function get_download_price_options_ajax_handler() {
		Automator()->utilities->ajax_auth_check();

		$values = filter_input_array(
			INPUT_POST,
			array(
				'values'  => array(
					'filter' => FILTER_SANITIZE_STRING,
					'flags'  => FILTER_REQUIRE_ARRAY,
				),
				'item_id' => array(
					'filter' => FILTER_SANITIZE_NUMBER_INT,
				),
			)
		);

		$download_id = isset( $values['values']['EDDPRODUCT'] ) ? $values['values']['EDDPRODUCT'] : 0;
		$item_id     = isset( $values['item_id'] ) ? absint( $values['item_id'] ) : 0;

		// For uo-action type, we don't want to include the "Any" option
		$include_any = 'uo-action' !== get_post_type( $item_id );

		// Get price options for the selected download
		$options = $this->get_download_price_options( $download_id, $include_any );

		echo wp_json_encode(
			array(
				'success' => true,
				'options' => $options,
			)
		);
		die();
	}



	/**
	 * Get the licenses of the order.
	 *
	 * @param int $order_id The payment ID.
	 *
	 * @return string The licenses.
	 */
	public function get_licenses( $order_id = 0 ) {

		if ( ! class_exists( '\EDD_Software_Licensing' ) ) {
			return '';
		}

		$order_licenses = array();

		$edd_license = \EDD_Software_Licensing::instance();

		$args['payment_id'] = $order_id;

		$licenses = (array) $edd_license->licenses_db->get_licenses( $args );

		if ( ! empty( $licenses ) ) {

			foreach ( $licenses as $license ) {

				$order_licenses[] = $license->license_key;

			}
		}

		return implode( ', ', $order_licenses );
	}
}
