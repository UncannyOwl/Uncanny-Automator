<?php

namespace Uncanny_Automator;

use GFFormsModel;
use Uncanny_Automator_Pro\Gravity_Forms_Pro_Helpers;
use Uncanny_Automator\Integrations\Gravity_Forms\GF_SUBFORM_CODES;
use Uncanny_Automator\Integrations\Gravity_Forms\GF_SUBFORM_GROUPS;

/**
 * Class Gravity_Forms_Helpers
 *
 * @package Uncanny_Automator
 */
class Gravity_Forms_Helpers {

	// /**
	//  * @var Gravity_Forms_Helpers
	//  */
	// public $options;

	// /**
	//  * @var Gravity_Forms_Pro_Helpers
	//  */
	// public $pro;

	// /**
	//  * @var bool
	//  */
	// public $load_options = true;

	// /**
	//  * Gravity_Forms_Helpers constructor.
	//  */
	// public function __construct() {
	// }

	/**
	 * @param Gravity_Forms_Helpers $options
	 * @deprecated 6.11
	 */
	public function setOptions( Gravity_Forms_Helpers $options ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
	 //$this->options = $options;
	}

	/**
	 * @param Gravity_Forms_Pro_Helpers $pro
	 * @deprecated 6.11
	 */
	public function setPro( Gravity_Forms_Pro_Helpers $pro ) {  // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
	 //$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function list_gravity_forms( $label = null, $option_code = 'GFFORMS', $args = array(), $is_any = false ) {
		// if ( ! $this->load_options ) {

		//  return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		// }

		if ( ! $label ) {
			$label = esc_attr_x( 'Form', 'Gravity Forms', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = array();

		if ( true === $is_any ) {
			$options[- 1] = esc_attr_x( 'Any form', 'Gravity Forms', 'uncanny-automator' );
		}

		if ( Automator()->helpers->recipe->load_helpers ) {
			$forms = \GFAPI::get_forms();

			if ( $forms ) {
				foreach ( $forms as $form ) {
					$options[ $form['id'] ] = esc_html( $form['title'] );
				}
			}
		}
		$type = 'select';

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => $type,
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
			'relevant_tokens' => array(
				$option_code         => esc_html_x( 'Form title', 'Gravity Forms', 'uncanny-automator' ),
				$option_code . '_ID' => esc_html_x( 'Form ID', 'Gravity Forms', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_list_gravity_forms', $option );
	}

	/**
	 * Retrieves all forms as option fields.
	 *
	 * @return array The list of option fields from Gravity forms.
	 */
	public function get_forms_as_options( $add_any = false ) {

		// if ( ! class_exists( '\GFAPI' ) || ! is_admin() ) {

		//  return array();

		// }

		$forms = \GFAPI::get_forms();

		$options = array();

		if ( true === $add_any ) {
			$options[] = array(
				'value' => -1,
				'text'  => esc_html_x( 'Any form', 'Gravity Forms', 'uncanny-automator' ),
			);
		}

		foreach ( $forms as $form ) {
			$options[] = array(
				'value' => absint( $form['id'] ),
				'text'  => $form['title'],
			);
		}

		return $options;
	}
}
