<?php


namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Woocommerce_Pro_Helpers;

/**
 * Class Woocommerce_Helpers
 *
 * @package Uncanny_Automator
 */
class Woocommerce_Helpers {
	/**
	 * @var Woocommerce_Helpers
	 */
	public $options;

	/**
	 * @var Woocommerce_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 *
	 */
	public function __construct() {
		$this->load_options = true;
	}

	/**
	 * @param Woocommerce_Helpers $options
	 */
	public function setOptions( Woocommerce_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Woocommerce_Pro_Helpers $pro
	 */
	public function setPro( Woocommerce_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return array
	 */
	public function all_wc_products( $label = null, $option_code = 'WOOPRODUCT' ) {

		$relevant_tokens                                = array(
			$option_code                                => esc_attr__( 'Product title', 'uncanny-automator' ),
			$option_code . '_ID'                        => esc_attr__( 'Product ID', 'uncanny-automator' ),
			$option_code . '_URL'                       => esc_attr__( 'Product URL', 'uncanny-automator' ),
			$option_code . '_THUMB_ID'                  => esc_attr__( 'Product featured image ID', 'uncanny-automator' ),
			$option_code . '_THUMB_URL'                 => esc_attr__( 'Product featured image URL', 'uncanny-automator' ),
			$option_code . '_PRODUCT_PRICE'             => esc_attr__( 'Product price', 'uncanny-automator' ),
			$option_code . '_PRODUCT_PRICE_UNFORMATTED' => esc_attr__( 'Product price (unformatted)', 'uncanny-automator' ),
			$option_code . '_PRODUCT_SALE_PRICE'        => esc_attr__( 'Product sale price', 'uncanny-automator' ),
			$option_code . '_PRODUCT_SALE_PRICE_UNFORMATTED' => esc_attr__( 'Product sale price (unformatted)', 'uncanny-automator' ),
		);
		$relevant_tokens[ $option_code . '_ORDER_QTY' ] = esc_attr__( 'Product quantity', 'uncanny-automator' );

		return $this->load_products( $label, $option_code, $relevant_tokens );
	}

	/**
	 * @param $label
	 * @param $option_code
	 *
	 * @return array
	 */
	public function all_wc_view_products( $label = null, $option_code = 'WOOPRODUCT' ) {
		$relevant_tokens                                 = array(
			$option_code                                => esc_attr__( 'Product title', 'uncanny-automator' ),
			$option_code . '_ID'                        => esc_attr__( 'Product ID', 'uncanny-automator' ),
			$option_code . '_URL'                       => esc_attr__( 'Product URL', 'uncanny-automator' ),
			$option_code . '_THUMB_ID'                  => esc_attr__( 'Product featured image ID', 'uncanny-automator' ),
			$option_code . '_THUMB_URL'                 => esc_attr__( 'Product featured image URL', 'uncanny-automator' ),
			$option_code . '_PRODUCT_PRICE'             => esc_attr__( 'Product price', 'uncanny-automator' ),
			$option_code . '_PRODUCT_PRICE_UNFORMATTED' => esc_attr__( 'Product price (unformatted)', 'uncanny-automator' ),
			$option_code . '_PRODUCT_SALE_PRICE'        => esc_attr__( 'Product sale price', 'uncanny-automator' ),
			$option_code . '_PRODUCT_SALE_PRICE_UNFORMATTED' => esc_attr__( 'Product sale price (unformatted)', 'uncanny-automator' ),
		);
		$relevant_tokens[ $option_code . '_SKU' ]        = esc_attr__( 'Product SKU', 'uncanny-automator' );
		$relevant_tokens[ $option_code . '_CATEGORIES' ] = esc_attr__( 'Product categories', 'uncanny-automator' );
		$relevant_tokens[ $option_code . '_TAGS' ]       = esc_attr__( 'Product tags', 'uncanny-automator' );

		return $this->load_products( $label, $option_code, $relevant_tokens );
	}

	/**
	 * @param $label
	 * @param $option_code
	 * @param $relevant_tokens
	 *
	 * @return mixed|null
	 */
	public function load_products( $label = null, $option_code = 'WOOPRODUCT', $relevant_tokens = array() ) {

		if ( ! $label ) {
			$label = esc_attr__( 'Product', 'uncanny-automator' );
		}

		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => 999999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args, true, esc_attr__( 'Any product', 'uncanny-automator' ) );
		$option  = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => $relevant_tokens,
		);

		return apply_filters( 'uap_option_all_wc_products', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function wc_order_statuses( $label = null, $option_code = 'WCORDERSTATUS' ) {
		if ( ! $this->load_options ) {
			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = 'Status';
		}

		$option = array(
			'option_code' => $option_code,
			'label'       => $label,
			'input_type'  => 'select',
			'required'    => true,
			'options'     => wc_get_order_statuses(),
		);

		return apply_filters( 'uap_option_woocommerce_statuses', $option );
	}

	/**
	 * @param string $code
	 *
	 * @return mixed|void
	 */
	public function get_woocommerce_trigger_conditions( $code = 'TRIGGERCOND' ) {
		$options = array(
			'option_code' => $code,
			/* translators: Noun */
			'label'       => esc_attr__( 'Trigger condition', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			'options'     => $this->get_trigger_condition_labels(),
		);

		return apply_filters( 'uap_option_woocommerce_trigger_conditions', $options );
	}

	/**
	 * Fetch labels for trigger conditions.
	 *
	 * @return array
	 * @since 2.10
	 */
	public function get_trigger_condition_labels() {
		/**
		 * Filters WooCommerce Integrations' trigger conditions.
		 *
		 * @param array $trigger_conditions An array of key-value pairs of action hook handle and human readable label.
		 */
		return apply_filters(
			'uap_wc_trigger_conditions',
			array(
				'woocommerce_payment_complete'       => _x( 'pays for', 'WooCommerce', 'uncanny-automator' ),
				'woocommerce_order_status_completed' => _x( 'completes', 'WooCommerce', 'uncanny-automator' ),
				'woocommerce_thankyou'               => _x( 'lands on a thank you page for', 'WooCommerce', 'uncanny-automator' ),
			)
		);
	}
}
