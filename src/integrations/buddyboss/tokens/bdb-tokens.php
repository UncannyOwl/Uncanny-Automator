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

		add_filter( 'automator_maybe_trigger_bdb_tokens', array( $this, 'bdb_possible_tokens' ), 20, 2 );

		add_filter( 'automator_maybe_trigger_bdb_bdbforumstopic_tokens', array( $this, 'bdb_bdbforums_possible_tokens' ), 20, 2 );

		add_filter( 'automator_maybe_trigger_bdb_bdbtopic_tokens', array( $this, 'bdb_topic_possible_tokens' ), 20, 2 );

		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_bp_token' ), 20, 6 );

	}

	/**
	 * Possible tokens.
	 *
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function bdb_possible_tokens( $tokens = array(), $args = array() ) {

		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}
		$trigger_integration = $args['integration'];
		$trigger_meta        = $args['meta'];

		$fields = array(
			array(
				'tokenId'         => 'BDBUSER',
				'tokenName'       => __( 'Avatar URL', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'BDBUSERAVATAR',
			),
		);
		// Get BDB xprofile fields from DB.
		global $wpdb;

		$xprofile_fields = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->base_prefix}bp_xprofile_fields WHERE parent_id = %d ORDER BY group_id ASC",
				0
			)
		);

		if ( ! empty( $xprofile_fields ) ) {

			foreach ( $xprofile_fields as $field ) {

				if ( 'socialnetworks' === $field->type ) {
					$child_fields = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT * FROM {$wpdb->base_prefix}bp_xprofile_fields WHERE parent_id = %d ORDER BY group_id ASC",
							$field->id
						)
					);
					if ( ! empty( $child_fields ) ) {
						foreach ( $child_fields as $child_field ) {
							$fields[] = array(
								'tokenId'         => 'BDBUSER',
								'tokenName'       => $field->name . ' - ' . $child_field->name,
								'tokenType'       => 'text',
								'tokenIdentifier' => 'BDBXPROFILE:' . $field->id . '|' . $child_field->name,
							);
						}
					}
				} elseif ( 'membertypes' === $field->type ) {
					$fields[] = array(
						'tokenId'         => 'BDBUSER',
						'tokenName'       => $field->name,
						'tokenType'       => 'text',
						'tokenIdentifier' => 'BDBXPROFILE:' . $field->id . '|membertypes',
					);
				} else {
					$fields[] = array(
						'tokenId'         => 'BDBUSER',
						'tokenName'       => $field->name,
						'tokenType'       => 'text',
						'tokenIdentifier' => 'BDBXPROFILE:' . $field->id,
					);
				}
			}
		}

		if ( isset( $args['triggers_meta']['code'] ) && 'BDBACTIVITYSTRM' === $args['triggers_meta']['code'] ) {

			$fields[] = array(
				'tokenId'         => 'ACTIVITY_ID',
				'tokenName'       => __( 'Activity ID', 'uncanny-automator' ),
				'tokenType'       => 'int',
				'tokenIdentifier' => 'BDBUSERACTIVITY',
			);
			$fields[] = array(
				'tokenId'         => 'ACTIVITY_URL',
				'tokenName'       => __( 'Activity URL', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'BDBUSERACTIVITY',
			);
			$fields[] = array(
				'tokenId'         => 'ACTIVITY_STREAM_URL',
				'tokenName'       => __( 'Activity stream URL', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'BDBUSERACTIVITY',
			);
			$fields[] = array(
				'tokenId'         => 'ACTIVITY_CONTENT',
				'tokenName'       => __( 'Activity content', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'BDBUSERACTIVITY',
			);
		}

		if ( isset( $args['triggers_meta']['code'] ) && ( 'BDBUSERSENDSFRIENDREQUEST' === $args['triggers_meta']['code'] || 'BDBUSERACCEPTFRIENDREQUEST' === $args['triggers_meta']['code'] ) ) {
			$trigger_code = $args['triggers_meta']['code'];
			$fields[]     = array(
				'tokenId'         => 'FRIEND_ID',
				'tokenName'       => __( 'Friend ID', 'uncanny-automator' ),
				'tokenType'       => 'int',
				'tokenIdentifier' => $trigger_code,
			);
			$fields[]     = array(
				'tokenId'         => 'FRIEND_FIRSTNAME',
				'tokenName'       => __( 'Friend first name', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			);
			$fields[]     = array(
				'tokenId'         => 'FRIEND_LASTNAME',
				'tokenName'       => __( 'Friend last name', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			);
			$fields[]     = array(
				'tokenId'         => 'FRIEND_EMAIL',
				'tokenName'       => __( 'Friend email', 'uncanny-automator' ),
				'tokenType'       => 'email',
				'tokenIdentifier' => $trigger_code,
			);
		} elseif ( isset( $args['triggers_meta']['code'] ) && 'BDBUSERNEWFOLLOWER' === $args['triggers_meta']['code'] ) {
			$trigger_code = $args['triggers_meta']['code'];
			$fields[]     = array(
				'tokenId'         => 'FOLLOWER_ID',
				'tokenName'       => __( 'Follower ID', 'uncanny-automator' ),
				'tokenType'       => 'int',
				'tokenIdentifier' => $trigger_code,
			);
			$fields[]     = array(
				'tokenId'         => 'FOLLOWER_FIRSTNAME',
				'tokenName'       => __( 'Follower first name', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			);
			$fields[]     = array(
				'tokenId'         => 'FOLLOWER_LASTNAME',
				'tokenName'       => __( 'Follower last name', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			);
			$fields[]     = array(
				'tokenId'         => 'FOLLOWER_EMAIL',
				'tokenName'       => __( 'Follower email', 'uncanny-automator' ),
				'tokenType'       => 'email',
				'tokenIdentifier' => $trigger_code,
			);
		}

		$tokens = array_merge( $tokens, $fields );

		return $tokens;
	}

	/**
	 * Topic possible tokens.
	 *
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function bdb_topic_possible_tokens( $tokens = array(), $args = array() ) {

		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}
		$trigger_integration = $args['integration'];
		$trigger_meta        = $args['meta'];

		$fields = array(
			array(
				'tokenId'         => 'BDBTOPICREPLY',
				'tokenName'       => __( 'Reply content', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'BDBUSERPOSTREPLYFORUM',
			),
		);

		$tokens = array_merge( $tokens, $fields );

		return $tokens;

	}

	/**
	 * Parse tokens.
	 *
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 *
	 * @return mixed
	 */
	public function parse_bp_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( $pieces ) {

			if ( in_array( 'BDBUSERS', $pieces, true ) ) {

				// Get user id from meta log
				if ( $trigger_data ) {

					foreach ( $trigger_data as $trigger ) {

						$token_value = $trigger['meta']['BDBUSERS'];

						$value = ( intval( $token_value ) === - 1 ) ? $user_id : $token_value;

					}
				}
			} elseif ( in_array( 'BDBUSERAVATAR', $pieces, true ) ) {

				// Get Group id from meta log
				if ( function_exists( 'get_avatar_url' ) ) {

					$value = get_avatar_url( $user_id );

				}
			} elseif ( in_array( 'BDBXPROFILE', $pieces, true ) ) {

				if ( isset( $pieces[2] ) && ! empty( $pieces[2] ) ) {

					$value = $this->get_profile_field_value( $user_id, $pieces[2] );

				}
			} elseif ( in_array( 'BDBTOPICREPLY', $pieces, true ) ) {

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
			} elseif ( in_array( 'BDBNEWTOPIC', $pieces, true ) ) {

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
			} elseif ( in_array( 'BDBUSERACTIVITY', $pieces, true ) || in_array( 'BDBUSERSENDSFRIENDREQUEST', $pieces, true ) ||
				in_array( 'BDBUSERACCEPTFRIENDREQUEST', $pieces, true ) || in_array( 'BDBUSERNEWFOLLOWER', $pieces, true ) ) {

				if ( $trigger_data ) {
					foreach ( $trigger_data as $trigger ) {
						$trigger_id     = $trigger['ID'];
						$trigger_log_id = $replace_args['trigger_log_id'];
						$meta_key       = $pieces[2];
						$meta_value     = Automator()->helpers->recipe->get_form_data_from_trigger_meta( $meta_key, $trigger_id, $trigger_log_id, $user_id );
						if ( ! empty( $meta_value ) ) {
							$value = maybe_unserialize( $meta_value );
						}
					}
				}
			} elseif ( in_array( 'BDBUSERPOSTREPLYFORUM', $pieces, true ) ) {

				foreach ( $trigger_data as $trigger ) {

					if ( in_array( $pieces[2], array( 'BDBFORUMS_ID', 'BDBFORUMS_URL', 'BDBFORUMS' ), true ) ) {

						$trigger_id     = $trigger['ID'];
						$trigger_log_id = $replace_args['trigger_log_id'];
						$topic_id       = Automator()->helpers->recipe->get_form_data_from_trigger_meta( 'BDBTOPIC', $trigger_id, $trigger_log_id, $user_id );
						$forum_id       = bbp_get_topic_forum_id( $topic_id );

						if ( 'BDBFORUMS_ID' === $pieces[2] ) {
							$value = $forum_id;
						} elseif ( 'BDBFORUMS_URL' === $pieces[2] ) {
							$value = get_permalink( $forum_id );
						} elseif ( 'BDBFORUMS' === $pieces[2] ) {
							$value = get_the_title( $forum_id );
						}
					}
				}
			}
		}

		return $value;

	}

	/**
	 * Method get_profile_field_value
	 *
	 * @param integer $user_id The user id.
	 * @param string  $piece The token piece.
	 *
	 * @return string The profile field value. Comma separated list of values for multiple selection.
	 */
	public function get_profile_field_value( $user_id = 0, $piece = '' ) {

		if ( ! function_exists( 'bp_get_profile_field_data' ) ) {
			return '';
		}

		$profile_field_value = bp_get_profile_field_data(
			array(
				'field'   => absint( $piece ),
				'user_id' => $user_id,
			)
		);

		$field = xprofile_get_field( absint( $piece ) );

		// Handle social media type first.
		if ( isset( $field->id ) && isset( $field->type ) && 'socialnetworks' === $field->type ) {
			list( $field_id, $field_key ) = explode( '|', $piece );
			return $this->field_social_media_value_retrieve( $field_key, $field->id, $user_id );
		}

		// Checkbox.
		if ( is_array( $profile_field_value ) ) {

			return implode( ', ', $profile_field_value );

		}

		return $profile_field_value;

	}

	/**
	 * Possible tokens.
	 *
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function bdb_bdbforums_possible_tokens( $tokens = array(), $args = array() ) {

		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		$trigger_integration = $args['integration'];
		$trigger_meta        = $args['meta'];

		$fields = array(
			array(
				'tokenId'         => 'BDBTOPICID',
				'tokenName'       => __( 'Topic ID', 'uncanny-automator' ),
				'tokenType'       => 'int',
				'tokenIdentifier' => 'BDBNEWTOPIC',
			),
			array(
				'tokenId'         => 'BDBTOPICTITLE',
				'tokenName'       => __( 'Topic title', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'BDBNEWTOPIC',
			),
			array(
				'tokenId'         => 'BDBTOPICURL',
				'tokenName'       => __( 'Topic URL', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'BDBNEWTOPIC',
			),
			array(
				'tokenId'         => 'BDBTOPICCONTENT',
				'tokenName'       => __( 'Topic content', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'BDBNEWTOPIC',
			),
		);

		$tokens = array_merge( $tokens, $fields );

		return $tokens;

	}

	/**
	 * This method assumes that the field is of social media type.
	 *
	 * @param string $field_key The social media field key (e.g. 'facebook', 'twitter').
	 * @param int $field_id The social media field ID.
	 * @param int $user_id The user ID.
	 *
	 * @return string The field value. Returns empty string if there is not value found.
	 */
	private function field_social_media_value_retrieve( $field_key, $field_id, $user_id ) {

		global $wpdb;

		/**
		 * Retrieve the field value directly from the DB. Can't locate a buddyboss function
		 * that does this for us. If you found one, replace this with BB built-in function.
		 * @since 4.15
		 **/
		$social_media_value_serialized = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT value 
					FROM {$wpdb->prefix}bp_xprofile_data
						WHERE field_id = %d
							AND user_id = %d
				",
				$field_id,
				$user_id
			)
		);

		// The value is stored as a serialized array in the DB.
		$field_val = maybe_unserialize( $social_media_value_serialized );

		if ( is_array( $field_val ) && isset( $field_val[ $field_key ] ) ) {
			return $field_val[ $field_key ];
		}

		return ''; // Return empty string.

	}

}
