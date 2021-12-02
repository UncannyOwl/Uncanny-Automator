<?php


namespace Uncanny_Automator;

/**
 * Class Automator_DB_Handler_Tokens
 *
 * @package Uncanny_Automator
 */
class Automator_DB_Handler_Tokens {
	/**
	 * @var
	 */
	public static $instance;

	/**
	 * @return Automator_DB_Handler_Tokens
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * @param $meta_key
	 * @param $meta_value
	 * @param $args
	 *
	 * @return bool|int|null
	 */
	public function save( $meta_key, $meta_value, $args ) {

		return Automator()->db->trigger->add_token_meta( $meta_key, $meta_value, $args );
	}

	/**
	 * @param $meta_key
	 * @param $args
	 *
	 * @return mixed|string
	 */
	public function get( $meta_key, $args = array() ) {

		return Automator()->db->trigger->get_token_meta( $meta_key, $args );
	}
}
