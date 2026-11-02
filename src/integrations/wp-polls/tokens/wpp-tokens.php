<?php

namespace Uncanny_Automator;

/**
 * Class Masterstudy_Tokens
 *
 * @package Uncanny_Automator
 */
class Wpp_Tokens {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WPP';

	public function __construct() {
		add_filter( 'automator_maybe_parse_token', array( $this, 'wp_polls_token' ), 20, 6 );
	}

	/**
	 * Parse the token.
	 *
	 * @param string $value .
	 * @param array $pieces .
	 * @param string $recipe_id .
	 *
	 * @param        $trigger_data
	 * @param        $user_id
	 * @param        $replace_args
	 *
	 * @return null|string
	 */
	public function wp_polls_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( $pieces ) {
			if (
				in_array( 'WPPOLLANSWERSUBMIT', $pieces, true )
				|| in_array( 'WPPOLLSUBMIT', $pieces, true )
			) {

				global $wpdb;
				$trigger_id     = $pieces[0];
				$trigger_meta   = $pieces[2];
				$trigger_log_id = isset( $replace_args['trigger_log_id'] ) ? absint( $replace_args['trigger_log_id'] ) : 0;

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Automator's own table; no WordPress API for it. Live run state read; must not be cached.
				$poll_id = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT meta_value
FROM {$wpdb->prefix}uap_trigger_log_meta
WHERE meta_key = %s
  AND automator_trigger_log_id = %d
  AND automator_trigger_id = %d
LIMIT 0, 1",
						'WPPOLL',
						$trigger_log_id,
						$trigger_id
					)
				);

				switch ( $trigger_meta ) {
					case 'WPPOLL':
						// Get Poll Questions
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP Polls' own table; the plugin offers no API for this query.
						$question = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = %d", $poll_id ) );

						if ( null !== $question ) {
							return $question;
						}
						break;
					case 'WPPOLL_ANSWERS':
						// Get Poll Answers
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP Polls' own table; the plugin offers no API for this query.
						$answers = $wpdb->get_results( $wpdb->prepare( "SELECT polla_answers FROM $wpdb->pollsa WHERE polla_qid = %d ORDER BY polla_aid DESC", $poll_id ) );

						if ( null !== $answers ) {
							$value = '';
							foreach ( $answers as $answer ) {
								$value .= $answer->polla_answers . "\r\n";
							}

							// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public hook name, kept for compatibility.
							return apply_filters( 'uap_wp_polls_token_WPPOLL_ANSWERS', $value, $poll_id, $answers, $trigger_id, $trigger_meta, $trigger_log_id );
						}

						break;
					case 'WPPOLL_START':
						// Get Poll start timestamp
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP Polls' own table; the plugin offers no API for this query.
						$start_timestamp = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_timestamp FROM $wpdb->pollsq WHERE pollq_id = %d", $poll_id ) );

						if ( null !== $start_timestamp && absint( $start_timestamp ) ) {
							// translators: 1: Date, 2: Time
							$poll_date = mysql2date( sprintf( __( '%1$s @ %2$s', 'wp-polls' ), get_option( 'date_format' ), get_option( 'time_format' ) ), gmdate( 'Y-m-d H:i:s', $start_timestamp ) ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch

							return $poll_date;
						}

						return esc_html__( 'Not set', 'uncanny-automator' );
						break;
					case 'WPPOLL_END':
						// Get Poll end timestamp
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP Polls' own table; the plugin offers no API for this query.
						$end_timestamp = $wpdb->get_var( $wpdb->prepare( "SELECT pollq_expiry FROM $wpdb->pollsq WHERE pollq_id = %d", $poll_id ) );

						if ( null !== $end_timestamp && absint( $end_timestamp ) ) {
							// translators: 1: Date, 2: Time
							$poll_date = mysql2date( sprintf( __( '%1$s @ %2$s', 'wp-polls' ), get_option( 'date_format' ), get_option( 'time_format' ) ), gmdate( 'Y-m-d H:i:s', $end_timestamp ) ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch

							return $poll_date;
						}

						return esc_html__( 'Not set', 'uncanny-automator' );
						break;
					case 'WPPOLLANSWER':
					case 'WPPOLL_WPPOLLANSWER':
						// Get user's answer ids
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Automator's own table; no WordPress API for it. Live run state read; must not be cached.
						$answer_ids = $wpdb->get_var(
							$wpdb->prepare(
								"SELECT meta_value
FROM {$wpdb->prefix}uap_trigger_log_meta
WHERE meta_key = %s
  AND automator_trigger_log_id = %d
  AND automator_trigger_id = %d
LIMIT 0, 1",
								'WPPOLLANSWER',
								$trigger_log_id,
								$trigger_id
							)
						);

						if ( null !== $answer_ids ) {

							$answer_ids = automator_safe_unserialize( $answer_ids );
							$safe_ids   = array_map( 'absint', (array) $answer_ids );
							$poll_id    = absint( $poll_id );

							// Get user's Poll Answers
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP Polls' own table; the plugin offers no API for this query.
							$answers = $wpdb->get_results(
								$wpdb->prepare(
									"SELECT polla_answers FROM $wpdb->pollsa WHERE polla_qid = %d AND polla_aid IN (" . implode( ',', array_fill( 0, count( $safe_ids ), '%d' ) ) . ') ORDER BY polla_aid DESC',
									array_merge( array( $poll_id ), $safe_ids )
								)
							);

							if ( null !== $answers ) {
								$value = '';
								foreach ( $answers as $answer ) {
									$value .= $answer->polla_answers . "\r\n";
								}

								// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public hook name, kept for compatibility.
								return apply_filters( 'uap_wp_polls_token_WPPOLLANSWER', $value, $poll_id, $answers, $trigger_id, $trigger_meta, $trigger_log_id );
							}
						}
						break;
				}

				return '';
			}
		}

		return $value;
	}
}
