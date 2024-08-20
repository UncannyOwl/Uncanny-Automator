<?php

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Edd_Pro_Helpers;

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
	 * EDD pro helpers.
	 *
	 * @var Edd_Pro_Helpers
	 */
	public $pro;

	/**
	 * Load options.
	 *
	 * @var bool
	 */
	public $load_options = true;

	public function __construct() {

	}

	/**
	 * Set options.
	 *
	 * @param Edd_Helpers $options
	 */
	public function setOptions( Edd_Helpers $options ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->options = $options;
	}

	/**
	 * Set pro.
	 *
	 * @param Edd_Pro_Helpers $pro
	 */
	public function setPro( Edd_Pro_Helpers $pro ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->pro = $pro;
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
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Download', 'uncanny-automator' );
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

		if ( $downloads ) {
			foreach ( $downloads as $download ) {

				$download_id = $download->ID;

				// Just list everything
				if ( ! $is_recurring ) {
					$all_downloads[ $download_id ] = $download->post_title;
					continue;
				}

				// Check if the product has a recurring option
				if ( edd_recurring()->is_recurring( $download_id ) ) {
					$all_downloads[ $download_id ] = $download->post_title;
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
						$all_downloads[ $download_id ] = $download->post_title;
					}
				}
			}
		}

		$any = array();

		if ( $any_option ) {
			$any = array( '-1' => esc_attr__( 'Any download', 'uncanny-automator' ) );
		}

		$options = $any + $all_downloads;

		$relevant_tokens = array(
			$option_code . '_DISCOUNT_CODES'  => esc_attr__( 'Discount codes used', 'uncanny-automator' ),
			$option_code                      => esc_attr__( 'Download title', 'uncanny-automator' ),
			$option_code . '_ID'              => esc_attr__( 'Download ID', 'uncanny-automator' ),
			$option_code . '_URL'             => esc_attr__( 'Download URL', 'uncanny-automator' ),
			$option_code . '_THUMB_ID'        => esc_attr__( 'Download featured image ID', 'uncanny-automator' ),
			$option_code . '_THUMB_URL'       => esc_attr__( 'Download featured image URL', 'uncanny-automator' ),
			$option_code . '_LICENSE_KEY'     => esc_attr__( 'License key', 'uncanny-automator' ),
			$option_code . '_ORDER_DISCOUNTS' => esc_attr__( 'Order discounts', 'uncanny-automator' ),
			$option_code . '_ORDER_SUBTOTAL'  => esc_attr__( 'Order subtotal', 'uncanny-automator' ),
			$option_code . '_ORDER_TAX'       => esc_attr__( 'Order tax', 'uncanny-automator' ),
			$option_code . '_ORDER_TOTAL'     => esc_attr__( 'Order total', 'uncanny-automator' ),
			$option_code . '_PAYMENT_METHOD'  => esc_attr__( 'Payment method', 'uncanny-automator' ),
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
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
			'relevant_tokens' => ( false === $is_relevant_tokens ) ? array() : $relevant_tokens,
		);

		return apply_filters( 'uap_option_all_edd_downloads', $option );
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
