<?php

namespace Uncanny_Automator;

/**
 * Class Automator_Helpers_Recipe_Field
 *
 * @package Uncanny_Automator
 */
class Automator_Helpers_Recipe_Field extends Automator_Helpers_Recipe {
	/**
	 * Automator_Helpers_Recipe_Field constructor.
	 */
	public function __construct() {
	}

	/**
	 * @param $args
	 *
	 * @return mixed|void
	 */
	public function create_field( $args = array() ) {
		$defaults        = array(
			'option_code'     => 'INT',
			'label'           => esc_attr__( 'Number', 'uncanny-automator' ),
			'description'     => '',
			'placeholder'     => esc_attr__( 'Example: 1', 'uncanny-automator' ),
			'required'        => true,
			'input_type'      => 'int',
			'default'         => '',
			'token_name'      => '',
			'supports_tokens' => true,
		);
		$args            = wp_parse_args( $args, $defaults );
		$option_code     = $args['option_code'];
		$label           = $args['label'];
		$description     = $args['description'];
		$placeholder     = $args['placeholder'];
		$required        = $args['required'];
		$default         = $args['default'];
		$token_name      = $args['token_name'];
		$supports_tokens = $args['supports_tokens'];

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'description'     => $description,
			'placeholder'     => $placeholder,
			'input_type'      => 'int',
			'required'        => $required,
			'default_value'   => $default,
			'token_name'      => $token_name,
			'supports_tokens' => $supports_tokens,
		);

