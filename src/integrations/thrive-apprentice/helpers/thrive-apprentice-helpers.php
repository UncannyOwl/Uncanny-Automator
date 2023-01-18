<?php
namespace Uncanny_Automator;

/**
 * Class Thrive_Apprentice_Helpers
 *
 * @package Uncanny_Automator
 */
class Thrive_Apprentice_Helpers {

	public function __construct( $hooks_loaded = true ) {

		if ( $hooks_loaded ) {

			add_action( 'wp_ajax_automator_thrive_apprentice_lessons_handler', array( $this, 'get_dropdown_options_ajax_handler_lessons' ) );

			add_action( 'wp_ajax_automator_thrive_apprentice_modules_handler', array( $this, 'get_dropdown_options_ajax_handler_modules' ) );

		}

	}

	/**
	 * Retrieves all dropdown courses option values.
	 *
	 * @param bool $has_option_any Whether dropdown options has `any` or not.
	 *
	 * @return array The list of courses.
	 */
	public function get_dropdown_options_courses( $has_option_any = false ) {

		$tva_courses = (array) $this->get_courses( array( 'published' => true ) );

		$courses = array();

		if ( ! empty( $tva_courses ) || ! is_wp_error( $tva_courses ) ) {

			if ( $has_option_any ) {
				$courses[-1] = esc_html__( 'Any course', 'uncanny-automator' );
			}

			foreach ( $tva_courses as $course ) {

				if ( isset( $course->term_id ) && isset( $course->name ) ) {

					$courses[ $course->term_id ] = $course->name;

				}
			}
		}

		return $courses;

	}


	/**
	 * Retrieves Courses from TA.
	 *
	 * @return array The list of courses.
	 */
	private function get_courses( $filters = array() ) {

		if ( function_exists( 'tva_get_courses' ) ) {

			return tva_get_courses( $filters );
		}

		return array();

	}

	/**
	 * Retrieves the dropdown options for lessons.
	 *
	 * @return void
	 */
	public function get_dropdown_options_ajax_handler_lessons() {

		Automator()->utilities->ajax_auth_check();

		$lessons = array();

		$selected_course = intval( filter_input( INPUT_POST, 'value', FILTER_SANITIZE_NUMBER_INT ) );

		$course = get_term_by( 'term_id', $selected_course, 'tva_courses' );

		// Only renders if its a valid course.
		if ( ! empty( $course ) && $course instanceof \WP_Term ) {

			$tva_lessons = $this->get_course_lessons( $course, array() );

			if ( ! empty( $tva_lessons ) ) {

				// Add the any option.
				$lessons[] = array(
					'value' => -1,
					'text'  => esc_html__( 'Any lesson', 'uncanny-automator' ),
				);

				// Populate the lessons.
				foreach ( $tva_lessons as $lesson ) {

					if ( isset( $lesson->ID ) && isset( $lesson->post_title ) ) {

						$lessons[] = array(
							'value' => $lesson->ID,
							'text'  => $lesson->post_title,
						);

					}
				}
			}
		}

		wp_send_json( $lessons );

	}

	/**
	 * Retrieves all lessons under a specific course.
	 *
	 * @param \WP_Term $course The TA Course Object.
	 * @param array $filters Filters to pass to filter the results.
	 * @return array The list of lessons.
	 */
	private function get_course_lessons( \WP_Term $course, $filters = array() ) {

		if ( class_exists( '\TVA_Manager' ) && method_exists( '\TVA_Manager', 'get_course_lessons' ) ) {

			return \TVA_Manager::get_all_lessons( $course, $filters );

		}

		return array();

	}


	/**
	 * Retrieves the dropdown options for modules.
	 *
	 * @return void
	 */
	public function get_dropdown_options_ajax_handler_modules() {

		Automator()->utilities->ajax_auth_check();

		$modules = array();

		$selected_course = intval( filter_input( INPUT_POST, 'value', FILTER_SANITIZE_NUMBER_INT ) );

		$course = get_term_by( 'term_id', $selected_course, 'tva_courses' );

		// Only renders if its a valid course.
		if ( ! empty( $course ) && $course instanceof \WP_Term ) {

			$tva_modules = $this->get_course_modules( $course, array() );

			if ( ! empty( $tva_modules ) ) {

				// Add the any option.
				$modules[] = array(
					'value' => -1,
					'text'  => esc_html__( 'Any module', 'uncanny-automator' ),
				);

				// Populate the lessons.
				foreach ( $tva_modules as $module ) {

					if ( isset( $module->ID ) && isset( $module->post_title ) ) {

						$modules[] = array(
							'value' => $module->ID,
							'text'  => $module->post_title,
						);

					}
				}
			}
		}

		wp_send_json( $modules );

	}

	/**
	 * Retrieves all course modules.
	 *
	 * @param \WP_term $course The course wp term object.
	 *
	 * @return array The modules.
	 */
	private function get_course_modules( \WP_Term $course ) {

		if ( class_exists( '\TVA_Manager' ) && method_exists( '\TVA_Manager', 'get_course_lessons' ) ) {

			return \TVA_Manager::get_course_modules( $course );

		}

		return array();

	}

	/**
	 * Get all products.
	 *
	 * @return array The list of all products.
	 */
	public function get_products() {

		$products = get_terms(
			'tva_product',
			array(
				'hide_empty' => false,
			)
		);

		$options_dropdown = array();

		if ( ! empty( $products ) ) {
			foreach ( $products as $product ) {
				if ( $product instanceof \WP_Term ) {
					$options_dropdown[ $product->term_id ] = $product->name;
				}
			}
		}

		return $options_dropdown;

	}

	/**
	 * Returns all relevant tokens of the field `Course`.
	 *
	 * @return array The list of the relevant tokens that should be attached to the course field.
	 */
	public function get_relevant_tokens_courses() {

		return array(
			'COURSE_ID'      => esc_html__( 'Course ID', 'uncanny-automator' ),
			'COURSE_URL'     => esc_html__( 'Course URL', 'uncanny-automator' ),
			'COURSE_AUTHOR'  => esc_html__( 'Course author', 'uncanny-automator' ),
			'COURSE_SUMMARY' => esc_html__( 'Course summary', 'uncanny-automator' ),
			'COURSE_TITLE'   => esc_html__( 'Course title', 'uncanny-automator' ),
		);

	}

	/**
	 * Returns all relevant tokens of the field `Module`.
	 *
	 * @return array The list of the relevant tokens that should be attached to the module field.
	 */
	public function get_relevant_tokens_courses_modules() {

		return array(
			'MODULE_ID'    => esc_html__( 'Module ID', 'uncanny-automator' ),
			'MODULE_URL'   => esc_html__( 'Module URL', 'uncanny-automator' ),
			'MODULE_TITLE' => esc_html__( 'Module title', 'uncanny-automator' ),
		);

	}

	/**
	 * Returns all relevant tokens of the field `Lesson`.
	 *
	 * @return array The list of the relevant tokens that should be attached to the lesson field.
	 */
	public function get_relevant_tokens_courses_lessons() {

		return array(
			'LESSON_ID'    => esc_html__( 'Lesson ID', 'uncanny-automator' ),
			'LESSON_URL'   => esc_html__( 'Lesson URL', 'uncanny-automator' ),
			'LESSON_TITLE' => esc_html__( 'Lesson title', 'uncanny-automator' ),
		);

	}

}
