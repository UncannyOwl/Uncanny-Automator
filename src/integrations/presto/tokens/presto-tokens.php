<?php

namespace Uncanny_Automator\Integrations\Presto;

class_alias( 'Uncanny_Automator\Integrations\Presto\Presto_Tokens', 'Uncanny_Automator\Presto_Tokens' );
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

	/**
	 * Presto_Tokens constructor.
	 */
	public function __construct() {

		//add_filter( 'automator_maybe_parse_token', array( $this, 'presto_token' ), 20, 6 );
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
		$keys  = array(
			'PRESTOVIDEO',
			'PRESTOVIDEO_POST_TITLE',
		);

		if ( $pieces ) {
			if ( ! empty( array_intersect( $keys, $pieces ) ) ) {
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

							// Validate we have one of the keys we're looking for.
							$key = $pieces[2];
							if ( ! in_array( $key, $keys, true ) ) {
								return $value;
							}

							// Get normalized video data.
							$video = Automator()->helpers->recipe->presto->options->normalize_video_data( $video_id );
							if ( ! $video ) {
								return $value;
							}

							switch ( $key ) {
								case 'PRESTOVIDEO':
									$value = $video->title;
									break;
								case 'PRESTOVIDEO_POST_TITLE':
									$value = ! empty( $video->hub_id ) ? get_the_title( $video->hub_id ) : '-';
									break;
							}
						}
					}
				}
			}
		}

		return $value;
	}
}
