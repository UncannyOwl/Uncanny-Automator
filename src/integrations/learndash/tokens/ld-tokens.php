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
	public function __construct( $load_action_hook = true ) {

		if ( ! $load_action_hook ) {
			return;
		}

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
			'automator_maybe_trigger_ld_ldquiz_tokens',
			array(
				$this,
				'possible_tokens_quiz_q_and_a',
			),
			PHP_INT_MAX - 1,
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
		add_filter( 'automator_maybe_parse_token', array( $this, 'ld_course_access_expiry_token' ), 9999, 6 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'ld_quiz_parse_q_and_a_tokens' ), 20, 6 );

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

			$quiz_data_tokens       = $this->get_quiz_data_tokens();
			$pieces_quiz_token_data = array_values( array_intersect( $pieces, $quiz_data_tokens ) );

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
				|| in_array( 'TCMODULEINTERACTION', $pieces, true )
				|| in_array( 'LDQUIZ_achieved_percent', $pieces, true )
				|| in_array( 'LDQUIZ_quiz_passing_percentage', $pieces, true )
				|| in_array( 'LDQUIZ_achieved_score', $pieces, true )
				|| in_array( 'LDQUIZ_achieved_points', $pieces, true )
				|| in_array( 'LDCOURSE_course_completed_on', $pieces, true )
				|| in_array( 'LDCOURSE_COURSE_CUMULATIVE_TIME', $pieces, true )
				|| in_array( 'LDCOURSE_COURSE_TIME_AT_COMPLETION', $pieces, true )
				|| ! empty( $pieces_quiz_token_data )
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

				// Quiz Data tokens
				if ( isset( $pieces[2] ) && in_array( $pieces[2], $pieces_quiz_token_data, true ) ) {
					$t_data = array_shift( $trigger_data );
					if ( isset( $t_data['meta']['LDQUIZ'] ) && intval( '-1' ) !== intval( $t_data['meta']['LDQUIZ'] ) ) {
						$quiz_id = $t_data['meta']['LDQUIZ'];
					} else {
						$quiz_id = Automator()->get->mayabe_get_token_meta_value_from_trigger_log( $trigger_id, $run_number, $recipe_id, 'LDQUIZ', $user_id, $recipe_log_id );
					}
					return $this->get_quiz_token_data( $pieces[2], $user_id, $quiz_id );
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

				// Toolkit Course Timer tokens.
				if ( in_array( 'LDCOURSE_COURSE_TIME_AT_COMPLETION', $pieces, true ) || in_array( 'LDCOURSE_COURSE_CUMULATIVE_TIME', $pieces, true ) ) {
					if ( Learndash_Helpers::is_course_timer_activated() ) {
						$token_key = $pieces[2];
						$course_id = Automator()->get->mayabe_get_token_meta_value_from_trigger_log( $trigger_id, $run_number, $recipe_id, 'LDCOURSE', $user_id, $recipe_log_id );
						$t_data    = array_shift( $trigger_data );
						if ( isset( $t_data['meta']['LDCOURSE'] ) && 0 === (int) $course_id ) {
							$course_id = $t_data['meta']['LDCOURSE'];
						}

						if ( empty( $course_id ) ) {
							// Get the course ID from the POST data.
							if ( automator_filter_has_var( 'course_id', INPUT_POST ) ) {
								$course_id = absint( automator_filter_input( 'course_id', INPUT_POST ) );
							}
						}

						if ( ! empty( $course_id ) ) {
							$course_id   = learndash_get_course_id( $course_id );
							$course_post = get_post( $course_id );
							if ( 'LDCOURSE_COURSE_TIME_AT_COMPLETION' === $token_key ) {
								$completed = '00:00:00';
								if ( is_a( $course_post, 'WP_Post' ) && 'sfwd-courses' === $course_post->post_type ) {
									$completed = get_user_meta( $user_id, "course_timer_completed_{$course_id}", true );
								}
								return $completed;
							}
							if ( 'LDCOURSE_COURSE_CUMULATIVE_TIME' === $token_key ) {
								return \uncanny_pro_toolkit\CourseTimer::get_uo_time( $course_post, $user_id );
							}
						}
					}
					return $value;
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
									if ( ! empty( $pieces_quiz_token_data ) ) {
										$piece = $pieces_quiz_token_data[0];
										if ( in_array( $piece, $pieces, true ) ) {
											$value = $this->get_quiz_token_data( $piece, $user_id, $quiz_id );
										}
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
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return mixed|string
	 */
	public function ld_course_access_expiry_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args = array() ) {

		if ( empty( $pieces ) || empty( $trigger_data ) || empty( $replace_args ) ) {
			return $value;
		}
		if ( ! in_array( 'LDCOURSE_ACCESS_EXPIRY', $pieces, true ) ) {
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

		return learndash_adjust_date_time_display( ld_course_access_expires_on( $course_id, $user_id ) );
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

	/**
	 * Maybe Add Quiz Questions Tokens
	 *
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function possible_tokens_quiz_q_and_a( $tokens = array(), $args = array() ) {

		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		if ( empty( $args['value'] ) || empty( $args['meta'] ) || empty( $args['triggers_meta'] ) ) {
			return $tokens;
		}

		$quiz_id = (int) $args['value'];

		// Bail early if not a quiz trigger or Quiz ID is less than or = 0 ( -1 )
		if ( 'LDQUIZ' !== $args['meta'] || $quiz_id <= 0 ) {
			return $tokens;
		}

		$q_a_triggers = apply_filters(
			'automator_learndash_quiz_q_and_a_tokens',
			array(
				'LD_PASSQUIZ',
				'LD_FAILQUIZ',
				'LD_QUIZDONE',
				'LD_QUIZPERCENT',
				'LD_QUIZPOINT',
				'LD_QUIZSCORE',
			)
		);
		if ( ! in_array( $args['triggers_meta']['code'], $q_a_triggers, true ) ) {
			return $tokens;
		}

		static $quiz_q_and_a_tokens = array();

		// Generate Tokens for Quiz Questions and Answers.
		if ( empty( $quiz_q_and_a_tokens[ $quiz_id ] ) ) {
			$quiz_q_and_a_tokens[ $quiz_id ] = array();
			$questions_ids                   = learndash_get_quiz_questions( $quiz_id );
			if ( ! empty( $questions_ids ) ) {
				foreach ( $questions_ids as $question_post_id => $question_pro_id ) {
					$question_title = get_the_title( $question_post_id );
					$length         = strlen( $question_title );
					$max            = 42;
					$question_title = substr( $question_title, 0, $max );
					$question_title .= ( $length > $max ) ? '...' : '';
					// Question Token.
					$quiz_q_and_a_tokens[ $quiz_id ][] = array(
						'tokenId'         => 'LDQUIZ_QUESTION_ID_' . $question_post_id,
						'tokenName'       => sprintf(
							/* translators: %d, Question Post ID %s: Question Token title */
							_x( 'Question (%1$d) - %2$s', 'LearnDash Question Token', 'uncanny-automator' ),
							$question_post_id,
							$question_title
						),
						'tokenType'       => 'text',
						'tokenIdentifier' => 'LDQUIZ',
					);
					// Answer Token.
					$quiz_q_and_a_tokens[ $quiz_id ][] = array(
						'tokenId'         => 'LDQUIZ_ANSWER_ID_' . $question_post_id,
						'tokenName'       => sprintf(
							/* translators: %d, Question Post ID %s: Question Token title */
							_x( 'Answer (%1$d) - %2$s', 'LearnDash Answer Token', 'uncanny-automator' ),
							$question_post_id,
							$question_title
						),
						'tokenType'       => 'text',
						'tokenIdentifier' => 'LDQUIZ',
					);
				}
			}
		}

		// Merge Tokens.
		if ( ! empty( $quiz_q_and_a_tokens[ $quiz_id ] ) ) {
			$tokens = array_merge( $tokens, $quiz_q_and_a_tokens[ $quiz_id ] );
		}

		return $tokens;
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
	public function ld_quiz_parse_q_and_a_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args = array() ) {
		if ( empty( $pieces ) || empty( $trigger_data ) || empty( $replace_args ) ) {
			return $value;
		}
		if ( ! in_array( 'LDQUIZ', $pieces, true ) || ! absint( $user_id ) || ! absint( $recipe_id ) ) {
			return $value;
		}

		$quiz_id = (int) Automator()->db->token->get( 'LDQUIZ', $replace_args );
		if ( empty( $quiz_id ) ) {
			$trigger = array_shift( $trigger_data );
			$meta    = isset( $trigger['meta'] ) ? $trigger['meta'] : array();
			if ( isset( $meta['LDQUIZ'] ) && intval( '-1' ) !== intval( $meta['LDQUIZ'] ) ) {
				$quiz_id = absint( $meta['LDQUIZ'] );
			}
		}
		if ( empty( $quiz_id ) ) {
			return '-';
		}

		$token = ! empty( $pieces[2] ) ? $pieces[2] : false;
		if ( ! $token ) {
			return $value;
		}

		// Question Token.
		if ( 0 === strpos( $token, 'LDQUIZ_QUESTION_ID_' ) ) {
			$question_id = (int) str_replace( 'LDQUIZ_QUESTION_ID_', '', $token );
			if ( empty( $question_id ) ) {
				return '-';
			}
			$question = do_shortcode( get_post_field( 'post_content', $question_id, 'raw' ) );
			if ( empty( $question ) ) {
				return '-';
			}
			return $question;
		}

		// Answer Token.
		if ( 0 === strpos( $token, 'LDQUIZ_ANSWER_ID_' ) ) {
			$question_id = (int) str_replace( 'LDQUIZ_ANSWER_ID_', '', $token );
			if ( empty( $question_id ) ) {
				return '-';
			}
			$user_quiz_data = $this->get_user_quiz_questions_and_answers( $user_id, $quiz_id );
			if ( empty( $user_quiz_data['data'] ) ) {
				return '-';
			}
			$question = isset( $user_quiz_data['data'][ $question_id ] ) ? $user_quiz_data['data'][ $question_id ] : false;
			return $question ? $question['string_answer'] : '-';
		}

		return $value;
	}

	/**
	 * @return array
	 */
	public function get_quiz_data_tokens() {
		return array(
			'LDQUIZ',
			'LDQUIZ_ID',
			'LDQUIZ_URL',
			'LDQUIZ_POINTS',
			'LDQUIZ_SCORE',
			'LDQUIZ_TIME',
			'LDQUIZ_CORRECT',
			'LDQUIZ_CATEGORY_SCORES',
			'LDQUIZ_Q_AND_A',
			'LDQUIZ_Q_AND_A_CSV',
		);
	}

	/**
	 * @param string $token
	 * @param int $user_id
	 * @param int $quiz_id
	 *
	 * @return mixed
	 */
	public function get_quiz_token_data( $token, $user_id, $quiz_id ) {

		$value = '';

		if ( ! in_array( $token, $this->get_quiz_data_tokens(), true ) || empty( $user_id ) || empty( $quiz_id ) ) {
			return $value;
		}

		switch ( $token ) {
			case 'LDQUIZ':
				$value = get_the_title( $quiz_id );
				break;
			case 'LDQUIZ_ID':
				$value = $quiz_id;
				break;
			case 'LDQUIZ_URL':
				$value = get_permalink( $quiz_id );
				break;
			case 'LDQUIZ_POINTS':
				$value = $this->get_quiz_data( 'points', $user_id, $quiz_id, 0 );
				break;
			case 'LDQUIZ_SCORE':
				$value = $this->get_quiz_data( 'percentage', $user_id, $quiz_id, 0 ) . '%';
				break;
			case 'LDQUIZ_TIME':
				$value = Utilities::seconds_to_hours( $this->get_quiz_data( 'timespent', $user_id, $quiz_id, 0 ) );
				break;
			case 'LDQUIZ_CORRECT':
				$value = $this->get_quiz_data( 'score', $user_id, $quiz_id, 0 );
				break;
			case 'LDQUIZ_CATEGORY_SCORES':
				$result = $this->get_user_quiz_category_scores( $user_id, $quiz_id );
				$value  = ! empty( $result['result'] ) ? $result['result'] : '';
				break;
			case 'LDQUIZ_Q_AND_A':
				$result = $this->get_user_quiz_questions_and_answers( $user_id, $quiz_id );
				$value  = ! empty( $result['result'] ) ? $result['result'] : '';
				break;
			case 'LDQUIZ_Q_AND_A_CSV':
				$result = $this->get_user_quiz_questions_and_answers( $user_id, $quiz_id );
				$value  = ! empty( $result['data'] ) ? $this->format_user_quiz_questions_and_answers_csv( $result['data'] ) : '';
				break;
		}

		return $value;
	}

	/**
	 * @param string $key
	 * @param int $user_id
	 * @param int $quiz_id
	 * @param mixed $default
	 *
	 * @return mixed
	 */
	private function get_quiz_data( $key, $user_id, $quiz_id, $default ) {

		$user_quiz_meta = get_user_meta( $user_id, '_sfwd-quizzes', true );
		if ( ! empty( $user_quiz_meta ) ) {
			$user_quiz_meta = array_reverse( $user_quiz_meta );
			foreach ( $user_quiz_meta as $quiz_meta ) {
				if ( $quiz_meta['quiz'] == $quiz_id ) {
					// Full Data.
					if ( 'data' === $key ) {
						return $quiz_meta;
					} elseif ( isset( $quiz_meta[ $key ] ) ) {
						return $quiz_meta[ $key ];
					}
				}
			}
		}

		return $default;
	}

	/**
	 * @param int $user_id
	 * @param int $quiz_post_id
	 *
	 * @return array
	 */
	public function get_user_quiz_category_scores( $user_id, $quiz_post_id ) {

		$user_quiz_data = $this->get_quiz_data( 'data', $user_id, $quiz_post_id, array() );
		if ( empty( $user_quiz_data ) ) {
			return array();
		}

		$quiz_pro_id  = $user_quiz_data['pro_quizid'];
		$reference_id = $user_quiz_data['statistic_ref_id'];

		$statistics = $this->get_user_quiz_statistics( $reference_id, $quiz_post_id, $quiz_pro_id );
		if ( empty( $statistics ) ) {
			return array();
		}

		$scores = array();
		foreach ( $statistics as $statistic ) {
			$category_id = (int) $statistic->getCategoryId();

			// Add new category if not set.
			if ( ! isset( $scores[ $category_id ] ) ) {
				$scores[ $category_id ] = array(
					'id'           => $category_id,
					'name'         => $category_id ? $statistic->getCategoryName() : esc_html__( 'No category', 'learndash' ),
					'points'       => 0,
					'total_points' => 0,
					'score'        => 0,
				);
			}

			// Points Tally.
			$scores[ $category_id ]['points']       += $statistic->getPoints();
			$scores[ $category_id ]['total_points'] += $statistic->getGPoints();
		}

		// Calculate the score & generate output.
		$results = '';
		foreach ( $scores as $category_id => $score ) {
			$scores[ $category_id ]['score'] = $score['total_points'] ? ( $score['points'] / $score['total_points'] ) * 100 : 0;
			// Generate String Output.
			$results .= sprintf(
				/* Translators: %1$s = category name, %2$s = score.*/
				'<div><strong>%s:</strong> %s</div>',
				$scores[ $category_id ]['name'],
				round( $scores[ $category_id ]['score'], 2 ) . '%'
			);
		}

		$result = array(
			'result' => $results,
			'scores' => $scores,
		);

		return apply_filters( 'automator_learndash_user_quiz_category_scores_token', $result, $user_id, $quiz_post_id );
	}

	/**
	 * @param int $user_id
	 * @param int $quiz_post_id
	 *
	 * @return array
	 */
	public function get_user_quiz_questions_and_answers( $user_id, $quiz_post_id ) {

		// Take Results from Cache if available.
		static $questions_and_answers = array();
		if ( isset( $questions_and_answers[ $user_id ][ $quiz_post_id ] ) ) {
			return apply_filters(
				'automator_learndash_user_quiz_questions_and_answers_token',
				$questions_and_answers[ $user_id ][ $quiz_post_id ],
				$user_id,
				$quiz_post_id
			);
		}

		$user_quiz_data = $this->get_quiz_data( 'data', $user_id, $quiz_post_id, array() );
		if ( empty( $user_quiz_data ) ) {
			return array();
		}

		$quiz_pro_id  = $user_quiz_data['pro_quizid'];
		$reference_id = $user_quiz_data['statistic_ref_id'];

		$statistics = $this->get_user_quiz_statistics( $reference_id, $quiz_post_id, $quiz_pro_id );
		if ( empty( $statistics ) ) {
			return array();
		}

		$data = array();
		foreach ( $statistics as $statistic_index => $statistic ) {
			$answer_type      = $statistic->getAnswerType();
			$answer_data      = $statistic->getStatisticAnswerData();
			$question_id      = (int) $statistic->getQuestionId();
			$question_name    = do_shortcode( $statistic->getQuestionName() );
			$question_data    = $statistic->getQuestionAnswerData();
			$question_pro_id  = $statistic->getQuestionId();
			$question_post_id = learndash_get_question_post_by_pro_id( $question_pro_id );

			// Question Results
			$data[ $question_post_id ] = array(
				'question' => str_replace( array( '<p>', '</p>' ), '', $question_name ),
				'answer'   => array(),
				'type'     => $answer_type,
			);

			// REVIEW - Test if this is required adapted from WpProQuiz_View_StatisticsAjax for LD V 2.0.6.8 -> 2.1.x. support.
			if ( ( 'sort_answer' == $answer_type ) || ( 'matrix_sort_answer' == $answer_type ) ) {
				if ( ! empty( $question_data ) && ! empty( $answer_data ) ) {
					if ( ( -1 == $answer_data[0] ) || ( 0 !== strcmp( $answer_data[0], (int) $answer_data[0] ) ) ) {
						foreach ( $question_data as $q_k => $q_v ) {
							$q_k     = (int) $q_k;
							$datapos = md5( $user_id . $question_id . $q_k );
							$s_pos   = array_search( $datapos, $answer_data, true );
							if ( false !== $s_pos ) {
								$answer_data[ $s_pos ] = $q_k;
							}
						}
					}
				}
			}

			if ( ! empty( $question_data ) && ! empty( $answer_data ) ) {

				// Sort Matrix Questions.
				if ( 'matrix_sort_answer' === $answer_type ) {
					$matrix = array();
					foreach ( $question_data as $k => $v ) {
						$matrix[ $k ][] = $k;
						foreach ( $question_data as $k2 => $v2 ) {
							if ( $k != $k2 ) {
								if ( $v->getAnswer() == $v2->getAnswer() ) {
									$matrix[ $k ][] = $k2;
								} elseif ( $v->getSortString() == $v2->getSortString() ) {
									$matrix[ $k ][] = $k2;
								}
							}
						}
					}
				}

				$question_count = count( $question_data );
				for ( $i = 0; $i < $question_count; $i++ ) {

					$answer_text = $question_data[ $i ]->isHtml() ? $question_data[ $i ]->getAnswer() : esc_html( $question_data[ $i ]->getAnswer() );
					$answer_text = do_shortcode( $answer_text );

					// Format the answer text.
					switch ( $answer_type ) {
						// Single and Multiple Choice.
						case 'single':
						case 'multiple':
							$user_selected = isset( $answer_data[ $i ] ) && $answer_data[ $i ];
							if ( $user_selected ) {
								// Output.
								$data[ $question_post_id ]['answer'][] = $answer_text;
							}
							break;

						// Free Answer.
						case 'free_answer':
							$free_answer = ! empty( $answer_data[0] ) ? $answer_data[0] : '';
							// Output.
							$data[ $question_post_id ]['answer'][] = esc_attr( $free_answer );
							break;

						// Sort Answer.
						case 'sort_answer':
							if ( isset( $answer_data[ $i ] ) ) {
								if ( isset( $question_data[ $answer_data[ $i ] ] ) ) {
									$v           = $question_data[ $answer_data[ $i ] ];
									$answer_text = $v->isHtml() ? $v->getAnswer() : esc_html( $v->getAnswer() );
									$answer_text = do_shortcode( $answer_text );
									// Output.
									$data[ $question_post_id ]['answer'][] = $answer_text;
								}
							}
							break;

						// Matrix Sort Answer.
						case 'matrix_sort_answer':
							// REVIEW - Add default
							if ( isset( $answer_data[ $i ] ) ) {
								$v         = $question_data[ $answer_data[ $i ] ];
								$sort_text = $v->isSortStringHtml() ? $v->getSortString() : esc_html( $v->getSortString() );
								// Output.
								$data[ $question_post_id ]['answer'][] = sprintf(
									// translators: placeholder: Answer, Sort Text.
									esc_html_x( '%1$s { %2$s }', 'placeholder: Answer, Sort Text', 'learndash' ),
									do_shortcode( $answer_text ),
									do_shortcode( $sort_text )
								);
							}
							break;

						// Cloze Answer.
						case 'cloze_answer':
							$cloze_output                          = $this->get_quiz_cloze_answer( $question_data[ $i ]->getAnswer(), $answer_data );
							$data[ $question_post_id ]['answer'][] = $cloze_output;
							break;

						// Assessment Answer.
						case 'assessment_answer':
							$assessment                            = $this->get_quiz_assessment_answer( $question_data[ $i ]->getAnswer(), $answer_data );
							$data[ $question_post_id ]['answer'][] = $assessment;
							break;

						// Essay Answer.
						case 'essay':
							$graded_type                           = $question_data[ $i ]->getGradedType();
							$essay                                 = $this->get_quiz_essay_answer( $answer_data, $graded_type, $reference_id, $quiz_pro_id, $question_id, $user_id );
							$data[ $question_post_id ]['answer'][] = $essay;
							break;
					}
				}
			}
		}

		// Bail no results.
		$results = '';
		if ( empty( $data ) ) {
			return $results;
		}

		$c          = 0;
		$data_count = count( $data );
		foreach ( $data as $question_post_id => $question ) {
			$c++;
			$answer = '';
			if ( ! empty( $question['answer'] ) ) {
				if ( count( $question['answer'] ) > 1 ) {
					$answer .= implode( ', ', $question['answer'] );
				} else {
					$answer .= $question['answer'][0];
				}
			}
			$data[ $question_post_id ]['string_answer'] = $answer;

			// Generate String Output.
			$results .= sprintf(
				/* Translators: %1$s = Question, %2$s = score.*/
				'<div><strong>%s:</strong><br>%s</div><br>',
				$question['question'],
				$answer
			);
			$results .= ( $c < $data_count ) ? '<br>' : '';
		}

		// Cache results.
		$questions_and_answers[ $user_id ][ $quiz_post_id ] = array(
			'data'   => $data,
			'result' => $results,
		);

		return apply_filters(
			'automator_learndash_user_quiz_questions_and_answers_token',
			$questions_and_answers[ $user_id ][ $quiz_post_id ],
			$user_id,
			$quiz_post_id
		);
	}

	/**
	 * @param array  $data
	 *
	 * @return string
	 */
	public function format_user_quiz_questions_and_answers_csv( $data ) {
		if ( empty( $data ) ) {
			return '';
		}

		// Format Data array.
		$array = array();
		foreach ( $data as $line ) {
			// Flatten Answers array to string.
			$answer = '';
			if ( ! empty( $line['answer'] ) ) {
				if ( count( $line['answer'] ) > 1 ) {
					$answer .= implode( ', ', $line['answer'] );
				} else {
					$answer .= $line['answer'][0];
				}
			}
			// Remove all HTML and add to array.
			$array[] = array(
				'question' => wp_strip_all_tags( $line['question'] ),
				'answer'   => wp_strip_all_tags( $answer ),
			);
		}

		$delimiter   = apply_filters(
			'automator_learndash_user_quiz_questions_and_answers_unformatted_token_delimiter',
			','
		);
		$enclosure   = apply_filters(
			'automator_learndash_user_quiz_questions_and_answers_unformatted_token_enclosure',
			'"'
		);
		$escape_char = apply_filters(
			'automator_learndash_user_quiz_questions_and_answers_unformatted_token_escape_char',
			'\\'
		);

		return Utilities::array_to_csv( $array, $delimiter, $enclosure, $escape_char );
	}

	/**
	 * @param int $reference_id
	 * @param int $quiz_post_id
	 * @param int $quiz_pro_id
	 *
	 * @return array
	 */
	public function get_user_quiz_statistics( $reference_id, $quiz_post_id, $quiz_pro_id ) {

		$statistic_mapper = new \WpProQuiz_Model_StatisticUserMapper();
		$statistics       = $statistic_mapper->fetchUserStatistic( $reference_id, $quiz_pro_id, false );
		if ( empty( $statistics ) ) {
			return '';
		}

		// Retrieve the questions in the same order as the quiz.
		$quiz_questions = learndash_get_quiz_questions( $quiz_post_id );
		if ( empty( $quiz_questions ) ) {
			// Issues arose with LearnDash 4.10 where this function is no longer returning questions consistently.
			return $statistics;
		}

		// Order the questions in the same order as the quiz see WpProQuiz_View_StatisticsAjax.
		$questions = array();
		foreach ( $quiz_questions as $question_pro_id ) {
			$question_pro_id = absint( $question_pro_id );
			foreach ( $statistics as $i => $statistic_question ) {
				$statistic_question_id = absint( $statistic_question->getQuestionId() );
				if ( $question_pro_id === $statistic_question_id ) {
					unset( $statistics[ $i ] );
					$questions[] = $statistic_question;
					break;
				}
			}
		}

		// If there are any questions not matched we merge to the bottom.
		if ( ! empty( $statistics ) ) {
			$questions = array_merge( $questions, $statistics );
		}
		if ( ! empty( $questions ) ) {
			$statistics = $questions;
		}

		return $statistics;
	}

	/**
	 * @param string $answer_text The answer text.
	 * @param array  $answer_data The answer data.
	 *
	 * @return string
	 */
	private function get_quiz_cloze_answer( $answer_text, $answer_data ) {

		$question_cloze_data = learndash_question_cloze_fetch_data( $answer_text );
		$answer_data_check   = array_map( 'trim', $answer_data );
		/** This filter is documented in includes/lib/wp-pro-quiz/wp-pro-quiz.php */
		if ( apply_filters( 'learndash_quiz_question_cloze_answers_to_lowercase', true ) ) {
			$lower_function    = function_exists( 'mb_strtolower' ) ? 'mb_strtolower' : 'strtolower';
			$answer_data_check = array_map( $lower_function, $answer_data_check );
		}
		foreach ( $question_cloze_data['correct'] as $correct_key => $correct_set ) {
			$correct_value = '---';
			if ( ( is_array( $correct_set ) ) && ( ! empty( $correct_set ) ) ) {
				if ( ! empty( $answer_data_check[ $correct_key ] ) ) {
					$correct_value = $answer_data_check[ $correct_key ];
				}
			}
			$replace_key                  = "@@wpProQuizCloze-{$correct_key}@@";
			$data['correct'][]            = $correct_value;
			$data['data'][ $replace_key ] = '{' . esc_html( $correct_value ) . '}';
		}

		if ( isset( $question_cloze_data['replace'] ) ) {
			$data['replace'] = $question_cloze_data['replace'];
		}

		return learndash_question_cloze_prepare_output( $data );
	}

	/**
	 * @param string $answer_text The answer text.
	 * @param array  $answer_data The answer data.
	 *
	 * @return string
	 */
	private function get_quiz_assessment_answer( $answer_text, $answer_data ) {

		$assessment = learndash_question_assessment_fetch_data( $answer_text, 0, 0 );
		if ( ! empty( $assessment['correct'] ) && is_array( $assessment['correct'] ) ) {

			$user_answer_index = absint( $answer_data[0] ) - 1;
			$answer            = '';

			foreach ( $assessment['correct'] as $answer_index => $answer_label ) {
				if ( $user_answer_index === $answer_index ) {
					$answer .= ' { ' . esc_html( $answer_label ) . ' } ';
				} else {
					$answer .= ' [ ' . esc_html( $answer_label ) . ' ] ';
				}
			}

			$answer_index                       = 0;
			$replace_key                        = "@@wpProQuizAssessment-{$answer_index}@@";
			$assessment['data'][ $replace_key ] = $answer;
			$assessment                         = learndash_question_assessment_prepare_output( $assessment );
			/** This filter is documented in includes/lib/wp-pro-quiz/wp-pro-quiz.php */
			$assessment = apply_filters( 'learndash_quiz_question_answer_postprocess', $assessment, 'assessment' );

			return do_shortcode( $assessment );
		}

		return '';
	}

	/**
	 * @param array  $answer_data
	 * @param string $graded_type
	 * @param int    $reference_id
	 * @param int    $quiz_pro_id
	 * @param int    $question_id
	 * @param int    $user_id
	 */
	private function get_quiz_essay_answer( $answer_data, $graded_type, $reference_id, $quiz_pro_id, $question_id, $user_id ) {

		$graded_id = ! empty( $answer_data['graded_id'] ) ? $answer_data['graded_id'] : 0;

		if ( empty( $graded_id ) ) {
			// Due to a bug on LD v2.4.3 the essay file user answer data was not saved. So we need to lookup
			// the essay post ID from the user quiz meta.
			if ( ! empty( $user_id ) && ! empty( $quiz_pro_id ) && ! empty( $reference_id ) ) {
				$user_quizzes = get_user_meta( $user_id, '_sfwd-quizzes', true );
				if ( ! empty( $user_quizzes ) ) {
					foreach ( $user_quizzes as $user_quiz ) {
						$pro_quiz_id_match  = ! empty( $user_quiz['pro_quizid'] ) && ( (int) $user_quiz['pro_quizid'] === (int) $quiz_pro_id );
						$reference_id_match = ! empty( $user_quiz['statistic_ref_id'] ) && ( (int) $user_quiz['statistic_ref_id'] === (int) $reference_id );
						if ( $pro_quiz_id_match && $reference_id_match ) {
							if ( isset( $user_quiz['graded'][ $question_id ] ) ) {
								if ( ! empty( $user_quiz['graded'][ $question_id ]['post_id'] ) ) {
									$answer_data = array( 'graded_id' => $user_quiz['graded'][ $question_id ]['post_id'] );

									// Once we have the correct post_id we update the quiz statistics for next time.
									global $wpdb;
									$update_ref = $wpdb->update(
										\LDLMS_DB::get_table_name( 'quiz_statistic' ),
										array( 'answer_data' => wp_json_encode( $answer_data ) ),
										array(
											'statistic_ref_id' => $reference_id,
											'question_id' => $question_id,
										),
										array( '%s' ),
										array( '%d', '%d' )
									);

									break;
								}
							}
						}
					}
				}
			}
		}

		$answer = '';
		// Check Graded ID again in case of the bug fix above.
		$graded_id = ! empty( $answer_data['graded_id'] ) ? $answer_data['graded_id'] : 0;
		if ( ! empty( $graded_id ) ) {
			$essay_post = get_post( $graded_id );
			if ( $essay_post instanceof \WP_Post ) {
				if ( 'graded' === $essay_post->post_status ) {
					$answer = __( 'Status: Graded', 'learndash' );
				} else {
					$answer = __( 'Status: Not Graded', 'learndash' );
				}
				if ( 'text' === $graded_type ) {
					$answer .= '<br>' . do_shortcode( nl2br( $essay_post->post_content ) );
				} elseif ( 'upload' === $graded_type ) {
					$uploaded_file = get_post_meta( $graded_id, 'upload', true );
					if ( ! empty( $uploaded_file ) ) {
						$answer .= '<br>' . $uploaded_file;
					}
				}
			}
			// Not Submitted.
		} else {
			$answer = __( 'Essay not submitted', '', 'learndash' );
		}

		return $answer;
	}

}
