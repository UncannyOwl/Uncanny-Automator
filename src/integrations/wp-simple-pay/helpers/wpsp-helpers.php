<?php

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Wpsp_Pro_Helpers;

/**
 * Class Wpsp_Helpers
 *
 * @package Uncanny_Automator
 */
class Wpsp_Helpers {

	/**
	 * @var Wpsp_Helpers
	 */
	public $options;

	/**
	 * @var Wpsp_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Wpsp_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param Wpsp_Helpers $options
	 */
	public function setOptions( Wpsp_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Wpsp_Pro_Helpers $pro
	 */
	public function setPro( Wpsp_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array  $args
	 *
	 * @return mixed
	 */

	public function list_wp_simpay_forms( $label = null, $option_code = 'WPSIMPAYFORMS', $args = array() ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Form', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$any_option   = key_exists( 'is_any', $args ) ? $args['is_any'] : false;

		$args = array(
			'post_type'      => 'simple-pay',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any form', 'uncanny-automator' ) );

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
			'relevant_tokens' => array(
				$option_code          => esc_attr__( 'Form title', 'uncanny-automator' ),
				$option_code . '_ID'  => esc_attr__( 'Form ID', 'uncanny-automator' ),
				$option_code . '_URL' => esc_attr__( 'Form URL', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_list_wpjm_job_types', $option );
	}
}
