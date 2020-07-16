<?php

namespace Uncanny_Automator;

/**
 * Class Upsell_Plugin_Helpers
 * @package Uncanny_Automator
 */
class Upsell_Plugin_Helpers {

	/**
	 * @var Upsell_Plugin_Helpers
	 */
	public $options;

	/**
	 * @var \Uncanny_Automator_Pro\Upsell_Plugin_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;


	/**
	 * Upsell_Plugin_Helpers constructor.
	 */
	public function __construct() {
		global $uncanny_automator;
		$this->load_options = $uncanny_automator->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param Upsell_Plugin_Helpers $options
	 */
	public function setOptions( Upsell_Plugin_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param \Uncanny_Automator_Pro\Upsell_Plugin_Pro_Helpers $pro
	 */
	public function setPro( \Uncanny_Automator_Pro\Upsell_Plugin_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_upsell_products( $label = null, $option_code = 'USPRODUCT' ) {
		if ( ! $this->load_options ) {
			global $uncanny_automator;

			return $uncanny_automator->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = __( 'Product', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'upsell_product',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		global $uncanny_automator;
		$options = $uncanny_automator->helpers->recipe->options->wp_query( $args, true, __( 'Any product', 'uncanny-automator' ) );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          => __( 'Product title', 'uncanny-automator' ),
				$option_code . '_ID'  => __( 'Product ID', 'uncanny-automator' ),
				$option_code . '_URL' => __( 'Product URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_upsell_products', $option );
	}

}