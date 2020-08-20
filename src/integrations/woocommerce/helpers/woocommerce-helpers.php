<?php


namespace Uncanny_Automator;


use Uncanny_Automator_Pro\Woocommerce_Pro_Helpers;

/**
 * Class Woocommerce_Helpers
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

	public function __construct() {
		global $uncanny_automator;
		$this->load_options = $uncanny_automator->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
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
	 * @return mixed
	 */
	public function all_wc_products( $label = null, $option_code = 'WOOPRODUCT' ) {
		if ( ! $this->load_options ) {
			global $uncanny_automator;

			return $uncanny_automator->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label =  esc_attr__( 'Product', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'product',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		global $uncanny_automator;
		$options = $uncanny_automator->helpers->recipe->options->wp_query( $args, true,  esc_attr__( 'Any product', 'uncanny-automator' ) );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Product title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Product ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Product URL', 'uncanny-automator' ),
			],
		];

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
			global $uncanny_automator;

			return $uncanny_automator->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = 'Status';
		}

		$option = [
			'option_code' => $option_code,
			'label'       => $label,
			'input_type'  => 'select',
			'required'    => true,
			'options'     => wc_get_order_statuses(),
		];

		return apply_filters( 'uap_option_woocommerce_statuses', $option );
	}

}