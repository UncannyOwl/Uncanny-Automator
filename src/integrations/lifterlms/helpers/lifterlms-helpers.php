<?php


namespace Uncanny_Automator;


/**
 * Class Lifterlms_Helpers
 * @package Uncanny_Automator
 */
class Lifterlms_Helpers {
	/**
	 * @var Lifterlms_Helpers
	 */
	public $options;

	/**
	 * @var \Uncanny_Automator_Pro\Lifterlms_Pro_Helpers
	 */
	public $pro;

	/**
	 * @param Lifterlms_Helpers $options
	 */
	public function setOptions( Lifterlms_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param \Uncanny_Automator_Pro\Lifterlms_Pro_Helpers $pro
	 */
	public function setPro( \Uncanny_Automator_Pro\Lifterlms_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param bool $any_option
	 *
	 * @return mixed
	 */
	public function all_lf_courses( $label = null, $option_code = 'LFCOURSE', $any_option = true ) {

		if ( ! $label ) {
			$label = __( 'Select a Course', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'course',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		global $uncanny_automator;
		$options = $uncanny_automator->helpers->recipe->options->wp_query( $args, $any_option, 'course' );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          => __( 'Course Title', 'uncanny-automator' ),
				$option_code . '_ID'  => __( 'Course ID', 'uncanny-automator' ),
				$option_code . '_URL' => __( 'Course URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_lf_courses', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_lf_lessons( $label = null, $option_code = 'LFLESSON', $any_option = true ) {

		if ( ! $label ) {
			$label = __( 'Select a Lesson', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'lesson',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		global $uncanny_automator;
		$options = $uncanny_automator->helpers->recipe->options->wp_query( $args, $any_option, 'lesson' );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          => __( 'Lesson Title', 'uncanny-automator' ),
				$option_code . '_ID'  => __( 'Lesson ID', 'uncanny-automator' ),
				$option_code . '_URL' => __( 'Lesson URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_lf_lessons', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_lf_sections( $label = null, $option_code = 'LFSECTION', $any_option = true ) {

		if ( ! $label ) {
			$label = __( 'Select a Section', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'section',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		global $uncanny_automator;
		$options = $uncanny_automator->helpers->recipe->options->wp_query( $args, $any_option, 'section' );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          => __( 'Section Title', 'uncanny-automator' ),
				$option_code . '_ID'  => __( 'Section ID', 'uncanny-automator' ),
				$option_code . '_URL' => __( 'Section URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_lf_sections', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_lf_memberships( $label = null, $option_code = 'LFMEMBERSHIP', $any_option = true, $is_all_label = false ) {

		if ( ! $label ) {
			$label = __( 'Select a Membership', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'llms_membership',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		global $uncanny_automator;
		$options = $uncanny_automator->helpers->recipe->options->wp_query( $args, $any_option, 'membership', $is_all_label );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          => __( 'Membership Title', 'uncanny-automator' ),
				$option_code . '_ID'  => __( 'Membership ID', 'uncanny-automator' ),
				$option_code . '_URL' => __( 'Membership URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_lf_memberships', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_lf_quizs( $label = null, $option_code = 'LFQUIZ', $any_option = true ) {

		if ( ! $label ) {
			$label = __( 'Select a Quiz', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'llms_quiz',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		global $uncanny_automator;
		$options = $uncanny_automator->helpers->recipe->options->wp_query( $args, $any_option, 'quiz' );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          => __( 'Quiz Title', 'uncanny-automator' ),
				$option_code . '_ID'  => __( 'Quiz ID', 'uncanny-automator' ),
				$option_code . '_URL' => __( 'Quiz URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_lf_quizs', $option );
	}
}