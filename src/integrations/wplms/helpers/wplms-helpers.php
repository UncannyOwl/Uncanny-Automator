<?php


namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Wplms_Pro_Helpers;

/**
 * Class Wplms_Helpers
 *
 * @package Uncanny_Automator
 */
class Wplms_Helpers {
	/**
	 * @var Wplms_Helpers
	 */
	public $options;

	/**
	 * @var Wplms_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Wplms_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param Wplms_Helpers $options
	 */
	public function setOptions( Wplms_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Wplms_Pro_Helpers $pro
	 */
	public function setPro( Wplms_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_wplms_quizs( $label = null, $option_code = 'WPLMS_QUIZ', $any_option = true ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Quiz', 'uncanny-automator' );
		}

		$args = array(
			'post_type'      => 'quiz',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any quiz', 'uncanny-automator' ) );

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
			'relevant_tokens' => array(
				$option_code                => esc_attr__( 'Quiz title', 'uncanny-automator' ),
				$option_code . '_ID'        => esc_attr__( 'Quiz ID', 'uncanny-automator' ),
				$option_code . '_URL'       => esc_attr__( 'Quiz URL', 'uncanny-automator' ),
				$option_code . '_THUMB_ID'  => esc_attr__( 'Quiz featured image ID', 'uncanny-automator' ),
				$option_code . '_THUMB_URL' => esc_attr__( 'Quiz featured image URL', 'uncanny-automator' ),
			),
		);

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
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Course', 'uncanny-automator' );
		}

		$args = array(
			'post_type'      => 'course',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any course', 'uncanny-automator' ) );

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
			'relevant_tokens' => array(
				$option_code                => esc_attr__( 'Course title', 'uncanny-automator' ),
				$option_code . '_ID'        => esc_attr__( 'Course ID', 'uncanny-automator' ),
				$option_code . '_URL'       => esc_attr__( 'Course URL', 'uncanny-automator' ),
				$option_code . '_THUMB_ID'  => esc_attr__( 'Course featured image ID', 'uncanny-automator' ),
				$option_code . '_THUMB_URL' => esc_attr__( 'Course featured image URL', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_all_wplms_courses', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_wplms_units( $label = null, $option_code = 'WPLMS_UNIT', $any_option = true ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Unit', 'uncanny-automator' );
		}

		$args = array(
			'post_type'      => 'unit',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any unit', 'uncanny-automator' ) );

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
			'relevant_tokens' => array(
				$option_code                => esc_attr__( 'Unit title', 'uncanny-automator' ),
				$option_code . '_ID'        => esc_attr__( 'Unit ID', 'uncanny-automator' ),
				$option_code . '_URL'       => esc_attr__( 'Unit URL', 'uncanny-automator' ),
				$option_code . '_THUMB_ID'  => esc_attr__( 'Unit featured image ID', 'uncanny-automator' ),
				$option_code . '_THUMB_URL' => esc_attr__( 'Unit featured image URL', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_all_wplms_units', $option );
	}

}
