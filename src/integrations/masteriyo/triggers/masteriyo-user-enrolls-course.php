<?php

namespace Uncanny_Automator\Integrations\Masteriyo;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Masteriyo_User_Enrolls_Course
 *
 * Listens to Masteriyo_Enrollment_Dispatcher, which fires when an enrollment
 * becomes active, with the enrollment, user and course ids.
 *
 * @package Uncanny_Automator
 * @since 8.1.0
 *
 * @property Masteriyo_Helpers $item_helpers
 */
class Masteriyo_User_Enrolls_Course extends Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'MASTERIYO_USER_ENROLLS_COURSE', 'MASTERIYO' )
			->trigger_meta( 'MASTERIYO_COURSE' )
			->hook( 'automator_masteriyo_user_enrolled', 10, 3 )
			->dispatcher( Masteriyo_Enrollment_Dispatcher::class );
	}

	/**
	 * Setup trigger configuration.
	 *
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_is_pro( false );
		// Enrollment is also created from the admin, the REST API and order
		// completion, so the acting user is taken from the payload instead of
		// the session.
		$this->set_is_login_required( false );

		$this->set_sentence(
			sprintf(
				/* translators: 1: Course selector */
				esc_html_x( 'A user enrolls in {{a course:%1$s}}', 'Masteriyo LMS', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'A user enrolls in {{a course}}', 'Masteriyo LMS', 'uncanny-automator' ) );
	}

	/**
	 * Define trigger options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array( $this->item_helpers->get_course_field( $this->get_trigger_meta() ) );
	}

	/**
	 * Define available tokens.
	 *
	 * @param array $trigger The trigger settings.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {

		$token_class = new Masteriyo_Tokens();

		return array_merge(
			$token_class->course_tokens(),
			array(
				array(
					'tokenId'   => 'ENROLLMENT_ID',
					'tokenName' => esc_html_x( 'Enrollment ID', 'Masteriyo LMS', 'uncanny-automator' ),
					'tokenType' => 'int',
				),
				array(
					'tokenId'   => 'ENROLLMENT_STATUS',
					'tokenName' => esc_html_x( 'Enrollment status', 'Masteriyo LMS', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'ENROLLMENT_DATE',
					'tokenName' => esc_html_x( 'Enrollment date', 'Masteriyo LMS', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'ORDER_ID',
					'tokenName' => esc_html_x( 'Order ID', 'Masteriyo LMS', 'uncanny-automator' ),
					'tokenType' => 'int',
				),
			)
		);
	}

	/**
	 * Validate trigger against hook arguments.
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args Enrollment ID, user ID, course ID.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		list( $enrollment_id, $user_id, $course_id ) = array_pad( (array) $hook_args, 3, 0 );

		if ( ! is_numeric( $user_id ) || 0 >= (int) $user_id || ! is_numeric( $enrollment_id ) ) {
			return false;
		}

		$selected = $this->item_helpers->get_selected_value( $trigger, $this->get_trigger_meta() );

		if ( ! $this->item_helpers->matches_selection( $selected, $course_id ) ) {
			return false;
		}

		$this->set_user_id( (int) $user_id );

		return true;
	}

	/**
	 * Hydrate token values from hook arguments.
	 *
	 * @param array $trigger   The completed trigger settings.
	 * @param array $hook_args Enrollment ID, user ID, course ID.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $enrollment_id, , $course_id ) = array_pad( (array) $hook_args, 3, 0 );

		$course_id   = absint( $course_id );
		$user_course = function_exists( 'masteriyo_get_user_course' ) ? masteriyo_get_user_course( absint( $enrollment_id ) ) : null;
		$date_start  = is_object( $user_course ) ? $user_course->get_date_start() : '';

		$token_class = new Masteriyo_Tokens();

		return array_merge(
			$token_class->hydrate_course_tokens( $course_id ),
			array(
				$this->get_trigger_meta() => 0 === $course_id ? '' : get_the_title( $course_id ),
				'ENROLLMENT_ID'           => absint( $enrollment_id ),
				'ENROLLMENT_STATUS'       => is_object( $user_course ) ? (string) $user_course->get_status() : '',
				'ENROLLMENT_DATE'         => $date_start instanceof \DateTimeInterface ? $date_start->format( 'Y-m-d H:i:s' ) : (string) $date_start,
				'ORDER_ID'                => is_object( $user_course ) ? absint( $user_course->get_order_id() ) : 0,
			)
		);
	}
}
