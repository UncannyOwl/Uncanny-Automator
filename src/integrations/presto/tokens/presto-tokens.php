<?php

namespace Uncanny_Automator;

/**
 * Class Presto_Tokens
 *
 * @package Uncanny_Automator
 */
class Presto_Tokens {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'PRESTO';

	public function __construct() {

		add_filter( 'automator_maybe_parse_token', array( $this, 'presto_token' ), 20, 6 );
	}

	/**
	 * Parse the token.
	 *
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return null|string
	 */
	public function presto_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		$piece = 'PRESTOVIDEO';
		if ( $pieces ) {
			if ( in_array( $piece, $pieces, true ) ) {

				$recipe_log_id = isset( $replace_args['recipe_log_id'] ) ? (int) $replace_args['recipe_log_id'] : Automator()->maybe_create_recipe_log_entry( $recipe_id, $user_id )['recipe_log_id'];
				if ( $trigger_data && $recipe_log_id ) {
					foreach ( $trigger_data as $trigger ) {
						if ( key_exists( $piece, $trigger['meta'] ) ) {
							$trigger_id = $trigger['ID'];
							if ( '-1' === $trigger['meta'][ $piece ] ) {
								$trigger_log_id = $replace_args['trigger_log_id'];
								global $wpdb;
								$video_id = $wpdb->get_var(
									$wpdb->prepare(
										"SELECT meta_value
										FROM {$wpdb->prefix}uap_trigger_log_meta
										WHERE meta_key = 'PRESTOVIDEO'
										AND automator_trigger_log_id = %d
										AND automator_trigger_id = %d
										LIMIT 0, 1",
										$trigger_log_id,
										$trigger_id
									)
								);
							} else {
								$video_id = $trigger['meta'][ $piece ];
							}

							if ( 'PRESTOVIDEO' === $pieces[2] ) {
								$video = new \PrestoPlayer\Models\Video( $video_id );
								$value = $video->title;
							}
						}
					}
				}
			}
		}

		return $value;
	}

}
