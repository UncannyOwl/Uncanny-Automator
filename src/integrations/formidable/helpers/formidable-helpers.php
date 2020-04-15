<?php


namespace Uncanny_Automator;

/**
 * Class Formidable_Helpers
 * @package Uncanny_Automator
 */
class Formidable_Helpers {
	/**
	 * @var Formidable_Helpers
	 */
	public $options;

	/**
	 * @var \Uncanny_Automator_Pro\Formidable_Pro_Helpers
	 */
	public $pro;

	/**
	 * @param Formidable_Helpers $options
	 */
	public function setOptions( Formidable_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param \Uncanny_Automator_Pro\Formidable_Pro_Helpers $pro
	 */
	public function setPro( \Uncanny_Automator_Pro\Formidable_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function all_formidable_forms( $label = null, $option_code = 'FIFORMS', $args = [] ) {
		if ( ! $label ) {
			$label = __( 'Select a form', 'uncanny-automator' );
		}

		$args = wp_parse_args( $args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => __( 'Any product', 'uncanny-automator' ),
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
				$options[ - 1 ] = $args['uo_any_label'];
			}
			$s_query                = [
				[
					'or'               => 1,
					'parent_form_id'   => null,
					'parent_form_id <' => 1,
				],
			];
			$s_query['is_template'] = 0;
			$s_query['status !']    = 'trash';
			$forms                  = \FrmForm::getAll( $s_query, '', ' 0, 999' );

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
			'placeholder'     => __( 'Select a form', 'uncanny-automator' ),
		];

		return apply_filters( 'uap_option_all_formidable_forms', $option );
	}
}