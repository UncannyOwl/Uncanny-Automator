<?php

namespace Uncanny_Automator;

/**
 * Class Bbpress_Anon_Tokens
 * @package Uncanny_Automator
 */
class Bbpress_Anon_Tokens {
	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'BB';

	/**
	 * Bbpress_Anon_Tokens constructor.
	 */
	public function __construct() {
		add_filter( 'automator_maybe_parse_token', [ $this, 'parse_bb_anon_tokens' ], 20, 6 );
		add_filter( 'automator_maybe_trigger_bb_anonbbnewtopic_tokens', [
			$this,
			'bb_possible_anonymous_tokens',
		], 20, 2 );
	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return mixed
	 */
	function parse_bb_anon_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( $pieces ) {
			if ( in_array( 'ANONYMOUS_EMAIL', $pieces ) ) {
				global $wpdb;
				$trigger_id     = $pieces[0];
				$trigger_meta   = $pieces[2];
				$trigger_log_id = isset( $replace_args['trigger_log_id'] ) ? absint( $replace_args['trigger_log_id'] ) : 0;
				$entry          = $wpdb->get_var( "SELECT meta_value
													FROM {$wpdb->prefix}uap_trigger_log_meta
													WHERE meta_key = '{$trigger_meta}'
													AND automator_trigger_log_id = {$trigger_log_id}
													AND automator_trigger_id = {$trigger_id}
													LIMIT 0,1" );

				$value = maybe_unserialize( $entry );
			}
		}

		return $value;
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	function bb_possible_anonymous_tokens( $tokens = array(), $args = array() ) {

		$trigger_integration = $args['integration'];
		$trigger_meta        = $args['meta'];

		$fields = [
			[
				'tokenId'         => 'ANONYMOUS_EMAIL',
				'tokenName'       => __( 'Guest email', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			],
		];

		$tokens = array_merge( $tokens, $fields );

		return $tokens;
	}

}