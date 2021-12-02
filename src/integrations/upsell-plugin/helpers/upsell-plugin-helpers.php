<?php

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Upsell_Plugin_Pro_Helpers;

/**
 * Class Upsell_Plugin_Helpers
 *
 * @package Uncanny_Automator
 */
class Upsell_Plugin_Helpers {

	/**
	 * @var Upsell_Plugin_Helpers
	 */
	public $options;

	/**
	 * @var Upsell_Plugin_Pro_Helpers
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

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param Upsell_Plugin_Helpers $options
	 */
	public function setOptions( Upsell_Plugin_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Upsell_Plugin_Pro_Helpers $pro
	 */
	public function setPro( Upsell_Plugin_Pro_Helpers $pro ) {
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

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Product', 'uncanny-automator' );
		}

		$args = array(
			'post_type'      => 'upsell_product',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args, true, esc_attr__( 'Any product', 'uncanny-automator' ) );

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => array(
				$option_code                => esc_attr__( 'Product title', 'uncanny-automator' ),
				$option_code . '_ID'        => esc_attr__( 'Product ID', 'uncanny-automator' ),
				$option_code . '_URL'       => esc_attr__( 'Product URL', 'uncanny-automator' ),
				$option_code . '_THUMB_ID'  => esc_attr__( 'Product featured image ID', 'uncanny-automator' ),
				$option_code . '_THUMB_URL' => esc_attr__( 'Product featured image URL', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_all_upsell_products', $option );
	}

}
