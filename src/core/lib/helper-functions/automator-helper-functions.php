<?php

/**
 * File in process
 * @author  Saad
 * @version 2.1.5
 */

/*******************************/
/*********** RECIPE ************/
/*******************************/
/**
 * @param int $user_id
 * @param int $recipe_id
 * @param int $completed
 * @param int $run_number
 *
 * @return int
 */
function automator_insert_recipe_run( int $user_id, int $recipe_id, int $completed, int $run_number ) {
	return Automator()->db->recipe->add( $user_id, $recipe_id, $completed, $run_number );
}

/**
 * @param array $update
 * @param array $where
 * @param array $update_format
 * @param array $where_format
 *
 * @return int
 */
function automator_update_recipe_run( array $update, array $where, array $update_format, array $where_format ) {
	return Automator()->db->recipe->update( $update, $where, $update_format, $where_format );
}

/**
 * @param      $recipe_id
 * @param      $meta_key
 * @param bool $single
 *
 * @return string
 */
function get_uap_recipe_meta( $recipe_id, $meta_key, $single = true ) {
	$value = '';

	return $value;
}

/**
 * @param        $recipe_id
 * @param        $meta_key
 * @param string $meta_value
 *
 * @return bool
 */
function add_uap_recipe_meta( $recipe_id, $meta_key, $meta_value = '' ) {

	return true;
}

/**
 * @param        $recipe_id
 * @param        $meta_key
 * @param string $meta_value
 *
 * @return bool
 */
function update_uap_recipe_meta( $recipe_id, $meta_key, $meta_value = '' ) {

	return true;
}

/**
 * @param        $recipe_id
 * @param        $meta_key
 * @param string $meta_value
 *
 * @return bool
 */
function delete_uap_recipe_meta( $recipe_id, $meta_key, $meta_value = '' ) {

	return true;
}

/*******************************/
/********** TRIGGER ************/
/*******************************/


/**
 * @param      $trigger_id
 * @param      $meta_key
 * @param bool $single
 *
 * @return string
 */
function get_uap_trigger_meta( $trigger_id, $meta_key, $single = true ) {
	$value = '';

	return $value;
}

/**
 * @param        $trigger_id
 * @param        $meta_key
 * @param string $meta_value
 *
 * @return bool
 */
function add_uap_trigger_meta( $trigger_id, $meta_key, $meta_value = '' ) {
	return true;

}

/**
 * @param        $trigger_id
 * @param        $meta_key
 * @param string $meta_value
 *
 * @return bool
 */
function update_uap_trigger_meta( $trigger_id, $meta_key, $meta_value = '' ) {
	return true;

}

/**
 * @param        $trigger_id
 * @param        $meta_key
 * @param string $meta_value
 *
 * @return bool
 */
function delete_uap_trigger_meta( $trigger_id, $meta_key, $meta_value = '' ) {
	return true;

}

/*******************************/
/*********** ACTION ************/
/*******************************/


/**
 * @param      $action_id
 * @param      $meta_key
 * @param bool $single
 *
 * @return string
 */
function get_uap_action_meta( $action_id, $meta_key, $single = true ) {
	$value = '';

	return $value;
}

/**
 * @param        $action_id
 * @param        $meta_key
 * @param string $meta_value
 *
 * @return bool
 */
function add_uap_action_meta( $action_id, $meta_key, $meta_value = '' ) {
	return true;

}

/**
 * @param        $action_id
 * @param        $meta_key
 * @param string $meta_value
 *
 * @return bool
 */
function update_uap_action_meta( $action_id, $meta_key, $meta_value = '' ) {
	return true;

}

/**
 * @param        $action_id
 * @param        $meta_key
 * @param string $meta_value
 *
 * @return bool
 */
function delete_uap_action_meta( $action_id, $meta_key, $meta_value = '' ) {
	return true;
}

