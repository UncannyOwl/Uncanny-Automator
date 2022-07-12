<?php

namespace Uncanny_Automator;

/**
 * Class WP_Anon_Tokens
 *
 * @package Uncanny_Automator
 */
class Wp_Tokens {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WP';

	/**
	 * Wp_Tokens constructor.
	 */
	public function __construct() {
		// Hide error for Automator Pro until Pro 3.1 is released.
		if ( PHP_MAJOR_VERSION >= 7 ) {
			set_error_handler(
				function ( $errno, $errstr, $file ) {
					return strpos( $file, '/tokens/wp-anon-tokens.php' ) !== false &&
						   strpos( $errstr, 'Declaration of' ) === 0;
				},
				E_WARNING
			);
		}

		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_wproles_token' ), 20, 6 );
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
	public function parse_wproles_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		$piece = 'WPROLE';
		if ( $pieces ) {
			if ( in_array( $piece, $pieces ) ) {

				if ( $trigger_data ) {
					foreach ( $trigger_data as $trigger ) {
						global $wpdb;
						$meta_field = $trigger['ID'] . ':' . $pieces[1] . ':' . $pieces[2];
						$trigger_id = $trigger['ID'];
						$meta_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}uap_trigger_log_meta WHERE meta_key LIKE %s AND automator_trigger_id = %d ORDER BY ID DESC LIMIT 0,1", "%%$meta_field%%", $trigger_id ) );
						if ( ! empty( $meta_value ) ) {
							$value = maybe_unserialize( $meta_value );
						}
					}
				}
			}
		}

		return $value;
	}
}
