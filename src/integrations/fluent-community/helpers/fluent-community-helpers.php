<?php
namespace Uncanny_Automator\Integrations\Fluent_Community;

use FluentCommunity\Modules\Course\Model\CourseLesson;


/**
 * Class Fluent_Community_Helpers
 *
 * @package Uncanny_Automator
 */
class Fluent_Community_Helpers {


	/**
	 * Get all FluentCommunity courses as modern dropdown options.
	 *
	 * @param bool $include_any Whether to include "Any course".
	 * @return array
	 */
	public function all_courses( $include_any = true ) {
		$options = array();

		if ( ! class_exists( '\FluentCommunity\Modules\Course\Model\Course' ) ) {
			return $options;
		}

		$courses = \FluentCommunity\Modules\Course\Model\Course::orderBy( 'title', 'ASC' )
			->select( array( 'id', 'title' ) )
			->get();

		if ( $include_any ) {
			$options[] = array(
				'value' => -1,
				'text'  => esc_html_x( 'Any course', 'Fluent Community', 'uncanny-automator' ),
			);
		}

		foreach ( $courses as $course ) {
			$options[] = array(
				'value' => $course->id,
				'text'  => $course->title,
			);
		}

		return $options;
	}

	/**
	 * Get all FluentCommunity spaces as modern dropdown options.
	 *
	 * @param bool   $include_any
	 *
	 * @return array
	 */
	public function all_spaces( $include_any = true ) {
		$options = array();

		if ( ! class_exists( '\FluentCommunity\App\Models\Space' ) ) {
			return $options;
		}

		$spaces = \FluentCommunity\App\Models\Space::where( 'status', 'published' )
			->orderBy( 'title', 'ASC' )
			->select( array( 'id', 'title' ) )
			->get();

		if ( $include_any ) {
			$options[] = array(
				'value' => -1,
				'text'  => esc_html_x( 'Any space', 'Fluent Community', 'uncanny-automator' ),
			);
		}

		foreach ( $spaces as $space ) {
			$options[] = array(
				'value' => $space->id,
				'text'  => $space->title,
			);
		}

		return $options;
	}

	/**
	 * Get all FluentCommunity users as modern dropdown options.
	 *
	 * @param bool $include_any Whether to include "Any user".
	 * @return array
	 */
	public function all_users( $include_any = true ) {
		$options = array();

		if ( ! class_exists( '\FluentCommunity\App\Models\User' ) ) {
			return $options;
		}

		$users = \FluentCommunity\App\Models\User::orderBy( 'display_name', 'ASC' )
			->select( array( 'id', 'display_name' ) )
			->get();

		if ( $include_any ) {
			$options[] = array(
				'value' => -1,
				'text'  => esc_html_x( 'Any user', 'Fluent Community', 'uncanny-automator' ),
			);
		}

		foreach ( $users as $user ) {
			$options[] = array(
				'value' => $user->id,
				'text'  => $user->display_name,
			);
		}

		return $options;
	}

	/**
	 * Get all lessons for a specific course as modern dropdown options.
	 *
	 * @param int  $course_id The course ID to get lessons for.
	 * @param bool $include_any Whether to include "Any lesson".
	 * @return array
	 */
	public function all_lessons( $course_id = -1, $include_any = true ) {
		$options = array();

		// Dynamically reference the model without 'use'
		if ( ! class_exists( '\FluentCommunity\Modules\Course\Model\CourseLesson' ) ) {
			return $options;
		}

		// If -1, return "Any lesson" option only (if allowed)
		if ( -1 === $course_id ) {
			if ( $include_any ) {
				$options[] = array(
					'value' => -1,
					'text'  => esc_html_x( 'Any lesson', 'Fluent Community', 'uncanny-automator' ),
				);
			}
			return $options;
		}

		// Fetch lessons belonging to the course (space_id)
		$lessons = \FluentCommunity\Modules\Course\Model\CourseLesson::where( 'space_id', $course_id )
			->where( 'type', 'course_lesson' )
			->where( 'status', 'published' )
			->orderBy( 'priority', 'ASC' )
			->get();

		// Optionally add "Any lesson"
		if ( $include_any ) {
			$options[] = array(
				'value' => -1,
				'text'  => esc_html_x( 'Any lesson', 'Fluent Community', 'uncanny-automator' ),
			);
		}

		// Convert to modern dropdown options
		foreach ( $lessons as $lesson ) {
			$options[] = array(
				'value' => $lesson->id,
				'text'  => $lesson->title,
			);
		}

		return $options;
	}
	
	/**
	 * Ajax fetch lessons by course.
	 */
	public function ajax_fetch_lessons_by_course() {
		Automator()->utilities->verify_nonce();
		//phpcs:ignore WordPress.Security.NonceVerification.Missing
		$course_id = absint( $_POST['values']['COURSE'] ?? -1 );
		$lessons   = $this->all_lessons( $course_id, true );

		wp_send_json(
			array(
				'success' => true,
				'options' => $lessons,
			)
		);
	}

	/**
	 * Ajax fetch lessons by course.
	 */
	public function ajax_fetch_lessons_by_course_for_action() {
		Automator()->utilities->verify_nonce();
		//phpcs:ignore WordPress.Security.NonceVerification.Missing
		$course_id = absint( $_POST['values']['COURSE'] ?? -1 );
		$lessons   = $this->all_lessons( $course_id, false );

		wp_send_json(
			array(
				'success' => true,
				'options' => $lessons,
			)
		);
	}
}