		return apply_filters( 'automator_option_' . strtolower( $option_code ) . '_field', $option, $args );

	}

	/**
	 * @param $args
	 *
	 * @return mixed|void
	 */
	public function int( $args = array() ) {

		$defaults = array(
			'option_code'     => 'INT',
			'label'           => esc_attr__( 'Number', 'uncanny-automator' ),
			'description'     => '',
			'placeholder'     => esc_attr__( 'Example: 1', 'uncanny-automator' ),
			'required'        => true,
			'input_type'      => 'int',
			'default'         => '',
			'token_name'      => '',
			'supports_tokens' => true,
		);
		$args     = wp_parse_args( $args, $defaults );

		return apply_filters( 'automator_option_int_field', $this->create_field( $args ) );
	}

	/**
	 * @param $option_code
	 * @param $label
	 * @param $description
	 * @param $placeholder
	 *
	 * @return mixed
	 * @deprecated 3.0 Use Automator()->helpers->recipe->field->int()
	 */
	public function integer_field( $option_code = 'INT', $label = null, $description = null, $placeholder = null ) {
		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && true === AUTOMATOR_DEBUG_MODE ) {
			_doing_it_wrong( 'Automator()->helpers->recipe->field->integer_field()', 'Use Automator()->helpers->recipe->field->int() instead.', '3.0' );
		}
		$option = array(
			'option_code' => $option_code,
			'label'       => $label,
			'description' => $description,
			'placeholder' => $placeholder,
			'input_type'  => 'int',
			'required'    => true,
		);

		$option = $this->int( $option );
		$option = apply_filters_deprecated( 'uap_option_integer_field', array( $option ), '3.0', 'automator_option_integer_field' );

		return apply_filters( 'automator_option_integer_field', $option );
	}

	/**
	 * @param $args
	 *
	 * @return mixed|void
	 */
	public function float( $args = array() ) {
		$defaults = array(
			'option_code'     => 'FLOAT',
			'label'           => esc_attr__( 'Number', 'uncanny-automator' ),
			'description'     => '',
			'placeholder'     => esc_attr__( 'Example: 1.1', 'uncanny-automator' ),
			'required'        => true,
			'input_type'      => 'float',
			'default'         => '',
			'token_name'      => '',
			'supports_tokens' => true,
		);
		$args     = wp_parse_args( $args, $defaults );

		return apply_filters( 'automator_option_float_field', $this->create_field( $args ) );
	}

	/**
	 * @param $option_code
	 * @param $label
	 * @param $description
	 * @param $placeholder
	 *
	 * @return mixed
	 * @deprecated 3.0 Use Automator()->helpers->recipe->field->float() instead
	 */
	public function float_field( $option_code = 'FLOAT', $label = null, $description = null, $placeholder = null ) {
		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && true === AUTOMATOR_DEBUG_MODE ) {
			_doing_it_wrong( 'Automator()->helpers->recipe->field->float_field()', 'Use Automator()->helpers->recipe->field->float() instead.', '3.0' );
		}
		$option = array(
			'option_code' => $option_code,
			'label'       => $label,
			'description' => $description,
			'placeholder' => $placeholder,
			'input_type'  => 'float',
			'required'    => true,
		);

		$option = $this->float( $option );
		$option = apply_filters_deprecated( 'uap_option_float_field', array( $option ), '3.0', 'automator_option_float_field' );

		return apply_filters( 'automator_option_float_field', $option );
	}

	/**
	 * @param $args
	 *
	 * @return mixed|void
	 */
	public function text( $args = array() ) {
		$defaults = array(
			'option_code'      => 'TEXT',
			'input_type'       => 'text',
			'label'            => esc_attr__( 'Text', 'uncanny-automator' ),
			'placeholder'      => '',
			'description'      => '',
			'required'         => true,
			'tokens'           => true,
			'default'          => null,
			'supports_tinymce' => null,
			'token_name'       => '',
		);

		$args             = wp_parse_args( $args, $defaults );
		$option_code      = $args['option_code'];
		$label            = $args['label'];
		$description      = $args['description'];
		$placeholder      = $args['placeholder'];
		$tokens           = $args['tokens'];
		$type             = $args['input_type'];
		$default          = $args['default'];
		$required         = $args['required'];
		$supports_tinymce = $args['supports_tinymce'];
		$token_name       = $args['token_name'];

		$option = array(
			'option_code'      => $option_code,
			'label'            => $label,
			'description'      => $description,
			'placeholder'      => $placeholder,
			'input_type'       => $type,
			'supports_tokens'  => $tokens,
			'required'         => $required,
			'default_value'    => $default,
			'supports_tinymce' => $supports_tinymce,
			'token_name'       => $token_name,
		);

		// Enable TinyMCE by default for all textarea fields unless other specified
		if ( is_null( $option['supports_tinymce'] ) && 'textarea' === $type ) {
			$option['supports_tinymce'] = true;
		}

		return apply_filters( 'automator_option_text_field', $option );
	}

	/**
	 * @param $option_code
	 * @param $label
	 * @param $tokens
	 * @param $type
	 * @param $default
	 * @param bool
	 * @param $description
	 * @param $placeholder
	 *
	 * @return mixed
	 * @deprecated 3.0 Use Automator()->helpers->recipe->field->text( $args ) instead.
	 */
	public function text_field( $option_code = 'TEXT', $label = null, $tokens = true, $type = 'text', $default = null, $required = true, $description = '', $placeholder = null ) {
		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && true === AUTOMATOR_DEBUG_MODE ) {
			_doing_it_wrong( 'Automator()->helpers->recipe->field->text_field()', 'Use Automator()->helpers->recipe->field->text( $args ) instead.', '3.0' );
		}
		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'description'     => $description,
			'placeholder'     => $placeholder,
			'input_type'      => $type,
			'supports_tokens' => $tokens,
			'required'        => $required,
			'default'         => $default,
		);

		if ( 'textarea' === $type ) {
			$option['supports_tinymce'] = true;
		}
		$option = $this->text( $option );
		$option = apply_filters_deprecated( 'uap_option_text_field', array( $option ), '3.0', 'automator_option_text_field' );

		return apply_filters( 'automator_option_text_field', $option );
	}

	/**
	 * @param $args
	 *
	 * @return mixed|void
	 */
	public function select_field_args( $args ) {
		// Create the array that will contain the field elements
		$field_args = array(
			'input_type' => 'select',
		);

		// Check if the select has the required elements
		if ( isset( $args['option_code'], $args['options'] ) ) {
			// Add the option_code and options to the field $args
			$field_args['option_code'] = $args['option_code'];
			$field_args['options']     = $args['options'];

			// Required
			// default: false
			$field_args['required'] = isset( $args['required'] ) ? (bool) $args['required'] : false;

			// Label
			if ( isset( $args['label'] ) ) {
				$field_args['label'] = $args['label'];
			}

			// Description
			if ( isset( $args['description'] ) ) {
				$field_args['description'] = $args['description'];
			}

			// Placeholder
			if ( isset( $args['placeholder'] ) ) {
				$field_args['placeholder'] = $args['placeholder'];
			}

			// Token name
			// Check if there is a token name defined, otherwise,
			// check if the field has a label defined, if so, use it, otherwise
			// use the field option code
			// default: label, or option_code
			$field_args['token_name'] = isset( $args['token_name'] ) ? $args['token_name'] : ( ! isset( $field_args['label'] ) ? $field_args['label'] : $field_args['option_code'] );

			// Default value
			if ( isset( $args['default_value'] ) ) {
				$field_args['default_value'] = $args['default_value'];
			}

			// Relevant tokens
			if ( isset( $args['relevant_tokens'] ) && is_array( $args['relevant_tokens'] ) ) {
				$field_args['relevant_tokens'] = $args['relevant_tokens'];
			}

			// Is AJAX
			// Check if "is_ajax" is defined, if it's true, and if "endpoint"
			// is defined, which is required when "is_ajax" is true
			if ( isset( $args['is_ajax'] ) && $args['is_ajax'] && isset( $args['endpoint'] ) ) {
				$field_args['is_ajax']  = true;
				$field_args['endpoint'] = $args['endpoint'];

				// "target_field"
				if ( isset( $args['target_field'] ) ) {
					$field_args['fill_values_in'] = $args['target_field'];
				}

				// "fill_values_in"
				// Check if the element is defined. This parameter is optional, but
				// only relevant if is_ajax is true.
				if ( isset( $args['fill_values_in'] ) ) {
					$field_args['endpoint'] = $args['fill_values_in'];
				}
			}

			// Supports multiple values
			// default: false
			$field_args['placeholder'] = isset( $args['placeholder'] ) ? $args['placeholder'] : '';

			// Supports multiple values
			// default: false
			$field_args['supports_multiple_values'] = isset( $args['supports_multiple_values'] ) ? $args['supports_multiple_values'] : false;

			// Supports custom value
			// default: true
			$field_args['supports_custom_value'] = isset( $args['supports_custom_value'] ) ? $args['supports_custom_value'] : true;

			// Elements related to supports custom value
			// First we have to check if it supports custom values
			if ( $field_args['supports_custom_value'] ) {
				// Supports tokens
				// default: true
				$field_args['supports_tokens'] = isset( $args['supports_tokens'] ) ? $args['supports_tokens'] : true;

				// Custom value description
				// default: ''
				$field_args['custom_value_description'] = isset( $args['custom_value_description'] ) ? $args['custom_value_description'] : true;
			}
		}

		$field_args = apply_filters_deprecated( 'uap_option_select_field_args', array( $field_args ), '3.0', 'automator_option_select_field_args' );

		return apply_filters( 'automator_option_select_field_args', $field_args );
	}

	/**
	 * @param $args
	 *
	 * @return mixed|void
	 */
	public function select( $args = array() ) {

		$defaults                 = array(
			'option_code'              => 'SELECT',
			'label'                    => esc_attr__( 'Option', 'uncanny-automator' ),
			'input_type'               => 'select',
			'supports_tokens'          => apply_filters( 'automator_option_select_field', false ),
			'required'                 => true,
			'default_value'            => null,
			'options'                  => array(),
			'custom_value_description' => '',
			'supports_custom_value'    => null,
			'relevant_tokens'          => null,
			'is_ajax'                  => false,
			'chained_to'               => null,
			'endpoint'                 => null,
			'token_name'               => '',
		);
		$args                     = wp_parse_args( $args, $defaults );
		$option_code              = $args['option_code'];
		$input_type               = $args['input_type'];
		$label                    = $args['label'];
		$required                 = $args['required'];
		$default                  = $args['default_value'];
		$options                  = $args['options'];
		$custom_value_description = $args['custom_value_description'];
		$supports_custom_value    = $args['supports_custom_value'];
		$supports_tokens          = $args['supports_tokens'];
		$relevant_tokens          = $args['relevant_tokens'];
		$token_name               = $args['token_name'];

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => $input_type,
			'supports_tokens'          => $supports_tokens,
			'required'                 => $required,
			'default_value'            => $default,
			'options'                  => $options,
			'custom_value_description' => $custom_value_description,
			'supports_custom_value'    => $supports_custom_value,
			'relevant_tokens'          => $relevant_tokens,
			'token_name'               => $token_name,
		);

		// TODO:: add keys optionally
		//      'is_ajax'                  => false,
		//          'chained_to'               => null,
		//          'endpoint'                 => null,

		return apply_filters( 'automator_option_select_field', $option );
	}

	/**
	 * @param $option_code
	 * @param $label
	 * @param $options
	 * @param $default
	 * @param $is_ajax
	 * @param $fill_values_in
	 *
	 * @return mixed
	 * @deprecated 3.0 Use Automator()->helpers->recipe->field->select() instead
	 */
	public function select_field( $option_code = 'SELECT', $label = null, $options = array(), $default = null, $is_ajax = false, $fill_values_in = '', $relevant_tokens = array(), $args = array() ) {
		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && true === AUTOMATOR_DEBUG_MODE ) {
			_doing_it_wrong( 'Automator()->helpers->recipe->field->select_field()', 'Use Automator()->helpers->recipe->field->select() instead.', '3.0' );
		}

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
			$label = esc_attr__( 'Option', 'uncanny-automator' );
		}

		$custom_value_description = key_exists( 'custom_value_description', $args ) ? $args['custom_value_description'] : null;
		$supports_custom_value    = key_exists( 'supports_custom_value', $args ) ? $args['supports_custom_value'] : null;
		$supports_tokens          = key_exists( 'supports_tokens', $args ) ? $args['supports_tokens'] : null;
		$support_token            = apply_filters( 'uap_option_' . $option_code . '_select_field', array( false ), '3.0', 'automator_option_' . $option_code . '_select_field' );
		$support_token            = apply_filters( 'automator_option_' . $option_code . '_select_field', $support_token );
		$option                   = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'supports_tokens'          => $support_token,
			'required'                 => true,
			'default_value'            => $default,
			'options'                  => $options,
			'custom_value_description' => $custom_value_description,
			'supports_custom_value'    => $supports_custom_value,
			'supports_tokens'          => $supports_tokens,
			//'is_ajax'         => $is_ajax,
			//'chained_to'      => $fill_values_in,
		);

		if ( ! empty( $relevant_tokens ) ) {
			$option['relevant_tokens'] = $relevant_tokens;
		}

		//$option = $this->select( $option );
		$option = apply_filters_deprecated( 'uap_option_select_field', array( $option ), '3.0', 'automator_option_select_field' );

		return apply_filters( 'automator_option_select_field', $option );
	}

	/**
	 * @param $option_code
	 * @param $label
	 * @param $options
	 * @param $default
	 * @param $is_ajax
	 *
	 * @return mixed
	 */
	public function select_field_ajax( $option_code = 'SELECT', $label = null, $options = array(), $default = null, $placeholder = '', $supports_token = false, $is_ajax = false, $args = array(), $relevant_tokens = array() ) {

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
			$label = esc_attr__( 'Option', 'uncanny-automator' );
		}

		$target_field             = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point                = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$description              = key_exists( 'description', $args ) ? $args['description'] : null;
		$custom_value_description = key_exists( 'custom_value_description', $args ) ? $args['custom_value_description'] : null;
		$supports_custom_value    = key_exists( 'supports_custom_value', $args ) ? $args['supports_custom_value'] : null;
		$supports_tokens          = key_exists( 'supports_tokens', $args ) ? $args['supports_tokens'] : null;
		$supports_tokens          = apply_filters_deprecated( 'uap_option_' . $option_code . '_select_field', array( $supports_tokens ), '3.0', 'automator_option_' . $option_code . '_select_field' );
		$supports_tokens          = apply_filters( 'automator_option_' . $option_code . '_select_field', $supports_tokens );
		$token_name               = apply_filters( 'automator_option_' . $option_code . '_select_field_token_name', '', $args );
		$option                   = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'description'              => $description,
			'input_type'               => 'select',
			'supports_tokens'          => $supports_tokens,
			'required'                 => true,
			'default_value'            => $default,
			'options'                  => $options,
			'custom_value_description' => $custom_value_description,
			'supports_custom_value'    => $supports_custom_value,
			'is_ajax'                  => $is_ajax,
			'fill_values_in'           => $target_field,
			'integration'              => 'GF',
			'endpoint'                 => $end_point,
			'placeholder'              => $placeholder,
			'token_name'               => $token_name,
		);

		if ( ! empty( $relevant_tokens ) ) {
			$option['relevant_tokens'] = $relevant_tokens;
		}

		$option = apply_filters_deprecated( 'uap_option_select_field_ajax', array( $option ), '3.0', 'automator_option_select_field_ajax' );

		return apply_filters( 'automator_option_select_field_ajax', $option );
	}

}

