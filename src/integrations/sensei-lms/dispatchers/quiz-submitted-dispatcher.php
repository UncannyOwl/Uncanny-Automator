<?php

namespace Uncanny_Automator\Integrations\Sensei_Lms;

use Sensei_Utils;

/**
 * Class Quiz_Submitted_Dispatcher
 *
 * Fires `automator_sensei_lms_quiz_submitted` once per quiz submission
 * (q-sensei-6). `sensei_user_quiz_submitted` alone misses the submission that
 * completes a course: that one ends in Sensei's course-completed redirect and
 * `exit` before the submit hook fires. Every such submission is auto-graded, and
 * Sensei fires `sensei_user_quiz_grade` for it earlier in the same request, so
 * this also listens there, at priority 9, while the learner is submitting.
 *
 * A request-scoped `{user}:{quiz}` key drops the second announcement of the same
 * submission before any recipe sees it.
 *
 * @package Uncanny_Automator\Integrations\Sensei_Lms
 */
class Quiz_Submitted_Dispatcher {

	/**
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Submissions already announced in this request, keyed "{user_id}:{quiz_id}".
	 *
	 * @var array<string,bool>
	 */
	private static $fired = array();

	/**
	 * Idempotent: load_shared_hooks() may run more than once per request.
	 *
	 * @return void
	 */
	public static function boot() {

		if ( self::$booted ) {
			return;
		}

		self::$booted = true;

		add_action( 'sensei_user_quiz_grade', array( __CLASS__, 'on_quiz_graded' ), 9, 5 );
		add_action( 'sensei_user_quiz_submitted', array( __CLASS__, 'on_quiz_submitted' ), 10, 5 );
	}

	/**
	 * Test seam.
	 *
	 * @return void
	 */
	public static function reset_boot() {
		self::$booted = false;
	}

	/**
	 * Test seam for the once-per-submission state.
	 *
	 * @return void
	 */
	public static function reset_fired() {
		self::$fired = array();
	}

	/**
	 * The grade path: only the learner's own front-end submission. A teacher's
	 * grade runs in wp-admin and never fires this trigger.
	 *
	 * The hook's pass mark is `absint()`-truncated and its grade type is always
	 * 'auto', so both are read from the quiz the way the submit path reads them.
	 *
	 * @param int    $user_id       The learner.
	 * @param int    $quiz_id       The quiz.
	 * @param float  $grade         The grade.
	 * @param int    $quiz_passmark The truncated pass mark (unused).
	 * @param string $grade_type    Always 'auto' (unused).
	 *
	 * @return void
	 */
	public static function on_quiz_graded( $user_id, $quiz_id, $grade, $quiz_passmark = 0, $grade_type = 'auto' ) {

		if ( is_admin() || get_current_user_id() !== (int) $user_id ) {
			return;
		}

		self::fire(
			$user_id,
			$quiz_id,
			$grade,
			Sensei_Utils::as_absolute_rounded_number( get_post_meta( $quiz_id, '_quiz_passmark', true ), 2 ),
			(string) get_post_meta( $quiz_id, '_quiz_grade_type', true )
		);
	}

	/**
	 * The submit path: manual quizzes and quizzes Sensei can't auto-grade, which
	 * get no grade hook during the submission.
	 *
	 * @param int                $user_id         The learner.
	 * @param int                $quiz_id         The quiz.
	 * @param float|false|object $grade           The grade, `false` or a `WP_Error`.
	 * @param float              $pass_percentage The pass mark.
	 * @param string             $grade_type      The quiz's grade type.
	 *
	 * @return void
	 */
	public static function on_quiz_submitted( $user_id, $quiz_id, $grade, $pass_percentage, $grade_type ) {
		self::fire( $user_id, $quiz_id, $grade, $pass_percentage, $grade_type );
	}

	/**
	 * @param int                $user_id         The learner.
	 * @param int                $quiz_id         The quiz.
	 * @param float|false|object $grade           The grade.
	 * @param float              $pass_percentage The pass mark.
	 * @param string             $grade_type      The quiz's grade type.
	 *
	 * @return void
	 */
	private static function fire( $user_id, $quiz_id, $grade, $pass_percentage, $grade_type ) {

		$key = absint( $user_id ) . ':' . absint( $quiz_id );

		if ( isset( self::$fired[ $key ] ) ) {
			return;
		}

		self::$fired[ $key ] = true;

		do_action( 'automator_sensei_lms_quiz_submitted', $user_id, $quiz_id, $grade, $pass_percentage, $grade_type );
	}
}
