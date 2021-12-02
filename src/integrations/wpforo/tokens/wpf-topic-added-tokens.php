<?php

namespace Uncanny_Automator;

/**
 * Class WPF_USER_REPLIES_TO_TOPIC_TOKENS
 *
 * @package Uncanny_Automator
 */
class WPF_TOPIC_ADDED_TOKENS {


	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WPFORO';

	public function __construct() {
		add_filter( 'automator_maybe_parse_token', array( $this, 'user_replies_to_topic' ), 20, 6 );
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
	 *
	 * @return mixed
	 */
	public function user_replies_to_topic( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		$tokens = array(
			'WPFORO_FORUM',
			'WPFORO_FORUM_ID',
			'WPFORO_FORUM_URL',
			'WPFORO_TOPIC',
			'WPFORO_TOPIC_ID',
			'WPFORO_TOPIC_URL',
			'WPFORO_TOPIC_CONTENT',
		);

		if ( $pieces && isset( $pieces[2] ) ) {
			$meta_field = $pieces[2];

			if ( ! empty( $meta_field ) && in_array( $meta_field, $tokens ) ) {
				if ( $trigger_data ) {
					foreach ( $trigger_data as $trigger ) {

						$trigger_id     = $trigger['ID'];
						$trigger_log_id = $replace_args['trigger_log_id'];
						$run_number     = $replace_args['run_number'];
						$user_id        = $replace_args['user_id'];

						$forum_id = absint(
							Automator()->get->get_trigger_log_meta(
								'WPFORO_TOPIC_FORUM_ID',
								$trigger_id,
								$trigger_log_id,
								$run_number,
								$user_id
							)
						);

						$forum = array();
						if ( $forum_id ) {
							$forum = WPF()->forum->get_forum( $forum_id );
						}

						$topic_id = absint(
							Automator()->get->get_trigger_log_meta(
								'WPFORO_TOPIC_ID',
								$trigger_id,
								$trigger_log_id,
								$run_number,
								$user_id
							)
						);

						$topic = array();
						if ( $topic_id ) {
							$topic = WPF()->topic->get_topic( $topic_id );
						}

						switch ( $meta_field ) {
							case 'WPFORO_FORUM':
								if ( ! empty( $forum ) && isset( $forum['title'] ) ) {
									$value = $forum['title'];
								}
								break;
							case 'WPFORO_FORUM_ID':
								if ( $forum_id ) {
									$value = $forum_id;
								}
								break;
							case 'WPFORO_FORUM_URL':
								if ( ! empty( $forum ) && isset( $forum['slug'] ) ) {
									$value = wpforo_home_url( utf8_uri_encode( $forum['slug'] ) );
								}
								break;
							case 'WPFORO_TOPIC':
								if ( ! empty( $topic ) && isset( $topic['title'] ) ) {
									$value = $topic['title'];
								}
								break;
							case 'WPFORO_TOPIC_ID':
								if ( $topic_id ) {
									$value = $topic_id;
								}
								break;
							case 'WPFORO_TOPIC_URL':
								if ( ! empty( $forum ) && isset( $forum['slug'] ) ) {
									if ( ! empty( $topic ) && isset( $topic['slug'] ) ) {
										$value = wpforo_home_url( $forum['slug'] . '/' . $topic['slug'] );
									}
								}
								break;
							case 'WPFORO_TOPIC_CONTENT':
								if ( ! empty( $topic ) && isset( $topic['first_postid'] ) ) {
									$first_post = wpforo_post( $topic['first_postid'] );
									if ( ! empty( $first_post ) && isset( $first_post['body'] ) ) {
										$value = $first_post['body'];
									}
								}
								break;
						}
					}
				}
			}
		}

		return $value;
	}

	/**
	 * @param $meta_key
	 * @param $trigger_id
	 *
	 * @return mixed|string
	 */
	public function get_form_data_from_trigger_meta( $meta_key, $trigger_id ) {
		global $wpdb;
		if ( empty( $meta_key ) || empty( $trigger_id ) ) {
			return '';
		}

		$meta_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}uap_trigger_log_meta WHERE meta_key = %s AND automator_trigger_id = %d ORDER BY ID DESC LIMIT 0,1", $meta_key, $trigger_id ) );

		if ( ! empty( $meta_value ) ) {
			return maybe_unserialize( $meta_value );
		}

		return '';
	}
}
