<?php


namespace Uncanny_Automator;


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
	 * @var \Uncanny_Automator_Pro\Wp_Courseware_Pro_Helpers
	 */
	public $pro;

	/**
	 * @param Wp_Courseware_Helpers $options
	 */
	public function setOptions( Wp_Courseware_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param \Uncanny_Automator_Pro\Wp_Courseware_Pro_Helpers $pro
	 */
	public function setPro( \Uncanny_Automator_Pro\Wp_Courseware_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_wpcw_courses( $label = null, $option_code = 'WPCW_COURSE', $any_option = true ) {

		global $uncanny_automator;
		if ( ! $label ) {
			$label = __( 'Course', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'wpcw_course',
			'posts_per_page' => 9999,
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

		return apply_filters( 'uap_option_all_wpcw_courses', $option );
	}


	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */

	public function all_wpcw_modules( $label = null, $option_code = 'WPCW_MODULE', $any_option = true ) {

		global $uncanny_automator;
		if ( ! $label ) {
			$label = __( 'Module', 'uncanny-automator' );
		}
		$modules = array();
		$options = [];
		if ( $uncanny_automator->helpers->recipe->load_helpers ) {
			if ( function_exists( 'wpcw_get_modules' ) ) {
				$modules = wpcw_get_modules();
			}

			if ( $any_option ) {
				$options['-1'] = __( 'Any module', 'uncanny-automator' );
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

		if ( ! $label ) {
			$label = __( 'Unit', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'course_unit',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		global $uncanny_automator;
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

		return apply_filters( 'uap_option_all_wpcw_units', $option );
	}
}