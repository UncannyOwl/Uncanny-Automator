<?php

namespace Uncanny_Automator;

/**
 * Class Restrict_Content_Helpers
 *
 * @package Uncanny_Automator
 */
class Restrict_Content_Helpers {
	/**
	 * @var Restrict_Content_Helpers
	 */
	public $options;

	/**
	 * @var \Uncanny_Automator_Pro\Restrict_Content_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Restrict_content_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param \Uncanny_Automator_Pro\Restrict_Content_Pro_Helpers $options
	 */
	public function setOptions( \Uncanny_Automator\Restrict_Content_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param \Uncanny_Automator_Pro\Restrict_Content_Pro_Helpers $pro
	 */
	public function setPro( \Uncanny_Automator_Pro\Restrict_Content_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param null   $label
	 * @param string $option_code
	 * @param string $type
	 *
	 * @return mixed|void
	 */
	public function get_membership_levels( $label = null, $option_code = null, $args = array() ) {

		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( null === $label ) {
			$label = esc_attr_x( 'Membership level', 'Restrict Content', 'uncanny-automator' );
		}

		if ( null === $option_code ) {
			$option_code = 'RCMEMBERSHIPLEVEL';
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$any          = key_exists( 'any', $args ) ? $args['any'] : false;

		$options = array();

		if ( $any ) {
			$options['-1'] = esc_attr_x( 'Any membership', 'Restrict Content', 'uncanny-automator' );
		}

		if ( function_exists( 'rcp_get_membership_levels' ) ) {
			// only available in Restrict Content Pro Version 3.4+
			$levels = rcp_get_membership_levels( array( 'number' => 999 ) );

			if ( ! empty( $levels ) ) {
				foreach ( $levels as $level ) {
					$options[ $level->get_id() ] = $level->get_name();
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
		);

		return apply_filters( 'uap_option_rc_get_membership_levels', $option );
	}
}
