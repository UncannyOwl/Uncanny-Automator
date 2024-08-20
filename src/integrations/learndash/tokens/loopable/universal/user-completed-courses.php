<?php
namespace Uncanny_Automator\Integrations\LearnDash\Tokens\Loopable\Universal;

use Uncanny_Automator\Services\Loopable\Loopable_Token_Collection;
use Uncanny_Automator\Services\Loopable\Universal_Loopable_Token;

/**
 * Class User_Completed_Courses.
 *
 * @package Uncanny_Automator\Integrations\LearnDash\Tokens\Loopable\Universal\User_Completed_Courses
 */
class User_Completed_Courses extends Universal_Loopable_Token {

	/**
	 * Register all loopable tokens.
	 *
	 * @return void
	 */
	public function register_loopable_token() {

		$child_tokens = array(
			'COURSE_ID'          => array(
				'name'       => _x( 'Course ID', 'LearnDash', 'uncanny-automator' ),
				'token_type' => 'integer',
			),
			'COURSE_NAME'        => array(
				'name' => _x( 'Course name', 'LearnDash', 'uncanny-automator' ),
			),
			'COURSE_DESCRIPTION' => array(
				'name' => _x( 'Course description', 'LearnDash', 'uncanny-automator' ),
			),
			'COURSE_PERMALINK'   => array(
				'name' => _x( 'Course URL', 'LearnDash', 'uncanny-automator' ),
			),
		);

		$this->set_id( 'COMPLETED_COURSE' );
		$this->set_name( _x( "User's completed courses", 'LearnDash', 'uncanny-automator' ) );
		$this->set_log_identifier( '#{{COURSE_ID}} {{COURSE_NAME}}' );
		$this->set_child_tokens( $child_tokens );

	}

	/**
	 * Hydrates all loopable tokens.
	 *
	 * @param array $args
	 *
	 * @return Loopable_Token_Collection
	 */
	public function hydrate_token_loopable( $args ) {

		$loopable = new Loopable_Token_Collection();

		// Bail.
		if ( ! isset( $args['user_id'] ) ) {
			return $loopable;
		}

		$completed_courses = $this->get_user_completed_courses( $args['user_id'] );

		// Bail.
		if ( false === $completed_courses ) {
			return $loopable;
		}

		// Now you can use the $posts_data array as needed
		foreach ( $completed_courses as $course ) {

			$course_id = $course->ID ?? 0;

			$loopable->create_item(
				array(
					'COURSE_ID'          => $course_id,
					'COURSE_NAME'        => get_the_title( $course_id ) ?? '',
					'COURSE_DESCRIPTION' => get_the_excerpt( $course_id ) ?? '',
					'COURSE_PERMALINK'   => get_the_permalink( $course_id ) ?? '',
				)
			);
		}

		return $loopable;

	}

	/**
	 * Retrieves the user's completed courses.
	 *
	 * @param int $user_id
	 *
	 * @return false|array
	 */
	function get_user_completed_courses( $user_id ) {

		if ( ! function_exists( 'learndash_course_status' ) || ! function_exists( 'learndash_user_get_enrolled_courses' ) ) {
			return false;
		}

		// Get all courses.
		$courses = learndash_user_get_enrolled_courses( $user_id );

		// Array to store completed courses.
		$completed_courses = array();

		// Loop through each course.
		foreach ( $courses as $course_id ) {

			if ( learndash_course_completed( $user_id, $course_id ) ) {
				$completed_courses[] = get_post( $course_id );
			}
		}

		return $completed_courses;
	}

}
