<?php

namespace Uncanny_Automator;

/**
 * Class Bbpress_Tokens
 *
 * @package Uncanny_Automator
 */
class Bbpress_Tokens {

	/**
	 * Bbpress_Tokens constructor.
	 */
	public function __construct() {
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_bbpress_tokens' ), 999, 6 );
		add_filter( 'automator_maybe_trigger_bb_bbforums_tokens', array( $this, 'bb_possible_tokens', ), 20, 2 );
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
	public function parse_bbpress_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( $pieces ) {
			if ( in_array( 'BBFORUMS', $pieces ) || ( in_array( 'BBPOSTAREPLY', $pieces ) ) ) {
				if ( $trigger_data ) {
					foreach ( $trigger_data as $trigger ) {
						$trigger_id     = $trigger['ID'];
						$trigger_log_id = $replace_args['trigger_log_id'];
						$meta_key       = $pieces[2];
						if ( $meta_key === 'BBFORUMS' || $meta_key === 'BBFORUMS_URL' ) {
							$forum_ID = Automator()->helpers->recipe->get_form_data_from_trigger_meta( 'BBFORUMS_ID', $trigger_id, $trigger_log_id, $user_id );
							$forum    = get_post( $forum_ID );
							$value    = $forum->post_title;
							if ( $meta_key === 'BBFORUMS_URL' ) {
								$value = get_permalink( $forum->ID );
							}
						} elseif ( $meta_key === 'BBTOPIC' || $meta_key === 'BBTOPIC_URL' || $meta_key === 'BBTOPIC_CONTENT' ) {
							$topic_ID = Automator()->helpers->recipe->get_form_data_from_trigger_meta( 'BBTOPIC_ID', $trigger_id, $trigger_log_id, $user_id );
							$topic    = get_post( $topic_ID );
							$value    = $topic->post_title;
							if ( $meta_key === 'BBTOPIC_URL' ) {
								$value = get_permalink( $topic->ID );
							} elseif ( $meta_key === 'BBTOPIC_CONTENT' ) {
								$value = $topic->post_content;
							}
						} else {
							$value = Automator()->helpers->recipe->get_form_data_from_trigger_meta( $meta_key, $trigger_id, $trigger_log_id, $user_id );
						}

						if ( ! empty( $value ) ) {
							$value = maybe_unserialize( $value );
						}
					}
				}
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
	public function bb_possible_tokens( $tokens = array(), $args = array() ) {

		$trigger_meta = $args['meta'];
		if ( isset( $args['triggers_meta'] ) && ( 'ANONBBNEWTOPIC' === $args['triggers_meta']['code'] || 'BBNEWTOPIC' === $args['triggers_meta']['code'] ) ) {
			$fields = array(
				array(
					'tokenId'         => 'BBTOPIC_ID',
					'tokenName'       => __( 'Topic ID', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				),
				array(
					'tokenId'         => 'BBTOPIC_URL',
					'tokenName'       => __( 'Topic URL', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				),
				array(
					'tokenId'         => 'BBTOPIC',
					'tokenName'       => __( 'Topic title', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				),
				array(
					'tokenId'         => 'BBTOPIC_CONTENT',
					'tokenName'       => __( 'Topic content', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				),
			);

			$tokens = array_merge( $tokens, $fields );
		}

		return $tokens;
	}

}
