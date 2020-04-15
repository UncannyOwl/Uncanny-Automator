<?php


namespace Uncanny_Automator;


/**
 * Class Wpforms_Helpers
 * @package Uncanny_Automator
 */
class Wpforms_Helpers {
	/**
	 * @var Wpforms_Helpers
	 */
	public $options;

	/**
	 * @var \Uncanny_Automator_Pro\Wpforms_Pro_Helpers
	 */
	public $pro;

	/**
	 * @param Wpforms_Helpers $options
	 */
	public function setOptions( Wpforms_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param \Uncanny_Automator_Pro\Wpforms_Pro_Helpers $pro
	 */
	public function setPro( \Uncanny_Automator_Pro\Wpforms_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */

	public function list_wp_forms( $label = null, $option_code = 'WPFFORMS', $args = [] ) {

		global $uncanny_automator;
		if ( ! $label ) {
			$label = __( 'Select a WP Form', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = [];

		if ( $uncanny_automator->helpers->recipe->load_helpers ) {
			$wpforms = new \WPForms_Form_Handler();

			$forms = $wpforms->get( '', [
				'orderby' => 'title',
			] );

			if ( ! empty( $forms ) ) {
				foreach ( $forms as $form ) {
					$options[ $form->ID ] = esc_html( $form->post_title );
				}
			}
		}
		$type = 'select';

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
			'placeholder'     => __( 'Select a form', 'uncanny-automator' ),
		];

		return apply_filters( 'uap_option_list_wp_forms', $option );
	}
}