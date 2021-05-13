<?php


namespace Uncanny_Automator;

/**
 * Class Automator_DB_Handler_Tokens
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
	 * @param string $meta_key
	 * @param string $meta_value
	 * @param array $args
	 *
	 * @return bool|int|null
	 */
	public function save( string $meta_key, string $meta_value, array $args ) {

		return Automator()->db->trigger->add_token_meta( $meta_key, $meta_value, $args );
	}

	/**
	 * @param string $meta_key
	 * @param array $args
	 *
	 * @return mixed|string
	 */
	public function get( string $meta_key, array $args = array() ) {

		return Automator()->db->trigger->get_token_meta( $meta_key, $args );
	}
}
