<?php

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Wp_User_Manager_Pro_Helpers;

/**
 * Class Wp_User_Manager_Helpers
 * @package Uncanny_Automator
 */
class Wp_User_Manager_Helpers {

	/**
	 * @var Wp_User_Manager_Helpers
	 */
	public $options;
	/**
	 * @var Wp_User_Manager_Helpers
	 */
	public $pro;
	/**
	 * @var bool
	 */
	public $load_options;


	/**
	 * Wp_User_Manager_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );

	}

	/**
	 * @param Wp_User_Manager_Helpers $options
	 */
	public function setOptions( Wp_User_Manager_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Wp_User_Manager_Pro_Helpers $pro
	 */
	public function setPro( Wp_User_Manager_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param null   $label
	 * @param string $option_code
	 * @param array  $args
	 *
	 * @return array|mixed|void
	 */
	public function get_all_forms( $label = null, $option_code = 'WPUMFORMS', $args = array() ) {
		if ( ! $this->load_options ) {


			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Form', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$is_any       = key_exists( 'is_any', $args ) ? $args['is_any'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = array();

		if ( $is_any ) {
			$options['-1'] = __( 'Any form', 'uncanny-automator' );
		}

		$forms = wpumrf_registration_forms();

		if ( is_array( $forms ) && ! empty( $forms ) ) {
			foreach ( $forms as $key => $form ) {
				$options[ $key ] = $form;
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

		return apply_filters( 'uap_option_get_all_forms', $option );
	}
}
