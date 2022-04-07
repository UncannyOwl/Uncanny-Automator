<?php

namespace Uncanny_Automator;

/**
 * Class Bbpress_Anon_Tokens
 *
 * @package Uncanny_Automator
 */
class Bbpress_Anon_Tokens {
	/**
	 * Bbpress_Anon_Tokens constructor.
	 */
	public function __construct() {
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_bb_anon_tokens' ), 20, 6 );
		add_filter(
			'automator_maybe_trigger_bb_anonbbnewtopic_tokens',
			array(
				$this,
				'bb_possible_anonymous_tokens',
			),
			20,
			2
		);
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
	public function parse_bb_anon_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( $pieces ) {
			if ( in_array( 'ANONYMOUS_EMAIL', $pieces ) ) {
				global $wpdb;
				$trigger_id     = $pieces[0];
				$trigger_meta   = $pieces[2];
				$trigger_log_id = isset( $replace_args['trigger_log_id'] ) ? absint( $replace_args['trigger_log_id'] ) : 0;
				$entry          = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT meta_value
FROM {$wpdb->prefix}uap_trigger_log_meta
WHERE meta_key = %s
  AND automator_trigger_log_id =%d
  AND automator_trigger_id =%d
LIMIT 0,1",
						$trigger_meta,
						$trigger_log_id,
						$trigger_id
					)
				);

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
	public function bb_possible_anonymous_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		$trigger_meta        = $args['meta'];

		$fields = array(
			array(
				'tokenId'         => 'ANONYMOUS_EMAIL',
				'tokenName'       => __( 'Guest email', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
		);

		return array_merge( $tokens, $fields );
	}

}
