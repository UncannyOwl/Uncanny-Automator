<?php

namespace Uncanny_Automator;

/**
 * Class Automator_Handle_Anon
 *
 * @package Uncanny_Automator
 */
class Automator_Handle_Anon {

	/**
	 * Automator_Handle_Anon constructor.
	 */
	public function __construct() {
		add_filter( 'uap_meta_box_title', array( $this, 'uap_meta_box_title_func' ), 10, 2 );
		add_filter( 'automator_recipe_types', array( $this, 'uap_recipe_types_func' ), 10 );
		add_filter( 'uap_error_messages', array( $this, 'uap_error_messages_func' ), 10 );
	}

	/**
	 * @param $default
	 * @param $recipe_type
	 *
	 * @return string|void
	 */
	public function uap_meta_box_title_func( $default, $recipe_type ) {
		if ( 'anonymous' === (string) $recipe_type ) {
			return __( 'Trigger', 'uncanny-automator' );
		}

		return $default;
	}

	/**
	 * @param $recipe_types
	 *
	 * @return array
	 */
	public function uap_recipe_types_func( $recipe_types ) {
		if ( ! in_array( 'anonymous', $recipe_types, true ) ) {
			$recipe_types[] = 'anonymous';
		}

		return $recipe_types;
	}

	/**
	 * @param $error_messages
	 *
	 * @return mixed
	 */
	public function uap_error_messages_func( $error_messages ) {

		$error_messages['anon-user-action-do-nothing'] = __( 'Anonymous recipe user action set to do nothing.', 'uncanny-automator' );

		return $error_messages;
	}
}
