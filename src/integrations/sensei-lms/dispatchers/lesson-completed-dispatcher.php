<?php

namespace Uncanny_Automator\Integrations\Sensei_Lms;

/**
 * Class Lesson_Completed_Dispatcher
 *
 * Relays `sensei_user_lesson_end` as `automator_sensei_lms_lesson_completed` at
 * priority 9. Sensei's own `update_status_after_lesson_change()` runs at 10 and
 * can complete the course, and its course-completed redirect then calls `exit`
 * before a later priority-10 listener runs, so the lesson that finishes a course
 * would otherwise never reach a recipe.
 *
 * @package Uncanny_Automator\Integrations\Sensei_Lms
 */
class Lesson_Completed_Dispatcher {

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

		add_action( 'sensei_user_lesson_end', array( __CLASS__, 'on_lesson_end' ), 9, 2 );
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
	 * @param int $user_id   The learner.
	 * @param int $lesson_id The lesson.
	 *
	 * @return void
	 */
	public static function on_lesson_end( $user_id, $lesson_id ) {
		do_action( 'automator_sensei_lms_lesson_completed', $user_id, $lesson_id );
	}
}
