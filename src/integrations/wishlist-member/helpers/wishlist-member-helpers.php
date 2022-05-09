<?php

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Wishlist_Member_Pro_Helpers;

/**
 * Class Wishlist_Member_Helpers
 *
 * @package Uncanny_Automator
 */
class Wishlist_Member_Helpers {
	/**
	 * @var Wishlist_Member_Helpers
	 */
	public $options;

	/**
	 * @var Wishlist_Member_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Wishlist_Member_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param Wishlist_Member_Helpers $options
	 */
	public function setOptions( Wishlist_Member_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Wishlist_Member_Pro_Helpers $pro
	 */
	public function setPro( Wishlist_Member_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param null $label
	 * @param string $option_code
	 * @param string $type
	 *
	 * @return mixed|void
	 */
	public function wm_get_all_membership_levels( $label = null, $option_code = 'WMMEMBERSHIPLEVELS', $args = array() ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Membership level', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$include_all  = key_exists( 'include_all', $args ) ? $args['include_all'] : false;
		$any          = key_exists( 'any', $args ) ? $args['any'] : false;

		$options = array();
		if ( $include_all ) {
			$options['-1'] = esc_attr__( 'All levels', 'uncanny-automator' );
		}

		if ( $any ) {
			$options['-1'] = esc_attr__( 'Any level', 'uncanny-automator' );
		}
		$levels = wlmapi_get_levels();

		if ( ! empty( $levels['levels']['level'] ) ) {
			foreach ( $levels['levels']['level'] as $level ) {
				$options[ $level['id'] ] = $level['name'];
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
				$option_code => esc_attr__( 'Membership level', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_wm_get_all_membership_levels', $option );
	}
}
