<?php

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Affwp_Pro_Helpers;

/**
 * Class Affwp_Helpers
 *
 * @package Uncanny_Automator
 */
class Affwp_Helpers {

	/**
	 * @var Affwp_Helpers
	 */
	public $options;

	/**
	 * @var Affwp_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Affwp_Helpers constructor.
	 */
	public function __construct() {
	}

	/**
	 * @param Affwp_Helpers $options
	 */
	public function setOptions( Affwp_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Affwp_Pro_Helpers $pro
	 */
	public function setPro( Affwp_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param null   $label
	 * @param string $option_code
	 * @param array  $args
	 *
	 * @return mixed|void
	 */
	public function get_referral_types( $label = null, $option_code = 'REFERRALTYPES', $args = array() ) {
		if ( ! $label ) {
			$label = __( 'Referral type', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$any_option   = key_exists( 'any_option', $args ) ? $args['any_option'] : false;
		$options      = array();

		if ( isset( $any_option ) && $any_option == true ) {
			$options['-1'] = __( 'Any type', 'uncanny-automator' );
		}

		foreach ( affiliate_wp()->referrals->types_registry->get_types() as $type_id => $type ) {
			$title = $type['label'];
			if ( empty( $title ) ) {
				$title = sprintf( __( 'ID: %s (no title)', 'uncanny-automator' ), $type_id );
			}
			$options[ $type_id ] = $title;
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

		return apply_filters( 'uap_option_get_referral_types', $option );
	}
}
