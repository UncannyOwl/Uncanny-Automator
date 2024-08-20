<?php
namespace Uncanny_Automator\Integrations\LearnDash\Tokens\Loopable\Universal;

use Uncanny_Automator\Services\Loopable\Loopable_Token_Collection;
use Uncanny_Automator\Services\Loopable\Universal_Loopable_Token;

/**
 * Class User_Enrolled_Courses.
 *
 * @package Uncanny_Automator\Integrations\LearnDash\Tokens\Loopable\Universal\User_Enrolled_Courses
 */
class User_Enrolled_Courses extends Universal_Loopable_Token {

	/**
	 * Registers the loopable token.
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

		$this->set_id( 'ENROLLED_COURSES' );
		$this->set_name( _x( "User's enrolled courses", 'LearnDash', 'uncanny-automator' ) );
		$this->set_log_identifier( '#{{COURSE_ID}} {{COURSE_NAME}}' );
		$this->set_child_tokens( $child_tokens );

	}

	/**
	 * Hydrates the token.
	 *
	 * @param array $args
	 *
	 * @return Loopable_Token_Collection
	 */
	public function hydrate_token_loopable( $args ) {

		$loopable = new Loopable_Token_Collection();

		// Bail if user id is empty.
		if ( ! function_exists( 'learndash_user_get_enrolled_courses' ) || ! isset( $args['user_id'] ) ) {
			return $loopable;
		}

		$user_enrolled_courses = (array) learndash_user_get_enrolled_courses( $args['user_id'] );

		// Now you can use the $posts_data array as needed
		foreach ( $user_enrolled_courses as $course_id ) {
			$loopable->create_item(
				array(
					'COURSE_ID'          => $course_id,
					'COURSE_NAME'        => get_the_title( $course_id ),
					'COURSE_DESCRIPTION' => get_the_excerpt( $course_id ),
					'COURSE_PERMALINK'   => get_permalink( $course_id ),
				)
			);
		}

		return $loopable;

	}

}
