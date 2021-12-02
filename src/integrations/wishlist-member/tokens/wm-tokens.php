<?php

namespace Uncanny_Automator;

/**
 * Class Wm_Tokens
 *
 * @package Uncanny_Automator
 */
class Wm_Tokens {
	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WISHLISTMEMBER';

	/**
	 * Wm_Tokens constructor.
	 */
	public function __construct() {
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_wm_token' ), 20, 6 );
	}

	/**
	 * Only load this integration and its triggers and actions if the related plugin is active
	 *
	 * @param $status
	 * @param $code
	 *
	 * @return bool
	 */
	public function plugin_active( $status, $code ) {

		if ( self::$integration === $code ) {

			$status = true;
		}

		return $status;
	}

	/**
	 * @param     $value
	 * @param     $pieces
	 * @param     $recipe_id
	 * @param     $trigger_data
	 *
	 * @param int $user_id
	 * @param     $replace_args
	 *
	 * @return mixed
	 */
	public function parse_wm_token( $value, $pieces, $recipe_id, $trigger_data, $user_id = 0, $replace_args ) {
		$tokens = array(
			'WMMEMBERSHIPLEVELS',
		);

		if ( $pieces && isset( $pieces[2] ) ) {
			$meta_field = $pieces[2];

			if ( ! empty( $meta_field ) && in_array( $meta_field, $tokens ) ) {
				if ( $trigger_data ) {
					foreach ( $trigger_data as $trigger ) {
						switch ( $meta_field ) {
							case 'WMMEMBERSHIPLEVELS':
								$value = $trigger['meta']['WMMEMBERSHIPLEVELS_readable'];
								break;
						}
					}
				}
			}
		}

		return $value;
	}
}
