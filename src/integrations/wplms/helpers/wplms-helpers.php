<?php


namespace Uncanny_Automator;


/**
 * Class Wplms_Helpers
 * @package Uncanny_Automator
 */
class Wplms_Helpers {
	/**
	 * @var Wplms_Helpers
	 */
	public $options;

	/**
	 * @var \Uncanny_Automator_Pro\Wplms_Pro_Helpers
	 */
	public $pro;

	/**
	 * @param Wplms_Helpers $options
	 */
	public function setOptions( Wplms_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param \Uncanny_Automator_Pro\Wplms_Pro_Helpers $pro
	 */
	public function setPro( \Uncanny_Automator_Pro\Wplms_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_wplms_quizs( $label = null, $option_code = 'WPLMS_QUIZ', $any_option = true ) {
		global $uncanny_automator;
		if ( ! $label ) {
			$label = __( 'Quiz', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'quiz',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = $uncanny_automator->helpers->recipe->options->wp_query( $args, $any_option, __( 'Any quiz', 'uncanny-automator' ) );

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
				$option_code          => __( 'Quiz title', 'uncanny-automator' ),
				$option_code . '_ID'  => __( 'Quiz ID', 'uncanny-automator' ),
				$option_code . '_URL' => __( 'Quiz URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_wplms_quizs', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param bool $any_option
	 *
	 * @return mixed
	 */
	public function all_wplms_courses( $label = null, $option_code = 'WPLMS_COURSE', $any_option = true ) {

		global $uncanny_automator;
		if ( ! $label ) {
			$label = __( 'Course', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'course',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = $uncanny_automator->helpers->recipe->options->wp_query( $args, $any_option, __( 'Any course', 'uncanny-automator' ) );

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
				$option_code          => __( 'Course title', 'uncanny-automator' ),
				$option_code . '_ID'  => __( 'Course ID', 'uncanny-automator' ),
				$option_code . '_URL' => __( 'Course URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_wplms_courses', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_wplms_units( $label = null, $option_code = 'WPLMS_UNIT', $any_option = true ) {

		global $uncanny_automator;
		if ( ! $label ) {
			$label = __( 'Unit', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'unit',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = $uncanny_automator->helpers->recipe->options->wp_query( $args, $any_option, __( 'Any unit', 'uncanny-automator' ) );

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
				$option_code          => __( 'Unit title', 'uncanny-automator' ),
				$option_code . '_ID'  => __( 'Unit ID', 'uncanny-automator' ),
				$option_code . '_URL' => __( 'Unit URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_wplms_units', $option );
	}

}