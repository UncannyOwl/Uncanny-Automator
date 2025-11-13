<?php

namespace Uncanny_Automator\Integrations\Edd_Recurring_Integration;

use Uncanny_Automator_Pro\Integrations\Edd_Recurring_Pro_Helpers;

/**
 * Class Edd_Recurring_Helpers
 *
 * @package Uncanny_Automator
 */
class Edd_Recurring_Helpers {

	/**
	 * All EDD downloads options.
	 *
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_recurring_edd_downloads( $label = null, $option_code = 'EDDPRODUCTS', $any_option = true ) {

		if ( ! $label ) {
			$label = esc_html_x( 'Download', 'EDD - Recurring Payments', 'uncanny-automator' );
		}

		$args = array(
			'post_type'      => 'download',
			'posts_per_page' => 9999, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$downloads = get_posts( $args );

		$all_downloads       = array();
		$processed_downloads = array(); // Track already processed downloads

		if ( $any_option ) {
			$all_downloads[] = array(
				'text'  => esc_html_x( 'Any download', 'EDD - Recurring Payments', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		if ( $downloads ) {
			foreach ( $downloads as $download ) {

				$download_id = $download->ID;

				// Skip if already processed
				if ( in_array( $download_id, $processed_downloads, true ) ) {
					continue;
				}

				// Check if the product has a recurring option
				if ( edd_recurring()->is_recurring( $download_id ) ) {
					$all_downloads[]       = array(
						'text'  => $download->post_title,
						'value' => $download_id,
					);
					$processed_downloads[] = $download_id;
					continue;
				}

				if ( ! edd_has_variable_prices( $download_id ) ) {
					continue;
				}

				$variable_prices = edd_get_variable_prices( $download_id );

				if ( $variable_prices ) {
					foreach ( $variable_prices as $price ) {
						if ( isset( $price['recurring'] ) && 'yes' === $price['recurring'] ) {
							$all_downloads[]       = array(
								'text'  => $download->post_title,
								'value' => $download_id,
							);
							$processed_downloads[] = $download_id;
							break; // Found one recurring option, add product and move on
						}
					}
				}
			}
		}

		// Clear the processed list for memory efficiency
		unset( $processed_downloads );

		$options = $all_downloads;

		$relevant_tokens = array(
			$option_code . '_DISCOUNT_CODES'  => esc_html_x( 'Discount codes used', 'EDD - Recurring Payments', 'uncanny-automator' ),
			$option_code                      => esc_html_x( 'Download title', 'EDD - Recurring Payments', 'uncanny-automator' ),
			$option_code . '_ID'              => esc_html_x( 'Download ID', 'EDD - Recurring Payments', 'uncanny-automator' ),
			$option_code . '_URL'             => esc_html_x( 'Download URL', 'EDD - Recurring Payments', 'uncanny-automator' ),
			$option_code . '_THUMB_ID'        => esc_html_x( 'Download featured image ID', 'EDD - Recurring Payments', 'uncanny-automator' ),
			$option_code . '_THUMB_URL'       => esc_html_x( 'Download featured image URL', 'EDD - Recurring Payments', 'uncanny-automator' ),
			$option_code . '_LICENSE_KEY'     => esc_html_x( 'License key', 'EDD - Recurring Payments', 'uncanny-automator' ),
			$option_code . '_ORDER_DISCOUNTS' => esc_html_x( 'Order discounts', 'EDD - Recurring Payments', 'uncanny-automator' ),
			$option_code . '_ORDER_SUBTOTAL'  => esc_html_x( 'Order subtotal', 'EDD - Recurring Payments', 'uncanny-automator' ),
			$option_code . '_ORDER_TAX'       => esc_html_x( 'Order tax', 'EDD - Recurring Payments', 'uncanny-automator' ),
			$option_code . '_ORDER_TOTAL'     => esc_html_x( 'Order total', 'EDD - Recurring Payments', 'uncanny-automator' ),
			$option_code . '_PAYMENT_METHOD'  => esc_html_x( 'Payment method', 'EDD - Recurring Payments', 'uncanny-automator' ),
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
			'relevant_tokens' => $relevant_tokens,
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
					'text'  => esc_html_x( 'Any price option', 'EDD - Recurring Payments', 'uncanny-automator' ),
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
				'text'  => esc_html_x( 'Any price option', 'EDD - Recurring Payments', 'uncanny-automator' ),
			);
		}

		// Check if the download has variable prices
		if ( edd_has_variable_prices( $download_id ) ) {
			$variable_prices = edd_get_variable_prices( $download_id );

			if ( $variable_prices ) {
				foreach ( $variable_prices as $price_id => $price ) {
					$price_name = isset( $price['name'] ) ? $price['name'] : sprintf(
						// translators: %d is the price option ID
						esc_html_x( 'Price option %d', 'EDD - Recurring Payments', 'uncanny-automator' ),
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
}
