<?php

namespace Uncanny_Automator\Integrations\Sensei_Lms;

/**
 * Class Course_Completed_Dispatcher
 *
 * Sensei fires `sensei_course_status_updated` on every course progress save,
 * including a re-save of `complete` when a finished course gets another lesson,
 * grade or recalculation. This fires `automator_sensei_lms_course_completed` only
 * when a course moves to `complete` from any other status (q-sensei-1).
 *
 * `$previous_status` exists from Sensei LMS 4.20.1. On table-reading progress
 * storage it is already `complete` on a first completion, so nothing fires there,
 * as with Sensei's own course-completed email (q-sensei-7).
 *
 * @package Uncanny_Automator\Integrations\Sensei_Lms
 */
class Course_Completed_Dispatcher {

	/**
	 * @var bool
	 */
	private static $booted = false;

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

		add_action( 'sensei_course_status_updated', array( __CLASS__, 'on_course_status_updated' ), 10, 5 );
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
	 * @param string      $status          The saved status.
	 * @param int         $user_id         The learner.
	 * @param int         $course_id       The course.
	 * @param int         $comment_id      The progress comment ID.
	 * @param string|null $previous_status The status before this save; null when there was none.
	 *
	 * @return void
	 */
	public static function on_course_status_updated( $status, $user_id, $course_id, $comment_id = 0, $previous_status = null ) {

		if ( 'complete' !== $status || 'complete' === $previous_status ) {
			return;
		}

		do_action( 'automator_sensei_lms_course_completed', $user_id, $course_id );
	}
}
