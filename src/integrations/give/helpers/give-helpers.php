<?php

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Give_Pro_Helpers;

/**
 * Class Give_Helpers
 * @package Uncanny_Automator
 */
class Give_Helpers {

	/**
	 * Give_Helpers constructor.
	 */
	public function __construct() {
	}

	/**
	 * @var Give_Helpers
	 */
	public $options;

	/**
	 * @param Give_Helpers $options
	 */
	public function setOptions( Give_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @var Give_Pro_Helpers
	 */
	public $pro;

	/**
	 * @param Give_Pro_Helpers $pro
	 */
	public function setPro( Give_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	public function list_all_give_forms( $label = null, $option_code = 'MAKEDONATION', $args = [] ) {

		global $uncanny_automator;

		if ( ! $label ) {
			$label = __( 'Form', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = [];

		$query_args = [
			'post_type'      => 'give_forms',
			'posts_per_page' => 9999,
			'post_status'    => 'publish'
		];
		$options    = $uncanny_automator->helpers->recipe->wp_query( $query_args, true, __( 'Any form', 'uncanny-automator' ) );
		$type       = 'select';

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => $type,
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
			'relevant_tokens' => [
				'ACTUALDONATEDAMOUNT' => esc_attr__( 'Donated Amount', 'uncanny-automator' ),
				$option_code          => esc_attr__( 'Form', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_list_all_give_forms', $option );
	}

}