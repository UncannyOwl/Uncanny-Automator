<?php


namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Memberpress_Pro_Helpers;

/**
 * Class Memberpress_Helpers
 *
 * @package Uncanny_Automator
 */
class Memberpress_Helpers {
	/**
	 * @var Memberpress_Helpers
	 */
	public $options;

	/**
	 * @var Memberpress_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Memberpress_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );

	}

	/**
	 * @param Memberpress_Helpers $options
	 */
	public function setOptions( Memberpress_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Memberpress_Pro_Helpers $pro
	 */
	public function setPro( Memberpress_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function all_memberpress_products( $label = null, $option_code = 'MPPRODUCT', $args = array() ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Product', 'uncanny-automator' );
		}

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => esc_attr__( 'Any product', 'uncanny-automator' ),
			)
		);

		$options = array();

		if ( $args['uo_include_any'] ) {
			$options[ - 1 ] = $args['uo_any_label'];
		}

		$options = Automator()->helpers->recipe->options->wp_query( array( 'post_type' => 'memberpressproduct' ) );

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

		return apply_filters( 'uap_option_all_memberpress_products', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function all_memberpress_products_onetime( $label = null, $option_code = 'MPPRODUCT', $args = array() ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Product', 'uncanny-automator' );
		}

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => esc_attr__( 'Any one-time subscription product', 'uncanny-automator' ),
			)
		);

		$options = array();

		if ( $args['uo_include_any'] ) {
			$options[ - 1 ] = $args['uo_any_label'];
		}

		//$posts   = get_posts( );
		$query_args = array(
			'post_type'      => 'memberpressproduct',
			'posts_per_page' => 999,
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'     => '_mepr_product_period_type',
					'value'   => 'lifetime',
					'compare' => '=',
				),
			),
		);
		$options    = Automator()->helpers->recipe->wp_query( $query_args );

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

		return apply_filters( 'uap_option_all_memberpress_products_onetime', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function all_memberpress_products_recurring( $label = null, $option_code = 'MPPRODUCT', $args = array() ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Product', 'uncanny-automator' );
		}

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => esc_attr__( 'Any recurring subscription product', 'uncanny-automator' ),
			)
		);

		$options = array();

		if ( $args['uo_include_any'] ) {
			$options[ - 1 ] = $args['uo_any_label'];
		}

		$query_args = array(
			'post_type'      => 'memberpressproduct',
			'posts_per_page' => 999,
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'     => '_mepr_product_period_type',
					'value'   => 'lifetime',
					'compare' => '!=',
				),
			),
		);
		$options    = Automator()->helpers->recipe->wp_query( $query_args );

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
				$option_code . '_THUMB_ID'  => esc_attr__( 'Product featureed image ID', 'uncanny-automator' ),
				$option_code . '_THUMB_URL' => esc_attr__( 'Product featureed image URL', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_all_memberpress_products_recurring', $option );
	}

}
