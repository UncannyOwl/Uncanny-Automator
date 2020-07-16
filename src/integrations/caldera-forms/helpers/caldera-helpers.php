<?php


namespace Uncanny_Automator;

/**
 * Class Caldera_Helpers
 * @package Uncanny_Automator
 */
class Caldera_Helpers {
	/**
	 * @var Caldera_Helpers
	 */
	public $options;

	/**
	 * @var \Uncanny_Automator_Pro\Caldera_Forms_Pro_Helpers
	 */
	public $pro;

	/**
	 * @param \Uncanny_Automator_Pro\Caldera_Forms_Pro_Helpers $pro
	 */
	public function setPro( \Uncanny_Automator_Pro\Caldera_Forms_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param Caldera_Helpers $options
	 */
	public function setOptions( Caldera_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function list_caldera_forms_forms( $label = null, $option_code = 'CFFORMS', $args = [] ) {

		if ( ! $label ) {
			$label = __( 'Form', 'uncanny-automator' );
		}
		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = [];
		global $uncanny_automator;
		if ( $uncanny_automator->helpers->recipe->load_helpers ) {
			$forms = \Caldera_Forms_Forms::get_forms( true );

			if ( ! empty( $forms ) ) {
				foreach ( $forms as $form ) {
					$options[ $form['ID'] ] = $form['name'];
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
		];

		return apply_filters( 'uap_option_list_caldera_forms_forms', $option );
	}
}