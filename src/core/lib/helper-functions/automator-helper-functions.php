<?php

/**
 * @param int $user_id
 * @param int $recipe_id
 * @param int $completed
 * @param int $run_number
 *
 * @return int
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_add_recipe_run( int $user_id, int $recipe_id, int $completed, int $run_number ) {
	return Automator()->db->recipe->add( $user_id, $recipe_id, $completed, $run_number );
}

/**
 * @param array $update
 * @param array $where
 * @param array $update_format
 * @param array $where_format
 *
 * @return int
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_update_recipe_run( array $update, array $where, array $update_format, array $where_format ) {
	return Automator()->db->recipe->update( $update, $where, $update_format, $where_format );
}


/**
 * @param $field_code
 * @param $label
 * @param string $default
 * @param bool $required
 *
 * @return mixed|void
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_add_text_field( $field_code, $label, string $default = '', bool $required = true ) {
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
 * @param string $default
 * @param bool $required
 *
 * @return mixed|void
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_add_email_field( $field_code, $label, string $default = '', bool $required = true ) {
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
 * @param string $default
 * @param bool $required
 *
 * @return mixed|void
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_add_integer_field( $field_code, $label, string $default = '', bool $required = true ) {
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
 * @param string $default
 * @param bool $required
 *
 * @return mixed|void
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_add_float_field( $field_code, $label, string $default = '', bool $required = true ) {
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
 * @param string $default
 * @param bool $required
 *
 * @return mixed|void
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_add_textarea_field( $field_code, $label, string $default = '', bool $required = true ) {
	$options = array(
		'option_code' => $field_code,
		'input_type'  => 'textarea',
		'label'       => $label,
		'default'     => $default,
		'required'    => $required,
	);

	return Automator()->helpers->recipe->field->text( $options );
}
