<?php


namespace Uncanny_Automator;


use Uncanny_Automator_Pro\Lifterlms_Pro_Helpers;

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
	 * @var Lifterlms_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Lifterlms_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param Lifterlms_Helpers $options
	 */
	public function setOptions( Lifterlms_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Lifterlms_Pro_Helpers $pro
	 */
	public function setPro( Lifterlms_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param bool   $any_option
	 *
	 * @return mixed
	 */
	public function all_lf_courses( $label = null, $option_code = 'LFCOURSE', $any_option = true ) {
		if ( ! $this->load_options ) {


			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}


		if ( ! $label ) {
			$label = esc_attr__( 'Course', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'course',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];


		$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any course', 'uncanny-automator' ) );

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
				$option_code          => esc_attr__( 'Course title', 'uncanny-automator' ),
				$option_code . '_ID'  => esc_attr__( 'Course ID', 'uncanny-automator' ),
				$option_code . '_URL' => esc_attr__( 'Course URL', 'uncanny-automator' ),
				$option_code . '_THUMB_ID'  => esc_attr__( 'Course featured image ID', 'uncanny-automator' ),
				$option_code . '_THUMB_URL' => esc_attr__( 'Course featured image URL', 'uncanny-automator' ),
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
		if ( ! $this->load_options ) {


			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}


		if ( ! $label ) {
			$label = esc_attr__( 'Lesson', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'lesson',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];


		$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any lesson', 'uncanny-automator' ) );

		$option = [
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'required'                 => true,
			// to setup example, lets define the value the child will be based on
			'current_value'            => false,
			'validation_type'          => 'text',
			'options'                  => $options,
			'relevant_tokens'          => [
				$option_code          => esc_attr__( 'Lesson title', 'uncanny-automator' ),
				$option_code . '_ID'  => esc_attr__( 'Lesson ID', 'uncanny-automator' ),
				$option_code . '_URL' => esc_attr__( 'Lesson URL', 'uncanny-automator' ),
				$option_code . '_THUMB_ID'  => esc_attr__( 'Lesson featured image ID', 'uncanny-automator' ),
				$option_code . '_THUMB_URL' => esc_attr__( 'Lesson featured image URL', 'uncanny-automator' ),
			],
			'custom_value_description' => esc_attr__( 'Lesson ID', 'uncanny-automator' ),
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
		if ( ! $this->load_options ) {


			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}


		if ( ! $label ) {
			$label = esc_attr__( 'Section', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'section',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];


		$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any section', 'uncanny-automator' ) );

		$option = [
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'required'                 => true,
			// to setup example, lets define the value the child will be based on
			'current_value'            => false,
			'validation_type'          => 'text',
			'options'                  => $options,
			'relevant_tokens'          => [
				$option_code          => esc_attr__( 'Section title', 'uncanny-automator' ),
				$option_code . '_ID'  => esc_attr__( 'Section ID', 'uncanny-automator' ),
				$option_code . '_URL' => esc_attr__( 'Section URL', 'uncanny-automator' ),
			],
			'custom_value_description' => esc_attr__( 'Section ID', 'uncanny-automator' ),
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
		if ( ! $this->load_options ) {


			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}


		if ( ! $label ) {
			$label = esc_attr__( 'Membership', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'llms_membership',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];


		$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any membership', 'uncanny-automator' ), $is_all_label );

		$option = [
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'required'                 => true,
			// to setup example, lets define the value the child will be based on
			'current_value'            => false,
			'validation_type'          => 'text',
			'options'                  => $options,
			'relevant_tokens'          => [
				$option_code          => esc_attr__( 'Membership title', 'uncanny-automator' ),
				$option_code . '_ID'  => esc_attr__( 'Membership ID', 'uncanny-automator' ),
				$option_code . '_URL' => esc_attr__( 'Membership URL', 'uncanny-automator' ),
			],
			'custom_value_description' => esc_attr__( 'Membership ID', 'uncanny-automator' ),
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
		if ( ! $this->load_options ) {


			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}


		if ( ! $label ) {
			$label = esc_attr__( 'Quiz', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'llms_quiz',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];


		$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any quiz', 'uncanny-automator' ) );

		$option = [
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'required'                 => true,
			// to setup example, lets define the value the child will be based on
			'current_value'            => false,
			'validation_type'          => 'text',
			'options'                  => $options,
			'relevant_tokens'          => [
				$option_code          => esc_attr__( 'Quiz title', 'uncanny-automator' ),
				$option_code . '_ID'  => esc_attr__( 'Quiz ID', 'uncanny-automator' ),
				$option_code . '_URL' => esc_attr__( 'Quiz URL', 'uncanny-automator' ),
			],
			'custom_value_description' => esc_attr__( 'Quiz ID', 'uncanny-automator' ),
		];

		return apply_filters( 'uap_option_all_lf_quizs', $option );
	}
}
