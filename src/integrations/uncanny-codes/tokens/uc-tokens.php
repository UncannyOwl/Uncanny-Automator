<?php

namespace Uncanny_Automator;

/**
 * Class Uc_Tokens
 *
 * @package Uncanny_Automator
 */
class Uc_Tokens {
	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'UNCANNYCODE';

	/**
	 * Uc_Tokens constructor.
	 */
	public function __construct() {
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_uncanny_codes_token' ), 20, 6 );
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
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return mixed|string
	 */
	public function parse_uncanny_codes_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		$tokens = array(
			'UNCANNYCODESPREFIX',
			'UNCANNYCODESSUFFIX',
			'UNCANNYCODESBATCH',
			'UNCANNYCODESBATCHEXPIRY',
		);

		if ( $pieces && isset( $pieces[2] ) ) {
			$meta_field = $pieces[2];
			if ( ! empty( $meta_field ) && in_array( $meta_field, $tokens ) ) {
				if ( $trigger_data ) {
					foreach ( $trigger_data as $trigger ) {
						switch ( $meta_field ) {
							case 'UNCANNYCODESBATCHEXPIRY':
								global $wpdb;
								$batch_id         = isset( $trigger['meta']['UNCANNYCODESBATCH'] ) && intval( '-1' ) !== intval( $trigger['meta']['UNCANNYCODESBATCH'] ) ? $trigger['meta']['UNCANNYCODESBATCH'] : absint( Automator()->db->token->get( 'UNCANNYCODESBATCH', $replace_args ) ); // Fix warning in error log.
								$expiry_date      = $wpdb->get_var( $wpdb->prepare( "SELECT expire_date FROM `{$wpdb->prefix}uncanny_codes_groups` WHERE ID = %d", $batch_id ) );
								$expiry_timestamp = strtotime( $expiry_date );

								// Check if the date is in future to filter out empty dates
								if ( $expiry_timestamp > time() ) {

									// Get the format selected in general WP settings
									$date_format = get_option( 'date_format' );
									$time_format = get_option( 'time_format' );

									// Get the formattted time according to the selected time zone
									$value = date_i18n( "$date_format $time_format", strtotime( $expiry_date ) );
								}
								break;
							case 'UNCANNYCODESBATCH':
								$value = $trigger['meta']['UNCANNYCODESBATCH_readable'];
								break;
							default:
								$value = isset( $trigger['meta'][ $meta_field ] ) ? $trigger['meta'][ $meta_field ] : '';
								break;
						}
					}
				}
			}
		}

		return $value;
	}
}
