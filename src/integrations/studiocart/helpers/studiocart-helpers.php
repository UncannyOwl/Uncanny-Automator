<?php

namespace Uncanny_Automator;

/**
 * Studiocart integration helper file
 */
class Studiocart_Helpers {
	/**
	 * Store options
	 *
	 * @var Studiocart_Helpers
	 */
	public $options;

	public $load_options = true;

	/**
	 * Store Studiocart Pro Helper instance
	 *
	 * @var Studiocart_Pro_Helpers
	 */
	public $pro;

	/**
	 * Studiocart_Helpers constructor.
	 */
	public function __construct() {
	}

	/**
	 * Set Studiocart options
	 *
	 * @param Studiocart_Helpers $options
	 */
	public function setOptions( Studiocart_Helpers $options ) { //phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->options = $options;
	}

	/**
	 * Set Studiocart Pro Helper instance
	 *
	 * @param Studiocart_Pro_Helpers $pro
	 */
	public function setPro( \Uncanny_Automator_Pro\Studiocart_Pro_Helpers $pro ) { //phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->pro = $pro;
	}

	/**
	 * Fetch all products
	 *
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function all_products( $label = null, $option_code = 'STUDIOCARTPRODUCTS', $args = array() ) {

		$label = null === $label ? esc_attr__( 'Product', 'uncanny-automator' ) : $label;

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => esc_attr__( 'Any product', 'uncanny-automator' ),
			)
		);

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = array();
		$option       = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
		);

		if ( ! Automator()->helpers->recipe->load_helpers ) {
			return apply_filters( 'uap_option_all_studiocart_products', $option );
		}

		$args = array(
			'post_type'      => 'sc_product',
			'posts_per_page' => 999, //phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
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
			'relevant_tokens' => array(),
		);

		$option['options'] = $options;

		return apply_filters( 'uap_option_all_studiocart_products', $option );
	}
}
