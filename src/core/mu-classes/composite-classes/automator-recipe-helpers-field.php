<?php


namespace Uncanny_Automator;


/**
 * Class Automator_Helpers_Recipe_Field
 * @package Uncanny_Automator
 */
class Automator_Helpers_Recipe_Field extends Automator_Helpers_Recipe {
	/**
	 * Automator_Helpers_Recipe_Field constructor.
	 */
	public function __construct() {
	}

	/**
	 * @param string $option_code
	 * @param string $label
	 * @param string $description
	 * @param string $placeholder
	 *
	 * @return mixed
	 */
	public function integer_field( $option_code = 'INT', $label = null, $description = null, $placeholder = null ) {

		if ( ! $label ) {
			$label = __( 'Number', 'uncanny-automator' );
		}

		if ( ! $description ) {
			$description = '';
		}

		if ( ! $placeholder ) {
			$placeholder = __( 'Example: 1', 'uncanny-automator' );
		}

		$option = [
			'option_code' => $option_code,
			'label'       => $label,
			'description' => $description,
			'placeholder' => $placeholder,
			'input_type'  => 'int',
			'required'    => true,
		];


		return apply_filters( 'uap_option_integer_field', $option );
	}

	/**
	 * @param string $option_code
	 * @param string $label
	 * @param string $description
	 * @param string $placeholder
	 *
	 * @return mixed
	 */
	public function float_field( $option_code = 'FLOAT', $label = null, $description = null, $placeholder = null ) {

		if ( ! $label ) {
			$label = __( 'Number', 'uncanny-automator' );
		}

		if ( ! $description ) {
			$description = '';
		}

		if ( ! $placeholder ) {
			$placeholder = __( 'Example: 1.1', 'uncanny-automator' );
		}

		$option = [
			'option_code' => $option_code,
			'label'       => $label,
			'description' => $description,
			'placeholder' => $placeholder,
			'input_type'  => 'float',
			'required'    => true,
		];


		return apply_filters( 'uap_option_float_field', $option );
	}

	/**
	 * @param string $option_code
	 * @param string $label
	 * @param bool $tokens
	 * @param string $type
	 * @param string $default
	 * @param bool
	 * @param string $description
	 * @param string $placeholder
	 *
	 * @return mixed
	 */
	public function text_field( $option_code = 'TEXT', $label = null, $tokens = true, $type = 'text', $default = null, $required = true, $description = '', $placeholder = null ) {

		if ( ! $label ) {
			$label = __( 'Text', 'uncanny-automator' );
		}

		if ( ! $description ) {
			$description = '';
		}

		if ( ! $placeholder ) {
			$placeholder = '';
		}

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'description'     => $description,
			'placeholder'     => $placeholder,
			'input_type'      => $type,
			'supports_tokens' => $tokens,
			'required'        => $required,
			'default_value'   => $default,
		];

		if ( 'textarea' === $type ) {
			$option['supports_tinymce'] = true;
		}


		return apply_filters( 'uap_option_text_field', $option );
	}


	/**
	 * @param string $option_code
	 * @param string $label
	 * @param array $options
	 * @param string $default
	 * @param bool $is_ajax
	 * @param string $fill_values_in
	 *
	 * @return mixed
	 */
	public function select_field( $option_code = 'SELECT', $label = null, $options = [], $default = null, $is_ajax = false, $fill_values_in = '', $relevant_tokens = [] ) {

		// TODO this function should be the main way to create select fields
		// TODO chained values should be introduced using the format in function "list_gravity_forms"
		// TODO the following function should use this function to create selections
		// -- less_or_greater_than
		// -- all_posts
		// -- all_pages
		// -- all_ld_courses
		// -- all_ld_lessons
		// -- all_ld_topics
		// -- all_ld_groups
		// -- all_ld_quiz
		// -- all_buddypress_groups
		// -- all_wc_products
		// -- list_contact_form7_forms
		// -- list_bbpress_forums
		// -- wc_order_statuses
		// -- wp_user_roles
		// -- list_gravity_forms
		// -- all_ec_events
		// -- all_lp_courses
		// -- all_lp_lessons
		// -- all_lf_courses
		// -- all_lf_lessons

		if ( ! $label ) {
			$label = __( 'Option', 'uncanny-automator' );
		}

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'supports_tokens' => apply_filters( 'uap_option_' . $option_code . '_select_field', false ),
			'required'        => true,
			'default_value'   => $default,
			'options'         => $options,
			//'is_ajax'         => $is_ajax,
			//'chained_to'      => $fill_values_in,
		];

		if ( ! empty( $relevant_tokens ) ) {
			$option['relevant_tokens'] = $relevant_tokens;
		}

		return apply_filters( 'uap_option_select_field', $option );
	}

	/**
	 * @param string $option_code
	 * @param string $label
	 * @param array $options
	 * @param string $default
	 * @param bool $is_ajax
	 *
	 * @return mixed
	 */
	public function select_field_ajax( $option_code = 'SELECT', $label = null, $options = [], $default = null, $placeholder = '', $supports_token = false, $is_ajax = false, $args = [], $relevant_tokens = [] ) {


		// TODO this function should be the main way to create select fields
		// TODO chained values should be introduced using the format in function "list_gravity_forms"
		// TODO the following function should use this function to create selections
		// -- less_or_greater_than
		// -- all_posts
		// -- all_pages
		// -- all_ld_courses
		// -- all_ld_lessons
		// -- all_ld_topics
		// -- all_ld_groups
		// -- all_ld_quiz
		// -- all_buddypress_groups
		// -- all_wc_products
		// -- list_contact_form7_forms
		// -- list_bbpress_forums
		// -- wc_order_statuses
		// -- wp_user_roles
		// -- list_gravity_forms
		// -- all_ec_events
		// -- all_lp_courses
		// -- all_lp_lessons
		// -- all_lf_courses
		// -- all_lf_lessons

		if ( ! $label ) {
			$label = __( 'Option', 'uncanny-automator' );
		}

		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'supports_tokens' => apply_filters( 'uap_option_' . $option_code . '_select_field', false ),
			'required'        => true,
			'default_value'   => $default,
			'options'         => $options,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'integration'     => 'GF',
			'endpoint'        => $end_point,
			'placeholder'     => $placeholder,
		];

		if ( ! empty( $relevant_tokens ) ) {
			$option['relevant_tokens'] = $relevant_tokens;
		}

		return apply_filters( 'uap_option_select_field_ajax', $option );
	}

}