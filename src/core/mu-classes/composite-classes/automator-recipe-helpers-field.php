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
			$label =  esc_attr__( 'Number', 'uncanny-automator' );
		}

		if ( ! $description ) {
			$description = '';
		}

		if ( ! $placeholder ) {
			$placeholder =  esc_attr__( 'Example: 1', 'uncanny-automator' );
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
			$label =  esc_attr__( 'Number', 'uncanny-automator' );
		}

		if ( ! $description ) {
			$description = '';
		}

		if ( ! $placeholder ) {
			$placeholder =  esc_attr__( 'Example: 1.1', 'uncanny-automator' );
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
			$label =  esc_attr__( 'Text', 'uncanny-automator' );
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

	public function select_field_args( $args ){
		// Create the array that will contain the field elements
		$field_args = [
			'input_type' => 'select'
		];

		// Check if the select has the required elements
		if ( isset( $args[ 'option_code' ], $args[ 'options' ] ) ){
			// Add the option_code and options to the field $args
			$field_args[ 'option_code' ] = $args[ 'option_code' ];
			$field_args[ 'options' ]     = $args[ 'options' ];

			// Required
			// default: false
			$field_args[ 'required' ] = isset( $args[ 'required' ] ) ? (boolean) $args[ 'required' ] : false;

			// Label
			if ( isset( $args[ 'label' ] ) ){
				$field_args[ 'label' ] = $args[ 'label' ];
			}

			// Description
			if ( isset( $args[ 'description' ] ) ){
				$field_args[ 'description' ] = $args[ 'description' ];
			}

			// Placeholder
			if ( isset( $args[ 'placeholder' ] ) ){
				$field_args[ 'placeholder' ] = $args[ 'placeholder' ];
			}

			// Token name
			// Check if there is a token name defined, otherwise,
			// check if the field has a label defined, if so, use it, otherwise
			// use the field option code
			// default: label, or option_code
			$field_args[ 'token_name' ] = isset( $args[ 'token_name' ] ) ? $args[ 'token_name' ] : ( ! isset( $field_args[ 'label' ] ) ? $field_args[ 'label' ] : $field_args[ 'option_code' ] );

			// Default value
			if ( isset( $args[ 'default_value' ] ) ){
				$field_args[ 'default_value' ] = $args[ 'default_value' ];
			}

			// Relevant tokens
			if ( isset( $args[ 'relevant_tokens' ] ) && is_array( $args[ 'relevant_tokens' ] ) ){
				$field_args[ 'relevant_tokens' ] = $args[ 'relevant_tokens' ];
			}

			// Is AJAX
			// Check if "is_ajax" is defined, if it's true, and if "endpoint"
			// is defined, which is required when "is_ajax" is true
			if ( isset( $args[ 'is_ajax' ] ) && $args[ 'is_ajax' ] && isset( $args[ 'endpoint' ] ) ){
				$field_args[ 'is_ajax' ]  = true;
				$field_args[ 'endpoint' ] = $args[ 'endpoint' ];

				// "target_field"
				if ( isset( $args[ 'target_field' ] ) ){
					$field_args[ 'fill_values_in' ] = $args[ 'target_field' ];
				}

				// "fill_values_in"
				// Check if the element is defined. This parameter is optional, but
				// only relevant if is_ajax is true.
				if ( isset( $args[ 'fill_values_in' ] ) ){
					$field_args[ 'endpoint' ] = $args[ 'fill_values_in' ];
				}
			}

			// Supports multiple values
			// default: false
			$field_args[ 'placeholder' ] = isset( $args[ 'placeholder' ] ) ? $args[ 'placeholder' ] : '';

			// Supports multiple values
			// default: false
			$field_args[ 'supports_multiple_values' ] = isset( $args[ 'supports_multiple_values' ] ) ? $args[ 'supports_multiple_values' ] : false;

			// Supports custom value
			// default: true
			$field_args[ 'supports_custom_value' ] = isset( $args[ 'supports_custom_value' ] ) ? $args[ 'supports_custom_value' ] : true;
			
			// Elements related to supports custom value
			// First we have to check if it supports custom values
			if ( $field_args[ 'supports_custom_value' ] ){
				// Supports tokens
				// default: true
				$field_args[ 'supports_tokens' ] = isset( $args[ 'supports_tokens' ] ) ? $args[ 'supports_tokens' ] : true;

				// Custom value description
				// default: ''
				$field_args[ 'custom_value_description' ] = isset( $args[ 'custom_value_description' ] ) ? $args[ 'custom_value_description' ] : true;
			}
		}

		return apply_filters( 'uap_option_select_field_args', $field_args );
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
	public function select_field( $option_code = 'SELECT', $label = null, $options = [], $default = null, $is_ajax = false, $fill_values_in = '', $relevant_tokens = [], $args = [] ) {

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
			$label =  esc_attr__( 'Option', 'uncanny-automator' );
		}

		$custom_value_description  = key_exists( 'custom_value_description', $args ) ? $args['custom_value_description'] : null;
		$supports_custom_value  = key_exists( 'supports_custom_value', $args ) ? $args['supports_custom_value'] : null;
		$supports_tokens  = key_exists( 'supports_tokens', $args ) ? $args['supports_tokens'] : null;

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'supports_tokens' => apply_filters( 'uap_option_' . $option_code . '_select_field', false ),
			'required'        => true,
			'default_value'   => $default,
			'options'         => $options,
			'custom_value_description' => $custom_value_description,
			'supports_custom_value' => $supports_custom_value,
			'supports_tokens' => $supports_tokens,
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
			$label =  esc_attr__( 'Option', 'uncanny-automator' );
		}

		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$description  = key_exists( 'description', $args ) ? $args['description'] : null;
		$custom_value_description  = key_exists( 'custom_value_description', $args ) ? $args['custom_value_description'] : null;
		$supports_custom_value  = key_exists( 'supports_custom_value', $args ) ? $args['supports_custom_value'] : null;
		$supports_tokens  = key_exists( 'supports_tokens', $args ) ? $args['supports_tokens'] : null;

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'description'     => $description,
			'input_type'      => 'select',
			'supports_tokens' => apply_filters( 'uap_option_' . $option_code . '_select_field', $supports_tokens ),
			'required'        => true,
			'default_value'   => $default,
			'options'         => $options,
			'custom_value_description' => $custom_value_description,
			'supports_custom_value' => $supports_custom_value,
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