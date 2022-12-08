<?php

namespace Uncanny_Automator;

/**
 * Class Easy_Affiliate_Helpers
 *
 * @package Uncanny_Automator
 */
class Easy_Affiliate_Helpers {

	/**
	 * @param $option_code
	 * @param $add_any
	 *
	 * @return array|mixed|void
	 */
	public function get_all_affiliates( $option_code, $add_any = true ) {

		$options = array();

		if ( $add_any ) {
			$options['-1'] = __( 'Any affiliate', 'uncanny-automator' );
		}

		$args = array(
			'meta_query' => array(
				array(
					'key'     => 'wafp_is_affiliate',
					'value'   => '1',
					'compare' => '=',
				),
			),
		);

		$affiliates = get_users( $args );

		foreach ( $affiliates as $user ) {
			$options[ $user->ID ] = $user->display_name;
		}

		$option = array(
			'input_type'            => 'select',
			'option_code'           => $option_code,
			/* translators: HTTP request method */
			'label'                 => esc_attr__( 'Affiliate', 'uncanny-automator' ),
			'required'              => true,
			'supports_custom_value' => true,
			'options'               => $options,
		);

		return apply_filters( 'uap_option_get_all_affiliates', $option );
	}

}
