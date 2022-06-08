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
	 * @return $status True if GP is active. Otherwise, false.
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

		if ( in_array( 'GPSPECIFICPOINTS', $pieces, true ) ) {
			if ( $trigger_data ) {
				foreach ( $trigger_data as $trigger ) {
					$trigger_id     = $trigger['ID'];
					$trigger_log_id = $replace_args['trigger_log_id'];
					$meta_key       = $pieces[2];
					if ( 'NUMBERCOND' === $meta_key ) {
						$meta_value = $trigger['meta']['NUMBERCOND_readable'];
					} else {
						$meta_value = Automator()->helpers->recipe->get_form_data_from_trigger_meta( $meta_key, $trigger_id, $trigger_log_id, $user_id );
					}
					if ( ! empty( $meta_value ) ) {
						$value = maybe_unserialize( $meta_value );
					}
				}
			}
		}

		$award_type = isset( $trigger_data[0]['meta']['GPAWARDTYPES'] ) ? $trigger_data[0]['meta']['GPAWARDTYPES'] : '';

		$token = isset( $pieces[2] ) ? $pieces[2] : '';

		if ( ! empty( $token ) && ! empty( $award_type ) ) {

			if ( 'GPAWARDTYPES' === $token ) {
				if ( '-1' !== $award_type ) {
					$value = isset( $trigger_data[0]['meta']['GPAWARDTYPES_readable'] ) ? $trigger_data[0]['meta']['GPAWARDTYPES_readable'] : '';
				} else {
					global $wpdb;
					$meta_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}uap_trigger_log_meta WHERE meta_key = %s AND automator_trigger_id = %d AND automator_trigger_log_id = %d ORDER BY ID DESC LIMIT 0,1", $token, $trigger_data[0]['ID'], $replace_args['trigger_log_id'] ) );
					if ( ! empty( $meta_value ) ) {
						$value = maybe_unserialize( $meta_value );
					}
				}
			}
		}

		return $value;

	}

}
