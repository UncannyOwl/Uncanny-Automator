<?php

namespace Uncanny_Automator;

use UCTINCAN\Database;

/**
 *
 */
class Ld_Tokens {

	/**
	 *
	 */
	public function __construct() {
		add_filter(
			'automator_maybe_trigger_ld_ldquiz_tokens',
			array(
				$this,
				'possible_tokens_quiz_score_percent',
			),
			9999,
			2
		);

		add_filter(
			'automator_maybe_trigger_ld_tcmoduleinteraction_tokens',
			array(
				$this,
				'possible_tokens',
			),
			9999,
			2
		);

		add_filter(
			'automator_maybe_trigger_ld_ldcourse_tokens',
			array(
				$this,
				'possible_tokens_course_done',
			),
			9999,
			2
		);

		add_filter( 'automator_maybe_parse_token', array( $this, 'ld_tokens' ), 20, 6 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'ld_course_status_token' ), 9999, 6 );
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function possible_tokens_quiz_score_percent( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}
		if ( ! isset( $args['value'] ) || ! isset( $args['meta'] ) ) {
			return $tokens;
		}

		if ( empty( $args['value'] ) || empty( $args['meta'] ) ) {
			return $tokens;
		}
		if ( ! isset( $args['triggers_meta'] ) ) {
			return $tokens;
		}

		$trigger_meta = $args['meta'];
		$trigger_code = $args['triggers_meta']['code'];

		if ( 'LD_QUIZPERCENT' === $trigger_code ) {
			$new_tokens[] = array(
				'tokenId'         => $trigger_meta . '_achieved_percent',
				'tokenName'       => __( "User's quiz percentage", 'uncanny-automator' ),
				'tokenType'       => 'float',
				'tokenIdentifier' => $trigger_meta,
			);

			$new_tokens[] = array(
				'tokenId'         => $trigger_meta . '_quiz_passing_percentage',
				'tokenName'       => __( 'Passing score %', 'uncanny-automator' ),
				'tokenType'       => 'int',
				'tokenIdentifier' => $trigger_meta,
			);
			$tokens       = array_merge( $tokens, $new_tokens );
		}

		if ( 'LD_QUIZSCORE' === $trigger_code ) {
			$new_tokens[] = array(
				'tokenId'         => $trigger_meta . '_achieved_score',
				'tokenName'       => __( "User's quiz score", 'uncanny-automator' ),
				'tokenType'       => 'int',
				'tokenIdentifier' => $trigger_meta,
			);
			$tokens       = array_merge( $tokens, $new_tokens );
		}

		if ( 'LD_QUIZPOINT' === $trigger_code ) {
			$new_tokens[] = array(
				'tokenId'         => $trigger_meta . '_achieved_points',
				'tokenName'       => __( "User's quiz points", 'uncanny-automator' ),
				'tokenType'       => 'int',
				'tokenIdentifier' => $trigger_meta,
			);
			$tokens       = array_merge( $tokens, $new_tokens );
		}

