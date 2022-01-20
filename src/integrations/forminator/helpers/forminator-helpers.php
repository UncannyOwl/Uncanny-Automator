<?php


namespace Uncanny_Automator;

use Forminator_API;
use Uncanny_Automator_Pro\Forminator_Pro_Helpers;

/**
 * Class Forminator_Helpers
 *
 * @package Uncanny_Automator
 */
class Forminator_Helpers {
	/**
	 * @var Forminator_Helpers
	 */
	public $options;

	/**
	 * @var Forminator_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Forminator_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param Forminator_Helpers $options
	 */
	public function setOptions( Forminator_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Forminator_Pro_Helpers $pro
	 */
	public function setPro( Forminator_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function all_forminator_forms( $label = null, $option_code = 'FRFORMS', $args = array() ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Form', 'uncanny-automator' );
		}

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => esc_attr__( 'Any form', 'uncanny-automator' ),
			)
		);

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = array();

		if ( Automator()->helpers->recipe->load_helpers ) {
			if ( $args['uo_include_any'] ) {
				$options[ - 1 ] = $args['uo_any_label'];
			}
			$forms = Forminator_API::get_forms( null, 1, 999 );
			if ( ! empty( $forms ) ) {
				foreach ( $forms as $form ) {
					$options[ $form->id ] = isset( $form->settings ) && isset( $form->settings['form_name'] ) ? $form->settings['form_name'] : $form->name;
				}
			}
		}
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
				$option_code         => esc_attr__( 'Form title', 'uncanny-automator' ),
				$option_code . '_ID' => esc_attr__( 'Form ID', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_all_forminator_forms', $option );
	}
}
