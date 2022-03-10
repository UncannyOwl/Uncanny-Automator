<?php

namespace Uncanny_Automator;

class Uncanny_Groups_Tokens {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'UOG';

	/**
	 * Uncanny_Groups_Tokens constructor.
	 */
	public function __construct() {
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_uncanny_groups_token' ), 20, 6 );
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
	public function parse_uncanny_groups_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		$tokens = array(
			'REGISTEREDWITHGROUPKEY',
			'REDEEMSGROUPKEY'
		);

		if ( $pieces && isset( $pieces[2] ) ) {
			$meta_code  = $pieces[1];
			$meta_field = $pieces[2];
			if ( ! empty( $meta_code ) && in_array( $meta_code, $tokens ) ) {
				if ( $trigger_data ) {
					foreach ( $trigger_data as $trigger ) {
						global $wpdb;
						$code_details = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}uap_trigger_log_meta WHERE meta_key = 'code_details' AND automator_trigger_id = %d ORDER BY ID DESC LIMIT 0,1", $trigger['ID'] ) );
						$code_details = maybe_unserialize( $code_details );
						switch ( $meta_field ) {
							case 'UNCANNYGROUPS':
								$value = get_the_title( $code_details['ld_group_id'] );
								break;
							case 'UNCANNYGROUPS_ID':
								$value = $code_details['ld_group_id'];
								break;
							case 'UNCANNYGROUPS_URL':
								$value = get_permalink( $code_details['ld_group_id'] );
								break;
							case 'UNCANNYGROUPS_KEY':
								$value = $code_details['key'];
								break;
							case 'UNCANNYGROUPS_KEY_BATCH_ID':
								$value = $code_details['group_id'];
								break;
						}
					}
				}
			}
		}

		return $value;
	}

}
