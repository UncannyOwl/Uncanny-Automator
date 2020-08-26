<?php

namespace Uncanny_Automator;

/**
 * Class WP_Anon_Tokens
 * @package Uncanny_Automator_Pro
 */
class Wp_Tokens {


	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WP';

	/**
	 * Wp_Tokens constructor.
	 */
	public function __construct() {

		add_filter( 'automator_maybe_trigger_wp_wppostcomments_tokens', [ $this, 'wp_possible_tokens' ], 20, 2 );
		add_filter( 'automator_maybe_parse_token', [ $this, 'parse_anonusercreated_token' ], 20, 6 );
	}
	
	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function wp_possible_tokens( $tokens = [], $args = [] ) {
		$trigger_integration = $args['integration'];
		$trigger_meta        = $args['meta'];

		$fields = [
			[
				'tokenId'         => 'authorname',
				'tokenName'       => 'Post\'s Author Name',
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			],
			[
				'tokenId'         => 'authoremail',
				'tokenName'       => 'Post\'s Author Email',
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			],
		];

		$tokens = array_merge( $tokens, $fields );

		return $tokens;
	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 *
	 * @param int $user_id
	 * @param $replace_args
	 *
	 * @return mixed
	 */
	public function parse_anonusercreated_token( $value, $pieces, $recipe_id, $trigger_data, $user_id = 0, $replace_args ) {
		$piece = 'WPPOSTCOMMENTS';
		if ( $pieces ) {
			if ( in_array( $piece, $pieces ) ) {
				global $uncanny_automator;

				if ( $trigger_data ) {
					foreach ( $trigger_data as $trigger ) {
						$post_id = $trigger['meta']['WPPOSTCOMMENTS'];
						$post    = get_post( $post_id );
						if ( ! empty( $post ) ) {
							if ( 'authorname' === $pieces[2] ) {
								$value = get_the_author_meta( 'display_name', $post->post_author );
							}
							if ( 'authoremail' === $pieces[2] ) {
								$value = get_the_author_meta( 'email', $post->post_author );
							}
						}
					}
				}
			}
		}

		return $value;
	}
}
