<?php

namespace Uncanny_Automator\Integrations\Masteriyo;

/**
 * Class Masteriyo_Enrollment_Dispatcher
 *
 * A paid checkout saves the enrollment `inactive`, and completing the order only
 * flips it to `active`, so neither Masteriyo hook alone is "a user enrolls". This
 * fires `automator_masteriyo_user_enrolled` when an enrollment becomes active: a
 * new active row, or an inactive row activated.
 *
 * PayPal Standard completes the order in Masteriyo's IPN handler at `init` 0 and
 * exits, so T1 also declares this class in `definition()` and the engine boots it
 * at `plugins_loaded` for a live recipe. It fires plain ids, which the
 * shutdown loopback can carry.
 *
 * @package Uncanny_Automator
 */
class Masteriyo_Enrollment_Dispatcher {

	/**
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Idempotent: the engine and load_shared_hooks() may both boot it.
	 *
	 * @return void
	 */
	public static function boot() {

		if ( self::$booted ) {
			return;
		}

		self::$booted = true;

		add_action( 'masteriyo_new_user_course', array( __CLASS__, 'on_enrollment' ), 10, 2 );
		add_action( 'masteriyo_user_course_status_inactive_to_active', array( __CLASS__, 'on_enrollment' ), 10, 2 );
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
	 * A new row saved without a status is written `active` by the repository.
	 *
	 * @param int                           $id          The enrollment ID.
	 * @param \Masteriyo\Models\UserCourse $user_course The enrollment.
	 *
	 * @return void
	 */
	public static function on_enrollment( $id, $user_course ) {

		if ( ! is_object( $user_course ) ) {
			return;
		}

		$status = (string) $user_course->get_status();

		if ( '' !== $status && 'active' !== $status ) {
			return;
		}

		do_action( 'automator_masteriyo_user_enrolled', (int) $id, (int) $user_course->get_user_id(), (int) $user_course->get_course_id() );
	}
}
