<?php

namespace Uncanny_Automator_Pro;

/**
 * Class Wplms_Tokens
 *
 * @package Uncanny_Automator_Pro
 */
class Wplms_Tokens {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WPLMS';

	/**
	 *
	 */
	public function __construct() {
		add_action( 'automator_wplms_save_tokens', array( $this, 'automator_wplms_save_tokens_func' ), 10, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'wplms_token' ), 20, 6 );
	}

	/**
	 * @param $args
	 * @param $result
	 *
	 * @return void
	 */
	public function automator_wplms_save_tokens_func( $args, $result ) {
		if ( ! isset( $args['action'] ) ) {
			return;
		}
		$action         = $args['action'];
		$user_id        = $args['user_id'];
		$trigger_id     = (int) $result['trigger_id'];
		$trigger_log_id = (int) $result['get_trigger_id'];
		$run_number     = (int) $result['run_number'];

		$args = array(
			'user_id'        => $user_id,
			'trigger_id'     => $trigger_id,
			'run_number'     => $run_number, //get run number
			'trigger_log_id' => $trigger_log_id,
			'meta_value'     => maybe_serialize( $args ),
		);
		switch ( $action ) {
			case 'unit_completed':
				$args['meta_key'] = 'WPLMSUNITCOMPLETED_tokens';
				break;
			case 'quiz_completed':
				$args['meta_key'] = 'WPLMSQUIZCOMPLETED_tokens';
				break;
			case 'course_started':
				$args['meta_key'] = 'WPLMSCOURSESTARTED_tokens';
				break;
			case 'course_completed':
				$args['meta_key'] = 'WPLMSCOURSECOMPLETED_tokens';
				break;
		}
		Automator()->insert_trigger_meta( $args );
	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return string|null
	 */
	public function wplms_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		$wplms_pieces = array(
			'WPLMSUNITCOMPLETED',
			'WPLMSQUIZCOMPLETED',
			'WPLMSCOURSESTARTED',
			'WPLMSCOURSECOMPLETED',
		);
		if ( empty( array_intersect( $pieces, $wplms_pieces ) ) ) {
			return $value;
		}
		if ( empty( $trigger_data ) ) {
			return $value;
		}
		$trigger_meta = $pieces[1];
		$token        = $pieces[2];
		foreach ( $trigger_data as $trigger ) {
			$trigger_id     = absint( $trigger['ID'] );
			$trigger_log_id = absint( $replace_args['trigger_log_id'] );
			$parse_tokens   = array(
				'trigger_id'     => $trigger_id,
				'trigger_log_id' => $trigger_log_id,
				'user_id'        => $user_id,
			);

			$meta_key = $trigger_meta . '_tokens';
			$entry    = Automator()->db->trigger->get_token_meta( $meta_key, $parse_tokens );
			if ( empty( $entry ) ) {
				continue;
			}
			$entry     = maybe_unserialize( $entry );
			$course_id = isset( $entry['course_id'] ) ? absint( $entry['course_id'] ) : 0;
			$unit_id   = isset( $entry['unit_id'] ) ? absint( $entry['unit_id'] ) : 0;
			$quiz_id   = isset( $entry['quiz_id'] ) ? absint( $entry['quiz_id'] ) : 0;
			switch ( $token ) {
				case 'WPLMS_UNIT':
					$value = get_the_title( $unit_id );
					break;
				case 'WPLMS_UNIT_ID':
					$value = $unit_id;
					break;
				case 'WPLMS_UNIT_URL':
					$value = get_permalink( $unit_id );
					break;
				case 'WPLMS_UNIT_THUMB_ID':
					$value = get_post_thumbnail_id( $entry['unit_id'] );
					$value = empty( $value ) || 0 == $value ? 'N/A' : $value; //phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
					break;
				case 'WPLMS_UNIT_THUMB_URL':
					$value = get_the_post_thumbnail_url( $unit_id );
					$value = empty( $value ) || 0 == $value ? 'N/A' : $value; //phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
					break;
				case 'WPLMS_COURSESTART':
				case 'WPLMS_COURSE':
					$value = get_the_title( $course_id );
					break;
				case 'WPLMS_COURSESTART_ID':
				case 'WPLMS_COURSE_ID':
					$value = $course_id;
					break;
				case 'WPLMS_COURSE_URL':
				case 'WPLMS_COURSESTART_URL':
					$value = get_permalink( $course_id );
					break;
				case 'WPLMS_COURSE_THUMB_ID':
				case 'WPLMS_COURSESTART_THUMB_ID':
					$value = get_post_thumbnail_id( $course_id );
					$value = empty( $value ) || 0 == $value ? 'N/A' : $value; //phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
					break;
				case 'WPLMS_COURSE_THUMB_URL':
				case 'WPLMS_COURSESTART_THUMB_URL':
					$value = get_the_post_thumbnail_url( $course_id );
					$value = empty( $value ) || 0 == $value ? 'N/A' : $value; //phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
					break;
				case 'WPLMS_QUIZ':
					$value = get_the_title( $quiz_id );
					break;
				case 'WPLMS_QUIZ_ID':
					$value = $quiz_id;
					break;
				case 'WPLMS_QUIZ_URL':
					$value = get_permalink( $quiz_id );
					break;
				case 'WPLMS_QUIZ_THUMB_ID':
					$value = get_post_thumbnail_id( $quiz_id );
					$value = empty( $value ) || 0 == $value ? 'N/A' : $value; //phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
					break;
				case 'WPLMS_QUIZ_THUMB_URL':
					$value = get_the_post_thumbnail_url( $quiz_id );
					$value = empty( $value ) || 0 == $value ? 'N/A' : $value; //phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
					break;
			}
		}

		return $value;
	}
}
