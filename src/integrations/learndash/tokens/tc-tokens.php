<?php

namespace Uncanny_Automator;


use UCTINCAN\Database;

/**
 * Class Tc_Tokens
 * @package Uncanny_Automator
 */
class Tc_Tokens {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'LD';

	public function __construct() {
		//*************************************************************//
		// See this filter generator AT automator-get-data.php
		// in function recipe_trigger_tokens()
		//*************************************************************//
		add_filter( 'automator_maybe_trigger_ld_tcmoduleinteraction_tokens', [ $this, 'possible_tokens' ], 9999, 2 );
		add_filter( 'automator_maybe_parse_token', [ $this, 'tc_token' ], 20, 6 );
	}

	/**
	 * Only load this integration and its triggers and actions if the related plugin is active
	 *
	 * @param $status
	 * @param $plugin
	 *
	 * @return bool
	 */
	public function plugin_active( $status, $plugin ) {

		if ( self::$integration === $plugin ) {
			if ( defined( 'UNCANNY_REPORTING_VERSION' ) ) {
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
	public function possible_tokens( $tokens = [], $args = [] ) {

		if ( ! isset( $args['value'] ) || ! isset( $args['meta'] ) ) {
			return $tokens;
		}

		if ( empty( $args['value'] ) || empty( $args['meta'] ) ) {
			return $tokens;
		}

		$tc_module_id = $args['value'];
		$trigger_meta = $args['meta'];

		$new_tokens = [];
		if ( ! empty( $tc_module_id ) && absint( $tc_module_id ) ) {

			$new_tokens[] = [
				'tokenId'         => $tc_module_id,
				'tokenName'       => __( 'Course title', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta . '_maybe_course',
			];
			$new_tokens[] = [
				'tokenId'         => $tc_module_id,
				'tokenName'       => __( 'Course ID', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta . '_maybe_course_id',
			];
			$new_tokens[] = [
				'tokenId'         => $tc_module_id,
				'tokenName'       => __( 'Course URL', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta . '_maybe_course_url',
			];

			$new_tokens[] = [
				'tokenId'         => $tc_module_id,
				'tokenName'       => __( 'Lesson/Topic title', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta . '_maybe_lesson',
			];

			$new_tokens[] = [
				'tokenId'         => $tc_module_id,
				'tokenName'       => __( 'Lesson/Topic ID', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta . '_maybe_lesson_id',
			];

			$new_tokens[] = [
				'tokenId'         => $tc_module_id,
				'tokenName'       => __( 'Lesson/Topic URL', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta . '_maybe_lesson_url',
			];

			$tokens = array_merge( $tokens, $new_tokens );
		}

		return $tokens;
	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 *
	 * @return string|null
	 */
	public function tc_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args = [] ) {

		if ( $pieces ) {
			if (
				in_array( 'TCMODULEINTERACTION_maybe_course', $pieces )
				|| in_array( 'TCMODULEINTERACTION_maybe_course_id', $pieces )
				|| in_array( 'TCMODULEINTERACTION_maybe_course_url', $pieces )
				|| in_array( 'TCMODULEINTERACTION_maybe_lesson', $pieces )
				|| in_array( 'TCMODULEINTERACTION_maybe_lesson_id', $pieces )
				|| in_array( 'TCMODULEINTERACTION_maybe_lesson_url', $pieces )
				|| in_array( 'TCVERB', $pieces )
				|| in_array( 'QUIZPERCENT', $pieces )
				|| in_array( 'TCMODULEINTERACTION', $pieces )
			) {
				if ( ! absint( $user_id ) ) {
					return $value;
				}

				if ( ! absint( $recipe_id ) ) {
					return $value;
				}

				global $uncanny_automator;
				$replace_pieces = $replace_args['pieces'];
				$recipe_id      = $replace_args['recipe_id'];
				$trigger_log_id = $replace_args['trigger_log_id'];
				$run_number     = $replace_args['run_number'];
				$user_id        = $replace_args['user_id'];
				$trigger_id     = absint( $replace_pieces[0] );

				// Verb can be found from trigger meta
				if ( in_array( 'TCVERB', $pieces ) ) {
					$value = $uncanny_automator->get->maybe_get_meta_value_from_trigger_log( 'TCVERB', $trigger_id, $trigger_log_id, $run_number, $user_id );

					return $value;
				}

				// Verb can be found from trigger meta
				if ( in_array( 'QUIZPERCENT', $pieces ) ) {
					$value = $uncanny_automator->get->maybe_get_meta_value_from_trigger_log( 'QUIZPERCENT', $trigger_id, $trigger_log_id, $run_number, $user_id );

					return $value;
				}

				// Otherwise get TC module id from trigger meta
				$module_id = $uncanny_automator->get->maybe_get_meta_value_from_trigger_log( 'TCMODULEINTERACTION', $trigger_id, $trigger_log_id, $run_number, $user_id );

				if ( class_exists( '\UCTINCAN\Database' ) ) {
					// In case just module name required.
					if ( in_array( 'TCMODULEINTERACTION', $pieces ) ) {
						$module_info = \TINCANNYSNC\Database::get_item( $module_id );
						$value       = isset( $module_info['file_name'] ) ? $module_info['file_name'] : '';

						return $value;
					}

					// For other tokens.
					global $wpdb;

					$table_name   = $wpdb->prefix . Database::TABLE_REPORTING;
					$q            = "SELECT * FROM {$table_name} WHERE user_id = {$user_id} AND module LIKE '%/uncanny-snc/{$module_id}/%' ORDER BY xstored DESC LIMIT 0,1";
					$tin_can_data = $wpdb->get_row( $q );

					if ( ! empty( $tin_can_data ) ) {
						if ( in_array( 'TCMODULEINTERACTION_maybe_course', $pieces, true ) && ! empty( $tin_can_data->course_id ) ) {
							$value = get_the_title( $tin_can_data->course_id );
						}
						if ( in_array( 'TCMODULEINTERACTION_maybe_course_id', $pieces, true ) && ! empty( $tin_can_data->course_id ) ) {
							$value = $tin_can_data->course_id;
						}
						if ( in_array( 'TCMODULEINTERACTION_maybe_course_url', $pieces, true ) && ! empty( $tin_can_data->course_id ) ) {
							$value = get_the_permalink( $tin_can_data->course_id );
						}
						if ( in_array( 'TCMODULEINTERACTION_maybe_lesson', $pieces, true ) && ! empty( $tin_can_data->lesson_id ) ) {
							$value = get_the_title( $tin_can_data->lesson_id );
						}
						if ( in_array( 'TCMODULEINTERACTION_maybe_lesson_id', $pieces, true ) && ! empty( $tin_can_data->lesson_id ) ) {
							$value = $tin_can_data->lesson_id;
						}
						if ( in_array( 'TCMODULEINTERACTION_maybe_lesson_url', $pieces, true ) && ! empty( $tin_can_data->lesson_id ) ) {
							$value = get_the_permalink( $tin_can_data->lesson_id );
						}
					}
				}
			}
		}

		return $value;
	}
}