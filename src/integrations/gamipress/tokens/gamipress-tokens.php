<?php
namespace Uncanny_Automator;

/**
 * Class Gamipress_Tokens
 *
 * @package Uncanny_Automator
 */
class Gamipress_Tokens {

	public static $integration = 'GP';

	public function __construct() {

		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_token' ), 20, 6 );

	}

	/**
	 * Check if GP is active or not.
	 *
	 * @return bool $status True if GP is active. Otherwise, false.
	 */
	public function plugin_active( $status, $code ) {

		if ( class_exists( '\GamiPress' ) ) {
			$status = true;
		} else {
			$status = false;
		}

		return $status;
	}

	/**
	 * Parse the token as usual.
	 *
	 * @return string the value of the token.
	 */
	public function parse_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		$award_type = $trigger_data[0]['meta']['GPAWARDTYPES'] ?? '';

		$token = $pieces[2] ?? '';

		if ( ! empty( $token ) && ! empty( $award_type ) ) {

			if ( 'GPAWARDTYPES' === $token ) {
				$award_type = $trigger_data[0]['meta']['GPAWARDTYPES_readable'] ?? '';
				return $award_type;
			}
		}

		return $value;

	}

}
