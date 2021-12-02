<?php

namespace Uncanny_Automator;

/**
 * Class Wc_Memberships_Helpers
 *
 * @package Uncanny_Automator
 */
class Wc_Memberships_Helpers {
	/**
	 * @var Wc_Memberships_Helpers
	 */
	public $options;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Wc_Memberships_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param Wc_Memberships_Helpers $options
	 */
	public function setOptions( Wc_Memberships_Helpers $options ) {
		$this->options = $options;
	}

	public function wcm_get_all_membership_plans( $label = null, $option_code = 'WCMEMBERSHIPPLANS', $args = array() ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = __( 'Membership plan', 'uncanny-automator' );
		}

		$token       = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax     = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$include_all = key_exists( 'include_all', $args ) ? $args['include_all'] : '';
		$is_any      = key_exists( 'is_any', $args ) ? $args['is_any'] : false;
		$end_point   = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';

		$args = array(
			'post_type'      => 'wc_membership_plan',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args, $is_any, esc_attr__( 'Any membership plan', 'uncanny-automator' ) );

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'endpoint'        => $end_point,
			'options'         => $options,
		);

		return apply_filters( 'uap_option_all_wc_variable_products', $option );
	}

}
