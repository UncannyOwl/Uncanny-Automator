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
	public $load_options;

	public function __construct() {

		$this->load_options = true;

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
	public function all_edd_downloads( $label = null, $option_code = 'EDDPRODUCTS', $any_option = true ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Product', 'uncanny-automator' );
		}

		$args = array(
			'post_type'      => 'download',
			'posts_per_page' => 9999, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any download', 'uncanny-automator' ) );

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
			'relevant_tokens' => $relevant_tokens,
		);

		return apply_filters( 'uap_option_all_edd_downloads', $option );
	}

	/**
	 * Get the licenses of the order.
	 *
	 * @param int $order_id The payment ID.
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
