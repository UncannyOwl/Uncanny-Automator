<?php


namespace Uncanny_Automator;

/**
 * Class Forminator_Helpers
 * @package Uncanny_Automator
 */
class Forminator_Helpers {
	/**
	 * @var Forminator_Helpers
	 */
	public $options;

	/**
	 * @var \Uncanny_Automator_Pro\Forminator_Pro_Helpers
	 */
	public $pro;

	/**
	 * @param Forminator_Helpers $options
	 */
	public function setOptions( Forminator_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param \Uncanny_Automator_Pro\Forminator_Pro_Helpers $pro
	 */
	public function setPro( \Uncanny_Automator_Pro\Forminator_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function all_forminator_forms( $label = null, $option_code = 'FRFORMS', $args = [] ) {
		if ( ! $label ) {
			$label = __( 'Form', 'uncanny-automator' );
		}

		$args = wp_parse_args( $args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => __( 'Any form', 'uncanny-automator' ),
			)
		);

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = [];
		global $uncanny_automator;
		if ( $uncanny_automator->helpers->recipe->load_helpers ) {
			if ( $args['uo_include_any'] ) {
				$options[-1] = $args['uo_any_label'];
			}
			$forms = \Forminator_API::get_forms(null, 1, 999);

			//$forms = forminator_cform_modules( 999, 'publish' );
			if ( ! empty( $forms ) ) {
				foreach ( $forms as $form ) {
					$options[ $form->id ] = $form->name;
				}
			}
		}
		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
		];

		return apply_filters( 'uap_option_all_forminator_forms', $option );
	}
}