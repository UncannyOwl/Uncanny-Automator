<?php

namespace Uncanny_Automator;

/**
 * Class Ultimate_Member_Helpers
 * @package Uncanny_Automator
 */
class Ultimate_Member_Helpers {
	/**
	 * @var Ultimate_Member_Helpers
	 */
	public $options;

	/**
	 * @var \Uncanny_Automator_Pro\Ultimate_Member_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * @param Ultimate_Member_Helpers $options
	 */
	public function setOptions( Ultimate_Member_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param \Uncanny_Automator_Pro\Ultimate_Member_Pro_Helpers $pro
	 */
	public function setPro( \Uncanny_Automator_Pro\Ultimate_Member_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * Ultimate_Member_Helpers constructor.
	 */
	public function __construct() {
		global $uncanny_automator;
		$this->load_options = $uncanny_automator->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param null $label
	 * @param string $option_code
	 * @param string $type
	 *
	 * @return mixed|void
	 */
	public function get_um_forms( $label = null, $option_code = 'UMFORM', $type = 'register', $params = [] ) {
		if ( ! $this->load_options ) {
			global $uncanny_automator;

			return $uncanny_automator->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = __( 'Form', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $params ) ? $params['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $params ) ? $params['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $params ) ? $params['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $params ) ? $params['endpoint'] : '';
		$any          = key_exists( 'any', $params ) ? $params['any'] : true;

		$options = [];
		if ( $any ) {
			$options['-1'] = __( 'Any form', 'uncanny-automator' );
		}
		$args = array(
			'posts_per_page'   => 999,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'post_type'        => 'um_form',
			'post_status'      => 'publish',
			'suppress_filters' => true,
			'fields'           => array( 'ids', 'titles' ),
		);

		if ( 'any' !== (string) $type ) {
			$args['meta_query'] = [
				[
					'key'     => '_um_mode',
					'value'   => $type,
					'compare' => 'LIKE'
				]
			];
		}

		//$forms_list = get_posts( $args );
		global $uncanny_automator;
		$forms_list = $uncanny_automator->helpers->recipe->options->wp_query( $args );
		/*if ( ! empty( $forms_list ) ) {
			foreach ( $forms_list as $form ) {
				// Check if the form title is defined
				$post_title           = ! empty( $form->post_title ) ? $form->post_title : sprintf( __( 'ID: %1$s (no title)', 'uncanny-automator' ), $form->ID );
				$options[ $form->ID ] = $post_title;
			}
		}*/


		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $forms_list,
			'relevant_tokens' => [
				$option_code         => __( 'Form title', 'uncanny-automator' ),
				$option_code . '_ID' => __( 'Form ID', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_um_forms', $option );
	}
}