<?php

namespace Uncanny_Automator;

use GFFormsModel;
use Uncanny_Automator_Pro\Gravity_Forms_Pro_Helpers;

/**
 * Class Gravity_Forms_Helpers
 * @package Uncanny_Automator
 */
class Gravity_Forms_Helpers {
	/**
	 * @var Gravity_Forms_Helpers
	 */
	public $options;

	/**
	 * @var Gravity_Forms_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Gravity_Forms_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param Gravity_Forms_Helpers $options
	 */
	public function setOptions( Gravity_Forms_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Gravity_Forms_Pro_Helpers $pro
	 */
	public function setPro( Gravity_Forms_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array  $args
	 *
	 * @return mixed
	 */
	public function list_gravity_forms( $label = null, $option_code = 'GFFORMS', $args = array() ) {
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
		$options      = array();


		if ( Automator()->helpers->recipe->load_helpers ) {
			$forms = GFFormsModel::get_forms();

			foreach ( $forms as $form ) {
				$options[ $form->id ] = esc_html( $form->title );
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

		return apply_filters( 'uap_option_list_gravity_forms', $option );

	}

}
