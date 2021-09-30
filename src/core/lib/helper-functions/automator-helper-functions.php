<?php

/**
 * @param $user_id
 * @param $recipe_id
 * @param $completed
 * @param $run_number
 *
 * @return int
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_add_recipe_run( $user_id, $recipe_id, $completed, $run_number ) {
	return Automator()->db->recipe->add( $user_id, $recipe_id, $completed, $run_number );
}

/**
 * @param $update
 * @param $where
 * @param $update_format
 * @param $where_format
 *
 * @return int
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_update_recipe_run( $update, $where, $update_format, $where_format ) {
	return Automator()->db->recipe->update( $update, $where, $update_format, $where_format );
}


/**
 * @param $field_code
 * @param $label
 * @param $default
 * @param $required
 *
 * @return mixed|void
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_add_text_field( $field_code, $label, $default = '',$required = true ) {
	$options = array(
		'option_code' => $field_code,
		'input_type'  => 'text',
		'label'       => $label,
		'default'     => $default,
		'required'    => $required,
	);

	return Automator()->helpers->recipe->field->text( $options );
}

/**
 * @param $field_code
 * @param $label
 * @param $default
 * @param $required
 *
 * @return mixed|void
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_add_email_field( $field_code, $label, $default = '', $required = true ) {
	$options = array(
		'option_code' => $field_code,
		'input_type'  => 'email',
		'label'       => $label,
		'default'     => $default,
		'required'    => $required,
	);

	return Automator()->helpers->recipe->field->text( $options );
}

/**
 * @param $field_code
 * @param $label
 * @param $default
 * @param $required
 *
 * @return mixed|void
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_add_integer_field( $field_code, $label, $default = '', $required = true ) {
	$options = array(
		'option_code' => $field_code,
		'label'       => $label,
		'default'     => $default,
		'required'    => $required,
	);

	return Automator()->helpers->recipe->field->int( $options );
}

/**
 * @param $field_code
 * @param $label
 * @param $default
 * @param $required
 *
 * @return mixed|void
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_add_float_field( $field_code, $label, $default = '', $required = true ) {
	$options = array(
		'option_code' => $field_code,
		'label'       => $label,
		'default'     => $default,
		'required'    => $required,
	);

	return Automator()->helpers->recipe->field->float( $options );
}

/**
 * @param $field_code
 * @param $label
 * @param $default
 * @param $required
 *
 * @return mixed|void
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_add_textarea_field( $field_code, $label, $default = '', $required = true ) {
	$options = array(
		'option_code' => $field_code,
		'input_type'  => 'textarea',
		'label'       => $label,
		'default'     => $default,
		'required'    => $required,
	);

	return Automator()->helpers->recipe->field->text( $options );
}
