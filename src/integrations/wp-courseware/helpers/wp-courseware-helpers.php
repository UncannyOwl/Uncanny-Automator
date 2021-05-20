<?php


namespace Uncanny_Automator;


use Uncanny_Automator_Pro\Wp_Courseware_Pro_Helpers;

/**
 * Class Wp_Courseware_Helpers
 * @package Uncanny_Automator
 */
class Wp_Courseware_Helpers {
	/**
	 * @var Wp_Courseware_Helpers
	 */
	public $options;

	/**
	 * @var Wp_Courseware_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Wp_Courseware_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param Wp_Courseware_Helpers $options
	 */
	public function setOptions( Wp_Courseware_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Wp_Courseware_Pro_Helpers $pro
	 */
	public function setPro( Wp_Courseware_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_wpcw_courses( $label = null, $option_code = 'WPCW_COURSE', $any_option = true ) {
		if ( ! $this->load_options ) {


			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}


		if ( ! $label ) {
			$label = esc_attr__( 'Course', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'wpcw_course',
			'posts_per_page' => 9999,
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
			],
		];

		return apply_filters( 'uap_option_all_wpcw_courses', $option );
	}


	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */

	public function all_wpcw_modules( $label = null, $option_code = 'WPCW_MODULE', $any_option = true ) {
		if ( ! $this->load_options ) {


			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}



		if ( ! $label ) {
			$label = esc_attr__( 'Module', 'uncanny-automator' );
		}
		$modules = array();
		$options = array();
		if ( Automator()->helpers->recipe->load_helpers ) {
			if ( function_exists( 'wpcw_get_modules' ) ) {
				$modules = wpcw_get_modules();
			}

			if ( $any_option ) {
				$options['-1'] = esc_attr__( 'Any module', 'uncanny-automator' );
			}
			if ( ! empty( $modules ) ) {
				foreach ( $modules as $module ) {
					$options[ $module->module_id ] = $module->module_title;
				}
			}
		}
		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
		];

		return apply_filters( 'uap_option_all_wpcw_modules', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_wpcw_units( $label = null, $option_code = 'WPCW_UNIT', $any_option = true ) {
		if ( ! $this->load_options ) {


			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}


		if ( ! $label ) {
			$label = esc_attr__( 'Unit', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'course_unit',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];


		$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any unit', 'uncanny-automator' ) );

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
				$option_code          => esc_attr__( 'Unit title', 'uncanny-automator' ),
				$option_code . '_ID'  => esc_attr__( 'Unit ID', 'uncanny-automator' ),
				$option_code . '_URL' => esc_attr__( 'Unit URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_wpcw_units', $option );
	}
}
