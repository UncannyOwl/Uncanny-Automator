<?php

namespace Uncanny_Automator;

/**
 * Class Bdb_Tokens
 *
 * @package Uncanny_Automator
 */
class Bdb_Tokens {


	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'BDB';

	public function __construct() {
		add_filter( 'automator_maybe_trigger_bdb_tokens', [ $this, 'bdb_possible_tokens' ], 20, 2 );
		add_filter( 'automator_maybe_trigger_bdb_bdbforumstopic_tokens', [
			$this,
			'bdb_bdbforums_possible_tokens',
		], 20, 2 );
		add_filter( 'automator_maybe_trigger_bdb_bdbtopic_tokens', [ $this, 'bdb_topic_possible_tokens' ], 20, 2 );
		add_filter( 'automator_maybe_parse_token', [ $this, 'parse_bp_token' ], 20, 6 );

	}

	/**
	 * Only load this integration and its triggers and actions if the related
	 * plugin is active
	 *
	 * @param $status
	 * @param $code
	 *
	 * @return bool
	 */
	public function plugin_active( $status, $code ) {

		if ( self::$integration === $code ) {
			if ( function_exists( 'buddypress' ) && isset( buddypress()->buddyboss ) && buddypress()->buddyboss ) {
				$status = true;
			} else {
				$status = false;
			}
		}

		return $status;
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function bdb_possible_tokens( $tokens = array(), $args = array() ) {
		$trigger_integration = $args['integration'];
		$trigger_meta        = $args['meta'];

		$fields = [
			[
				'tokenId'         => 'BDBUSER',
				'tokenName'       => __( 'Avatar URL', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'BDBUSERAVATAR',
			],
		];
		// Get BDB xprofile fields from DB.
		global $wpdb;
		
		$xprofile_fields = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bp_xprofile_fields WHERE parent_id = %d ORDER BY field_order ASC",
				0
			)
		);

		if ( ! empty( $xprofile_fields ) ) {
			foreach ( $xprofile_fields as $field ) {
				if ( 'socialnetworks' === $field->type ) {
					$child_fields = $wpdb->get_results( 
						$wpdb->prepare(
							"SELECT * FROM {$wpdb->prefix}bp_xprofile_fields WHERE parent_id = %d ORDER BY field_order ASC",
							$field->id
						)
					);
					if ( ! empty( $child_fields ) ) {
						foreach ( $child_fields as $child_field ) {
							$fields[] = [
								'tokenId'         => 'BDBUSER',
								'tokenName'       => $field->name . ' - ' . $child_field->name,
								'tokenType'       => 'text',
								'tokenIdentifier' => 'BDBXPROFILE:' . $field->id . '|' . $child_field->name,
							];
						}
					}
				} elseif ( 'membertypes' === $field->type ) {
					$fields[] = [
						'tokenId'         => 'BDBUSER',
						'tokenName'       => $field->name,
						'tokenType'       => 'text',
						'tokenIdentifier' => 'BDBXPROFILE:' . $field->id . '|membertypes',
					];
				} else {
					$fields[] = [
						'tokenId'         => 'BDBUSER',
						'tokenName'       => $field->name,
						'tokenType'       => 'text',
						'tokenIdentifier' => 'BDBXPROFILE:' . $field->id,
					];
				}
			}
		}

		if ( isset( $args['triggers_meta']['code'] ) && 'BDBACTIVITYSTRM' === $args['triggers_meta']['code'] ) {

			$fields[] = [
				'tokenId'         => 'ACTIVITY_ID',
				'tokenName'       => __( 'Activity ID', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'BDBUSERACTIVITY',
			];
			$fields[] = [
				'tokenId'         => 'ACTIVITY_URL',
				'tokenName'       => __( 'Activity URL', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'BDBUSERACTIVITY',
			];
			$fields[] = [
				'tokenId'         => 'ACTIVITY_STREAM_URL',
				'tokenName'       => __( 'Activity stream URL', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'BDBUSERACTIVITY',
			];
			$fields[] = [
				'tokenId'         => 'ACTIVITY_CONTENT',
				'tokenName'       => __( 'Activity content', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'BDBUSERACTIVITY',
			];
		}
		$tokens = array_merge( $tokens, $fields );

		return $tokens;
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function bdb_topic_possible_tokens( $tokens = array(), $args = array() ) {
		$trigger_integration = $args['integration'];
		$trigger_meta        = $args['meta'];

		$fields = [
			[
				'tokenId'         => 'BDBTOPICREPLY',
				'tokenName'       => __( 'Reply content', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'BDBUSERPOSTREPLYFORUM',
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
	 * @return mixed
	 */
	public function parse_bp_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( $pieces ) {
			if ( in_array( 'BDBUSERS', $pieces ) ) {
				// Get user id from meta log
				if ( $trigger_data ) {
					foreach ( $trigger_data as $trigger ) {
						$token_value = $trigger['meta']['BDBUSERS'];
						$value       = ( intval( $token_value ) == - 1 ) ? $user_id : $token_value;
					}
				}
			} elseif ( in_array( 'BDBUSERAVATAR', $pieces ) ) {
				// Get Group id from meta log
				if ( function_exists( 'get_avatar_url' ) ) {
					$value = get_avatar_url( $user_id );
				}
			} elseif ( in_array( 'BDBXPROFILE', $pieces ) ) {

				if ( isset( $pieces[2] ) && ! empty( $pieces[2] ) ) {
					$value = $this->get_xprofile_data( $user_id, $pieces[2] );
				}
			} elseif ( in_array( 'BDBTOPICREPLY', $pieces ) ) {
				$piece = 'BDBTOPIC';

				$recipe_log_id = Automator()->maybe_create_recipe_log_entry( $recipe_id, $user_id )['recipe_log_id'];
				if ( $trigger_data && $recipe_log_id ) {
					foreach ( $trigger_data as $trigger ) {
						if ( key_exists( $piece, $trigger['meta'] ) ) {
							$trigger_id     = $trigger['ID'];
							$trigger_log_id = $replace_args['trigger_log_id'];
							$meta_key       = $pieces[2];
							$meta_value     = Automator()->helpers->recipe->get_form_data_from_trigger_meta( $meta_key, $trigger_id, $trigger_log_id, $user_id );
							if ( ! empty( $meta_value ) ) {
								$content = get_post_field( 'post_content', $meta_value );
								$value   = apply_filters( 'bbp_get_reply_content', $content, $meta_value );
							}
						}
					}
				}
			} elseif ( in_array( 'BDBNEWTOPIC', $pieces ) ) {
				$piece = 'BDBFORUMSTOPIC';

				$recipe_log_id = Automator()->maybe_create_recipe_log_entry( $recipe_id, $user_id )['recipe_log_id'];
				if ( $trigger_data && $recipe_log_id ) {
					foreach ( $trigger_data as $trigger ) {
						if ( key_exists( $piece, $trigger['meta'] ) ) {
							$trigger_id     = $trigger['ID'];
							$trigger_log_id = $replace_args['trigger_log_id'];
							$meta_key       = 'BDBTOPIC';
							$meta_value     = Automator()->helpers->recipe->get_form_data_from_trigger_meta( $meta_key, $trigger_id, $trigger_log_id, $user_id );
							if ( ! empty( $meta_value ) ) {
								if ( 'BDBTOPICID' === $pieces[2] ) {
									$value = $meta_value;
								} elseif ( 'BDBTOPICTITLE' === $pieces[2] ) {
									$title = get_the_title( $meta_value );
									$value = apply_filters( 'bbp_get_topic_title', $title, $meta_value );
								} elseif ( 'BDBTOPICURL' === $pieces[2] ) {
									$topic_permalink = get_permalink( $meta_value );
									$value           = apply_filters( 'bbp_get_topic_permalink', $topic_permalink, $meta_value );
								} elseif ( 'BDBTOPICCONTENT' === $pieces[2] ) {
									$content = get_post_field( 'post_content', $meta_value );
									$value   = apply_filters( 'bbp_get_topic_content', $content, $meta_value );
								}
							}
						}
					}
				}
			} elseif ( in_array( 'BDBUSERACTIVITY', $pieces ) ) {

				if ( $trigger_data ) {
					foreach ( $trigger_data as $trigger ) {
						$trigger_id     = $trigger['ID'];
						$trigger_log_id = $replace_args['trigger_log_id'];
						$meta_key       = $pieces[2];
						$meta_value     = Automator()->helpers->recipe->get_form_data_from_trigger_meta( $meta_key, $trigger_id, $trigger_log_id, $user_id );
						if ( ! empty( $meta_value ) ) {
							$value = $meta_value;
						}
					}
				}
			}
		}

		return $value;
	}

	/**
	 * @param $user_id
	 * @param $field_id
	 *
	 * @return mixed|string
	 */
	public function get_xprofile_data( $user_id, $field_id ) {
		global $wpdb;
		if ( empty( $field_id ) ) {
			return '';
		}

		$field_token = explode( '|', $field_id );
		if ( count( $field_token ) > 0 ) {
			$field_id = $field_token[0];
		}

		$meta_value = $wpdb->get_var( $wpdb->prepare( "SELECT value FROM {$wpdb->prefix}bp_xprofile_data WHERE user_id = %d AND field_id = %s LIMIT 0,1", $user_id, $field_id ) );
		if ( ! empty( $meta_value ) ) {

			$meta_data = maybe_unserialize( $meta_value );
			if ( empty( $meta_data ) ) {
				return '';
			}
			if ( is_array( $meta_data ) ) {
				if ( isset( $field_token[1] ) ) {
					return isset( $meta_data[ $field_token[1] ] ) ? $meta_data[ $field_token[1] ] : '';
				}

				return implode( ', ', $meta_data );
			}

			if ( isset( $field_token[1] ) && 'membertypes' === $field_token[1] ) {
				return get_the_title( $meta_data );
			}

			return $meta_data;
		}

		return '';
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function bdb_bdbforums_possible_tokens( $tokens = array(), $args = array() ) {
		$trigger_integration = $args['integration'];
		$trigger_meta        = $args['meta'];

		$fields = [
			[
				'tokenId'         => 'BDBTOPICID',
				'tokenName'       => __( 'Topic ID', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'BDBNEWTOPIC',
			],
			[
				'tokenId'         => 'BDBTOPICTITLE',
				'tokenName'       => __( 'Topic title', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'BDBNEWTOPIC',
			],
			[
				'tokenId'         => 'BDBTOPICURL',
				'tokenName'       => __( 'Topic URL', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'BDBNEWTOPIC',
			],
			[
				'tokenId'         => 'BDBTOPICCONTENT',
				'tokenName'       => __( 'Topic content', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'BDBNEWTOPIC',
			],
		];

		$tokens = array_merge( $tokens, $fields );

		return $tokens;
	}
}
