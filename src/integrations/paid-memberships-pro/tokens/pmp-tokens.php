<?php

namespace Uncanny_Automator;

/**
 * Class Pmp_Tokens
 *
 * @package Uncanny_Automator
 */
class Pmp_Tokens {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'PMP';

	/**
	 * Pmp_Tokens constructor.
	 */
	public function __construct() {
		//*************************************************************//
		// See this filter generator AT automator-get-data.php
		// in function recipe_trigger_tokens()
		//*************************************************************//
		//add_filter( 'automator_maybe_trigger_pmp_tokens', [ $this, 'pmp_general_tokens' ], 20, 2 );
		//add_filter( 'automator_maybe_trigger_pmp_pmpmembership_tokens', [ $this, 'pmp_possible_tokens' ], 20, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'pmp_token' ), 20, 6 );
		add_action( 'uap_save_pmp_membership_level', array( $this, 'uap_save_pmp_membership_level' ), 10, 4 );
	}

	/**
	 * Only load this integration and its triggers and actions if the related plugin is active
	 *
	 * @param $status
	 * @param $plugin
	 *
	 * @return bool
	 */
	public function plugin_active( $status, $plugin ) {

		if ( self::$integration === $plugin ) {
			if ( defined( 'PMPRO_BASE_FILE' ) ) {
				$status = true;
			} else {
				$status = false;
			}
		}

		return $status;
	}

	public function uap_save_pmp_membership_level( $membership_id, $args, $user_id, $meta ) {

		$args = array(
			'user_id'        => $user_id,
			'trigger_id'     => $args['trigger_id'],
			'meta_key'       => $meta,
			'meta_value'     => $membership_id,
			'run_number'     => $args['run_number'], //get run number
			'trigger_log_id' => $args['get_trigger_id'],
		);

		Automator()->insert_trigger_meta( $args );
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function pmp_possible_tokens( $tokens = array(), $args = array() ) {

		return $tokens;
	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return string|null
	 */
	public function pmp_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( $pieces ) {

			if ( in_array( 'PMPMEMBERSHIP', $pieces, true ) ) {

				global $wpdb;

				$trigger_id     = $pieces[0];
				$field          = $pieces[2];
				$trigger_log_id = isset( $replace_args['trigger_log_id'] ) ? absint( $replace_args['trigger_log_id'] ) : 0;

				$entry = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT meta_value
						FROM {$wpdb->prefix}uap_trigger_log_meta
						WHERE meta_key = %s
						AND automator_trigger_log_id = %d
						AND automator_trigger_id = %d
						LIMIT 0,1",
						$field,
						$trigger_log_id,
						$trigger_id
					)
				);

				$entry = maybe_unserialize( $entry );

				if ( $entry ) {
					$level = pmpro_getLevel( $entry );
					if ( $level ) {
						if ( 'PMPMEMBERSHIP' === $field ) {
							$value = $level->name;
						} elseif ( 'PMPMEMBERSHIP_ID' === $field ) {
							$value = $entry;
						}
					}
				}
			}
		}

		return $value;
	}
}
