<?php

namespace Uncanny_Automator;

class MASTERSTUDY_ENROLL_USER_IN_COURSE extends \Uncanny_Automator\Recipe\Action {

	/**
	 * @return mixed
	 */
	protected function setup_action() {
		$this->set_integration( 'MSLMS' );
		$this->set_action_code( 'MSLMS_ENROLL_USER' );
		$this->set_action_meta( 'MSLMS_COURSES' );
		$this->set_requires_user( true );
		$this->set_sentence( sprintf( esc_attr_x( 'Enroll the user in {{a course:%1$s}}', 'MasterStudy LMS', 'uncanny-automator' ), $this->get_action_meta(), 'EXPIRATION_DATE:' . $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'Enroll the user in {{a course}}', 'MasterStudy LMS', 'uncanny-automator' ) );
	}

	/**
	 * Define the Action's options
	 *
	 * @return void
	 */
	public function options() {
		$args    = array(
			'post_type'      => 'stm-courses',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);
		$options = array();
		$courses = get_posts( $args );
		foreach ( $courses as $course ) {
			$options[] = array(
				'text'  => $course->post_title,
				'value' => $course->ID,
			);
		}

		return array(
			Automator()->helpers->recipe->field->select(
				array(
					'option_code'     => $this->get_action_meta(),
					'label'           => _x( 'Course', 'MasterStudy LMS', 'uncanny-automator' ),
					'relevant_tokens' => array(),
					'required'        => true,
					'options'         => $options,
				)
			),
		);

	}

	/**
	 * @return array[]
	 */
	public function define_tokens() {
		return array(
			'COURSE_ID'       => array(
				'name' => __( 'Course ID', 'uncanny-automator' ),
				'type' => 'int',
			),
			'COURSE_TITLE'    => array(
				'name' => __( 'Course title', 'uncanny-automator' ),
				'type' => 'text',
			),
			'COURSE_CATEGORY' => array(
				'name' => __( 'Course category', 'uncanny-automator' ),
				'type' => 'text',
			),
			'COURSE_AUTHOR'   => array(
				'name' => __( 'Course author', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param       $parsed
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$course_id = sanitize_text_field( $parsed[ $this->get_action_meta() ] );

		if ( empty( $course_id ) ) {
			$this->add_log_error( esc_attr_x( 'Please enter a valid course ID.', 'MasterStudy LMS', 'uncanny-automator' ) );

			return false;
		}

		if ( ! class_exists( '\STM_LMS_Course' ) ) {
			$this->add_log_error( esc_attr_x( '"STM_LMS_Course" class not found.', 'MasterStudy LMS', 'uncanny-automator' ) );

			return false;
		}

		if ( ! class_exists( '\STM_LMS_Lesson' ) ) {
			$this->add_log_error( esc_attr_x( '"\STM_LMS_Lesson" class not found.', 'MasterStudy LMS', 'uncanny-automator' ) );

			return false;
		}

		$course_categories = stm_lms_get_terms_array( $course_id, 'stm_lms_course_taxonomy', 'name' );

		$this->hydrate_tokens(
			array(
				'COURSE_ID'       => $course_id,
				'COURSE_TITLE'    => get_the_title( $course_id ),
				'COURSE_CATEGORY' => join( ', ', $course_categories ),
				'COURSE_AUTHOR'   => get_the_author_meta( 'display_name', get_post_field( 'post_author', $course_id ) ),
			)
		);

		$already_enrolled = stm_lms_get_user_course( $user_id, $course_id );
		if ( ! empty( $already_enrolled ) ) {
			$this->add_log_error( sprintf( esc_attr_x( 'The user is already enrolled into a course (%d).', 'MasterStudy LMS', 'uncanny-automator' ), $course_id ) );

			return false;
		}

		\STM_LMS_Course::add_user_course( $course_id, $user_id, \STM_LMS_Lesson::get_lesson_url( $course_id, '' ) );

		return true;
	}
}