		return $tokens;
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
	public function ld_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args = array() ) {

		if ( $pieces ) {
			if (
				in_array( 'TCMODULEINTERACTION_maybe_course', $pieces, true )
				|| in_array( 'TCMODULEINTERACTION_maybe_course_id', $pieces, true )
				|| in_array( 'TCMODULEINTERACTION_maybe_course_url', $pieces, true )
				|| in_array( 'TCMODULEINTERACTION_maybe_lesson', $pieces, true )
				|| in_array( 'TCMODULEINTERACTION_maybe_lesson_id', $pieces, true )
				|| in_array( 'TCMODULEINTERACTION_maybe_lesson_url', $pieces, true )
				|| in_array( 'TCMODULEINTERACTION_maybe_topic', $pieces, true )
				|| in_array( 'TCMODULEINTERACTION_maybe_topic_id', $pieces, true )
				|| in_array( 'TCMODULEINTERACTION_maybe_topic_url', $pieces, true )
				|| in_array( 'TCVERB', $pieces, true )
				|| in_array( 'QUIZPERCENT', $pieces, true )
				|| in_array( 'QUIZSCORE', $pieces, true )
				|| in_array( 'QUIZPOINT', $pieces, true )
				|| in_array( 'LDQUIZ', $pieces, true )
				|| in_array( 'LDQUIZ_ID', $pieces, true )
				|| in_array( 'LDQUIZ_URL', $pieces, true )
				|| in_array( 'TCMODULEINTERACTION', $pieces, true )
				|| in_array( 'LDQUIZ_achieved_percent', $pieces, true )
				|| in_array( 'LDQUIZ_quiz_passing_percentage', $pieces, true )
				|| in_array( 'LDQUIZ_achieved_score', $pieces, true )
				|| in_array( 'LDQUIZ_achieved_points', $pieces, true )
				|| in_array( 'LDCOURSE_course_completed_on', $pieces, true )
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

				// Required QUIZPERCENT token
				if ( in_array( 'QUIZPERCENT', $pieces, true ) ) {
					$t_data = array_shift( $trigger_data );
					if ( isset( $t_data['meta']['QUIZPERCENT'] ) ) {
						return $t_data['meta']['QUIZPERCENT'];
					}

					return $value;
				}

				// LDQUIZ token
				if ( is_array( $pieces ) && isset( $pieces[2] ) && 'LDQUIZ' === $pieces[2] ) {
					$t_data = array_shift( $trigger_data );
					if ( isset( $t_data['meta']['LDQUIZ'] ) && intval( '-1' ) !== intval( $t_data['meta']['LDQUIZ'] ) ) {
						return get_the_title( $t_data['meta']['LDQUIZ'] );
					}

					$quiz_id = Automator()->get->mayabe_get_token_meta_value_from_trigger_log( $trigger_id, $run_number, $recipe_id, 'quiz_id', $user_id, $recipe_log_id );
					if ( ! empty( $quiz_id ) ) {
						$value = get_the_title( $quiz_id );
					}

					return $value;
				}

				// LDQUIZ_ID token
				if ( is_array( $pieces ) && isset( $pieces[2] ) && 'LDQUIZ_ID' === $pieces[2] ) {
					$t_data = array_shift( $trigger_data );
					if ( isset( $t_data['meta']['LDQUIZ'] ) && intval( '-1' ) !== intval( $t_data['meta']['LDQUIZ'] ) ) {
						return $t_data['meta']['LDQUIZ'];
					}

					return Automator()->get->mayabe_get_token_meta_value_from_trigger_log( $trigger_id, $run_number, $recipe_id, 'quiz_id', $user_id, $recipe_log_id );
				}

				// LDQUIZ_URL token
				if ( is_array( $pieces ) && isset( $pieces[2] ) && 'LDQUIZ_URL' === $pieces[2] ) {
					$t_data = array_shift( $trigger_data );
					if ( isset( $t_data['meta']['LDQUIZ'] ) && intval( '-1' ) !== intval( $t_data['meta']['LDQUIZ'] ) ) {
						return get_permalink( $t_data['meta']['LDQUIZ'] );
					}

					return get_permalink( Automator()->get->mayabe_get_token_meta_value_from_trigger_log( $trigger_id, $run_number, $recipe_id, 'quiz_id', $user_id, $recipe_log_id ) );
				}

				// Required QUIZSCORE token
				if ( in_array( 'QUIZSCORE', $pieces, true ) ) {
					$t_data = array_shift( $trigger_data );
					if ( isset( $t_data['meta']['QUIZSCORE'] ) ) {
						return $t_data['meta']['QUIZSCORE'];
					}

					return $value;
				}

				// Required QUIZPOINT token
				if ( in_array( 'QUIZPOINT', $pieces, true ) ) {
					$t_data = array_shift( $trigger_data );
					if ( isset( $t_data['meta']['QUIZPOINT'] ) ) {
						return $t_data['meta']['QUIZPOINT'];
					}

					return $value;
				}

				// User's QUIZPERCENT token
				if ( in_array( 'LDQUIZ_achieved_percent', $pieces, true ) ) {
					return Automator()->get->mayabe_get_token_meta_value_from_trigger_log( $trigger_id, $run_number, $recipe_id, 'LDQUIZ_achieved_percent', $user_id, $recipe_log_id );
				}

				// LD QUIZPERCENT token
				if ( in_array( 'LDQUIZ_quiz_passing_percentage', $pieces, true ) ) {
					return Automator()->get->mayabe_get_token_meta_value_from_trigger_log( $trigger_id, $run_number, $recipe_id, 'LDQUIZ_quiz_passing_percentage', $user_id, $recipe_log_id );
				}

				// LD Course completion date token
				if ( in_array( 'LDCOURSE_course_completed_on', $pieces, true ) ) {
					$course_id = Automator()->get->mayabe_get_token_meta_value_from_trigger_log( $trigger_id, $run_number, $recipe_id, 'LDCOURSE', $user_id, $recipe_log_id );

					$t_data = array_shift( $trigger_data );
					if ( isset( $t_data['meta']['LDCOURSE'] ) && 0 === (int) $course_id ) {
						$course_id = $t_data['meta']['LDCOURSE'];
					}

					$completed_on = \learndash_user_get_course_completed_date( $user_id, $course_id );

					return \learndash_adjust_date_time_display( $completed_on );
				}

				// User's QUIZSCORE token
				if ( in_array( 'LDQUIZ_achieved_score', $pieces, true ) ) {
					return Automator()->get->mayabe_get_token_meta_value_from_trigger_log( $trigger_id, $run_number, $recipe_id, 'LDQUIZ_achieved_score', $user_id, $recipe_log_id );
				}

				// User's QUIZPOINTS token
				if ( in_array( 'LDQUIZ_achieved_points', $pieces, true ) ) {
					return Automator()->get->mayabe_get_token_meta_value_from_trigger_log( $trigger_id, $run_number, $recipe_id, 'LDQUIZ_achieved_points', $user_id, $recipe_log_id );
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
						if ( in_array( 'TCMODULEINTERACTION_maybe_course', $pieces, true ) && ! empty( $tin_can_data->course_id ) ) {
							$value = get_the_title( $tin_can_data->course_id );
						}
						if ( in_array( 'TCMODULEINTERACTION_maybe_course_id', $pieces, true ) && ! empty( $tin_can_data->course_id ) ) {
							$value = $tin_can_data->course_id;
						}
						if ( in_array( 'TCMODULEINTERACTION_maybe_course_url', $pieces, true ) && ! empty( $tin_can_data->course_id ) ) {
							$value = get_the_permalink( $tin_can_data->course_id );
						}
						$post_type = get_post_type( $tin_can_data->lesson_id );
						if ( 'sfwd-lessons' === $post_type ) {
							if ( in_array( 'TCMODULEINTERACTION_maybe_lesson', $pieces, true ) && ! empty( $tin_can_data->lesson_id ) ) {
								$value = get_the_title( $tin_can_data->lesson_id );
							}
							if ( in_array( 'TCMODULEINTERACTION_maybe_lesson_id', $pieces, true ) && ! empty( $tin_can_data->lesson_id ) ) {
								$value = $tin_can_data->lesson_id;
							}
							if ( in_array( 'TCMODULEINTERACTION_maybe_lesson_url', $pieces, true ) && ! empty( $tin_can_data->lesson_id ) ) {
								$value = get_the_permalink( $tin_can_data->lesson_id );
							}
						} else {
							if ( in_array( 'TCMODULEINTERACTION_maybe_topic', $pieces, true ) && ! empty( $tin_can_data->lesson_id ) ) {
								$value = get_the_title( $tin_can_data->lesson_id );
							}
							if ( in_array( 'TCMODULEINTERACTION_maybe_topic_id', $pieces, true ) && ! empty( $tin_can_data->lesson_id ) ) {
								$value = $tin_can_data->lesson_id;
							}
							if ( in_array( 'TCMODULEINTERACTION_maybe_topic_url', $pieces, true ) && ! empty( $tin_can_data->lesson_id ) ) {
								$value = get_the_permalink( $tin_can_data->lesson_id );
							}
							if ( in_array( 'TCMODULEINTERACTION_maybe_lesson', $pieces, true ) || in_array( 'TCMODULEINTERACTION_maybe_lesson_id', $pieces, true ) || in_array( 'TCMODULEINTERACTION_maybe_lesson_url', $pieces, true ) ) {
								if ( ( ! empty( $tin_can_data->course_id ) ) && ( \LearnDash_Settings_Section::get_section_setting( 'LearnDash_Settings_Courses_Builder', 'shared_steps' ) == 'yes' ) ) {
									$lesson_id = learndash_course_get_single_parent_step( $tin_can_data->course_id, $tin_can_data->lesson_id );
									if ( in_array( 'TCMODULEINTERACTION_maybe_lesson', $pieces, true ) && ! empty( $lesson_id ) ) {
										$value = get_the_title( $lesson_id );
									}
									if ( in_array( 'TCMODULEINTERACTION_maybe_lesson_id', $pieces, true ) && ! empty( $lesson_id ) ) {
										$value = $lesson_id;
									}
									if ( in_array( 'TCMODULEINTERACTION_maybe_lesson_url', $pieces, true ) && ! empty( $lesson_id ) ) {
										$value = get_the_permalink( $lesson_id );
									}
								}
							}
						}
					}
				}

				if ( $trigger_data ) {
					foreach ( $trigger_data as $trigger ) {
						$quiz_id = 0;
						if ( isset( $trigger['meta']['LDQUIZ'] ) && intval( '-1' ) !== intval( $trigger['meta']['LDQUIZ'] ) ) {
							$quiz_id = $trigger['meta']['LDQUIZ'];
						} else {
							$r_quiz_id = Automator()->get->mayabe_get_token_meta_value_from_trigger_log( $trigger_id, $run_number, $recipe_id, 'quiz_id', $user_id, $recipe_log_id );
							if ( ! empty( $r_quiz_id ) ) {
								$quiz_id = $r_quiz_id;
							}
						}

						if ( intval( '-1' ) === intval( $quiz_id ) ) {
							if ( automator_filter_has_var( 'quiz', INPUT_POST ) ) {
								$quiz_id = absint( automator_filter_input( 'quiz', INPUT_POST ) );
								if ( $quiz_id > 0 ) {
									if ( in_array( 'LDQUIZ', $pieces, true ) ) {
										$value = get_the_title( $quiz_id );
									}

									if ( in_array( 'LDQUIZ_ID', $pieces, true ) ) {
										$value = $quiz_id;
									}

									if ( in_array( 'LDQUIZ_URL', $pieces, true ) ) {
										$value = get_permalink( $quiz_id );
									}
								}
							}
						}
					}
				}
			}
		}

		return $value;
	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return mixed|string
	 */
	public function ld_course_status_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args = array() ) {

		if ( empty( $pieces ) || empty( $trigger_data ) || empty( $replace_args ) ) {
			return $value;
		}
		if ( ! in_array( 'LDCOURSE_STATUS', $pieces, true ) ) {
			return $value;
		}

		if ( ! absint( $user_id ) ) {
			return $value;
		}

		if ( ! absint( $recipe_id ) ) {
			return $value;
		}
		$course_id = (int) Automator()->db->token->get( 'LDCOURSE', $replace_args );
		if ( empty( $course_id ) ) {
			$trigger = array_shift( $trigger_data );
			if ( isset( $trigger['meta'] ) && isset( $trigger['meta']['LDCOURSE'] ) && intval( '-1' ) !== intval( $trigger['meta']['LDCOURSE'] ) ) {
				$course_id = absint( $trigger['meta']['LDCOURSE'] );
			}
		}
		if ( empty( $course_id ) ) {
			return '-';
		}

		return learndash_course_status( $course_id, $user_id );
	}

	/**
	 * @param $tokens
	 * @param $args
	 *
	 * @return array|mixed
	 */
	public function possible_tokens_course_done( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}
		if ( ! isset( $args['meta'] ) ) {
			return $tokens;
		}

		if ( empty( $args['meta'] ) ) {
			return $tokens;
		}
		if ( ! isset( $args['triggers_meta'] ) ) {
			return $tokens;
		}

		$trigger_meta = $args['meta'];
		$trigger_code = $args['triggers_meta']['code'];

		if ( 'COURSEDONE' === $trigger_code ) {
			$new_tokens[] = array(
				'tokenId'         => $trigger_meta . '_course_completed_on',
				'tokenName'       => __( 'Course completion date', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			);

			$tokens = array_merge( $tokens, $new_tokens );
		}

		return $tokens;
	}
}
