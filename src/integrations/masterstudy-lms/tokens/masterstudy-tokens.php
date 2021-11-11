<?php

namespace Uncanny_Automator;

/**
 * Class Masterstudy_Tokens
 *
 * @package Uncanny_Automator
 */
class Masterstudy_Tokens {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'MSLMS';

	public function __construct() {
		add_filter( 'automator_maybe_parse_token', array( $this, 'masterstudy_token' ), 20, 6 );
	}

	/**
	 * Parse the token.
	 *
	 * @param string $value     .
	 * @param array  $pieces    .
	 * @param string $recipe_id .
	 *
	 * @param        $trigger_data
	 * @param        $user_id
	 * @param        $replace_args
	 *
	 * @return null|string
	 */
	public function masterstudy_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( $pieces ) {
			if ( in_array( 'MSLMSQUIZ_SCORE', $pieces, true ) ) {
				global $wpdb;
				$trigger_id     = $pieces[0];
				$trigger_meta   = $pieces[2];
				$trigger_log_id = isset( $replace_args['trigger_log_id'] ) ? absint( $replace_args['trigger_log_id'] ) : 0;

				$entry = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT meta_value
						FROM {$wpdb->prefix}uap_trigger_log_meta
						WHERE meta_key = %s
						AND automator_trigger_log_id = %d
						AND automator_trigger_id = %d
						LIMIT 0, 1",
						$trigger_meta,
						$trigger_log_id,
						$trigger_id
					)
				);

				$value = $entry;

			}
		}

		return $value;
	}
}
