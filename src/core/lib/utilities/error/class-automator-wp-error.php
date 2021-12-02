<?php


namespace Uncanny_Automator;

use WP_Error;

/**
 * Class Automator_Error_Handler
 *
 * @package Uncanny_Automator
 */
class Automator_WP_Error {
	/**
	 * @var
	 */
	public static $instance;
	/**
	 * @var
	 */
	public $error_code;
	/**
	 * @var
	 */
	public $message;
	/**
	 * @var
	 */
	public $data;

	/**
	 * @var WP_Error
	 */
	public $wp_error;

	/**
	 * Automator_Error_Handler constructor.
	 */
	public function __construct() {
		$this->wp_error = new WP_Error();
	}

	/**
	 * @return Automator_WP_Error
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * @param $error_code
	 * @param $message
	 * @param $data
	 */
	public function add_error( $error_code, $message, $data = '' ) {
		$this->wp_error->add( $error_code, $message, $data );
	}

	/**
	 * @return mixed[]
	 */
	public function get_all_errors() {
		return $this->wp_error->get_all_error_data();
	}

	/**
	 * @param $type
	 *
	 * @return string
	 */
	public function get_message( $type ) {
		return $this->wp_error->get_error_message( $type );
	}


	/**
	 * @param string $error_code
	 *
	 * @return array
	 */
	public function get_messages( $error_code = '' ) {
		return $this->wp_error->get_error_messages( $error_code );
	}

	/**
	 * @return WP_Error
	 */
	public function get_wp_error_object() {
		return $this->wp_error;
	}

	/**
	 *
	 */
	public function reset_errors() {
		$this->wp_error = new WP_Error();
	}

	/**
	 * @param $message
	 * @param mixed $type flag
	 */
	public function trigger( $message, $type = E_USER_NOTICE ) {
		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && true === AUTOMATOR_DEBUG_MODE ) {
			trigger_error( esc_attr( $message ), $type ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
		}
	}
}
