<?php

namespace Uncanny_Automator;

use UCTINCAN\Database;

/**
 *
 */
class UOTC_Tokens {

	/**
	 *
	 */
	public function __construct() {

		add_filter(
			'automator_maybe_trigger_uotc_tcmoduleinteraction_tokens',
			array(
				$this,
				'possible_tokens',
			),
			9999,
			2
		);

		add_filter(
			'automator_maybe_trigger_uotc_tcuserattainsscore_tokens',
			array(
				$this,
				'possible_tokens',
			),
			9999,
			2
		);

		add_filter( 'automator_maybe_parse_token', array( $this, 'uotc_tokens' ), 20, 6 );
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		if ( ! isset( $args['value'] ) || ! isset( $args['meta'] ) ) {
			return $tokens;
		}

		if ( empty( $args['value'] ) || empty( $args['meta'] ) ) {
			return $tokens;
		}

		$tc_module_id = $args['value'];
		$trigger_meta = $args['meta'];

		$new_tokens = array();

		if ( ! empty( $tc_module_id ) && absint( $tc_module_id ) ) {

			$new_tokens[] = array(
				'tokenId'         => $tc_module_id,
				'tokenName'       => __( 'Course title', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta . '_maybe_course',
			);
			$new_tokens[] = array(
				'tokenId'         => $tc_module_id,
				'tokenName'       => __( 'Course ID', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta . '_maybe_course_id',
			);
			$new_tokens[] = array(
				'tokenId'         => $tc_module_id,
				'tokenName'       => __( 'Course URL', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta . '_maybe_course_url',
			);
			$new_tokens[] = array(
				'tokenId'         => $tc_module_id,
				'tokenName'       => __( 'Lesson title', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta . '_maybe_lesson',
			);
			$new_tokens[] = array(
				'tokenId'         => $tc_module_id,
				'tokenName'       => __( 'Lesson ID', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta . '_maybe_lesson_id',
			);
			$new_tokens[] = array(
				'tokenId'         => $tc_module_id,
				'tokenName'       => __( 'Lesson URL', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta . '_maybe_lesson_url',
			);
			$new_tokens[] = array(
				'tokenId'         => $tc_module_id,
				'tokenName'       => __( 'Topic title', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta . '_maybe_topic',
			);
			$new_tokens[] = array(
				'tokenId'         => $tc_module_id,
				'tokenName'       => __( 'Topic ID', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta . '_maybe_topic_id',
			);
			$new_tokens[] = array(
				'tokenId'         => $tc_module_id,
				'tokenName'       => __( 'Topic URL', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta . '_maybe_topic_url',
			);

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
	 * @param array $replace_args
	 *
	 * @return string|null
	 */
	public function uotc_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args = array() ) {
		if ( ! is_array( $trigger_data ) ) {
			return $value;
		}
		$trigger_meta_data = array_shift( $trigger_data );
		if ( ! isset( $trigger_meta_data['meta'] ) || ! isset( $trigger_meta_data['meta']['code'] ) ) {
			return $value;
		}
		$trigger_key_maybe_prefix = 'TC' . $trigger_meta_data['meta']['code'] . '_maybe_';

		if ( $pieces ) {

			if (
				in_array( $trigger_key_maybe_prefix . 'course', $pieces, true )
				|| in_array( $trigger_key_maybe_prefix . 'course_id', $pieces, true )
				|| in_array( $trigger_key_maybe_prefix . 'course_url', $pieces, true )
				|| in_array( $trigger_key_maybe_prefix . 'lesson', $pieces, true )
				|| in_array( $trigger_key_maybe_prefix . 'lesson_id', $pieces, true )
				|| in_array( $trigger_key_maybe_prefix . 'lesson_url', $pieces, true )
				|| in_array( $trigger_key_maybe_prefix . 'topic', $pieces, true )
				|| in_array( $trigger_key_maybe_prefix . 'topic_id', $pieces, true )
				|| in_array( $trigger_key_maybe_prefix . 'topic_url', $pieces, true )
				|| in_array( 'TCVERB', $pieces, true )
				|| in_array( 'TCUSERATTAINSSCORE', $pieces, true )
				|| in_array( 'TCMODULEINTERACTION', $pieces, true )
				|| in_array( 'NUMBERCOND', $pieces, true )
			) {

				if ( ! absint( $user_id ) ) {
					return $value;
				}

				if ( ! absint( $recipe_id ) ) {
					return $value;
				}

				$replace_pieces       = $replace_args['pieces'];
				$recipe_id            = $replace_args['recipe_id'];
				$run_number           = $replace_args['run_number'];
				$user_id              = $replace_args['user_id'];
				$recipe_log_id        = $replace_args['recipe_log_id'];
				$trigger_id           = $replace_args['trigger_id'];
				$trigger_log_id       = $replace_args['trigger_log_id'];
				$maybe_trigger_log_id = Automator()->get->mayabe_get_real_trigger_log_id( $trigger_id, $run_number, $recipe_id, $user_id, $recipe_log_id );

				// Verb can be found from trigger meta
				if ( in_array( 'TCVERB', $pieces ) ) {
					$value = Automator()->get->maybe_get_meta_value_from_trigger_log( 'TCVERB', $trigger_id, $trigger_log_id, $run_number, $user_id );

					return $value;
				}

				// Score can be found from trigger meta
				if ( in_array( 'TCUSERATTAINSSCORE', $pieces ) ) {
					$value = Automator()->get->maybe_get_meta_value_from_trigger_log( 'TCUSERATTAINSSCORE', $trigger_id, $trigger_log_id, $run_number, $user_id );

					return $value;
				}

				// Otherwise get TC module id from trigger meta
				$module_id = Automator()->get->maybe_get_meta_value_from_trigger_log( 'TCMODULEINTERACTION', $trigger_id, $trigger_log_id, $run_number, $user_id );

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
					$tin_can_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE user_id = %d AND module LIKE %s ORDER BY xstored DESC LIMIT 0,1", $user_id, '%%/uncanny-snc/' . $module_id . '/%%' ) ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

					if ( ! empty( $tin_can_data ) ) {
						if ( in_array( $trigger_key_maybe_prefix . 'course', $pieces, true ) && ! empty( $tin_can_data->course_id ) ) {
							$value = get_the_title( $tin_can_data->course_id );
						}
						if ( in_array( $trigger_key_maybe_prefix . 'course_id', $pieces, true ) && ! empty( $tin_can_data->course_id ) ) {
							$value = $tin_can_data->course_id;
						}
						if ( in_array( $trigger_key_maybe_prefix . 'course_url', $pieces, true ) && ! empty( $tin_can_data->course_id ) ) {
							$value = get_the_permalink( $tin_can_data->course_id );
						}
						$post_type = get_post_type( $tin_can_data->lesson_id );
						if ( 'sfwd-lessons' === $post_type ) {
							if ( in_array( $trigger_key_maybe_prefix . 'lesson', $pieces, true ) && ! empty( $tin_can_data->lesson_id ) ) {
								$value = get_the_title( $tin_can_data->lesson_id );
							}
							if ( in_array( $trigger_key_maybe_prefix . 'lesson_id', $pieces, true ) && ! empty( $tin_can_data->lesson_id ) ) {
								$value = $tin_can_data->lesson_id;
							}
							if ( in_array( $trigger_key_maybe_prefix . 'lesson_url', $pieces, true ) && ! empty( $tin_can_data->lesson_id ) ) {
								$value = get_the_permalink( $tin_can_data->lesson_id );
							}
						} else {
							if ( in_array( $trigger_key_maybe_prefix . 'topic', $pieces, true ) && ! empty( $tin_can_data->lesson_id ) ) {
								$value = get_the_title( $tin_can_data->lesson_id );
							}
							if ( in_array( $trigger_key_maybe_prefix . 'topic_id', $pieces, true ) && ! empty( $tin_can_data->lesson_id ) ) {
								$value = $tin_can_data->lesson_id;
							}
							if ( in_array( $trigger_key_maybe_prefix . 'topic_url', $pieces, true ) && ! empty( $tin_can_data->lesson_id ) ) {
								$value = get_the_permalink( $tin_can_data->lesson_id );
							}
							if ( in_array( $trigger_key_maybe_prefix . 'lesson', $pieces, true ) || in_array( $trigger_key_maybe_prefix . 'lesson_id', $pieces, true ) || in_array( $trigger_key_maybe_prefix . 'lesson_url', $pieces, true ) ) {
								if ( ( ! empty( $tin_can_data->course_id ) ) && ( \LearnDash_Settings_Section::get_section_setting( 'LearnDash_Settings_Courses_Builder', 'shared_steps' ) == 'yes' ) ) {
									$lesson_id = learndash_course_get_single_parent_step( $tin_can_data->course_id, $tin_can_data->lesson_id );
									if ( in_array( $trigger_key_maybe_prefix . 'lesson', $pieces, true ) && ! empty( $lesson_id ) ) {
										$value = get_the_title( $lesson_id );
									}
									if ( in_array( $trigger_key_maybe_prefix . 'lesson_id', $pieces, true ) && ! empty( $lesson_id ) ) {
										$value = $lesson_id;
									}
									if ( in_array( $trigger_key_maybe_prefix . 'lesson_url', $pieces, true ) && ! empty( $lesson_id ) ) {
										$value = get_the_permalink( $lesson_id );
									}
								}
							}
						}
					}
				}

				if ( in_array( 'NUMBERCOND', $pieces ) ) {
					$parse = $pieces[2];
					$val   = Automator()->get->maybe_get_meta_value_from_trigger_log( 'NUMBERCOND', $trigger_id, $trigger_log_id, $run_number, $user_id );

					switch ( $val ) {
						case '<':
							$value = esc_attr__( 'less than', 'uncanny-automator' );
							break;
						case '>':
							$value = esc_attr__( 'greater than', 'uncanny-automator' );
							break;
						case '=':
							$value = esc_attr__( 'equal to', 'uncanny-automator' );
							break;
						case '!=':
							$value = esc_attr__( 'not equal to', 'uncanny-automator' );
							break;
						case '>=':
							$value = esc_attr__( 'greater or equal to', 'uncanny-automator' );
							break;
						case '<=':
							$value = esc_attr__( 'less or equal to', 'uncanny-automator' );
							break;
						default:
							$value = '';
							break;
					}

					return $value;
				}
			}
		}

		return $value;
	}
}
