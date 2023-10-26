<?php


namespace Uncanny_Automator;

use Error;

/**
 * Class Automator_Error
 *
 * @package Uncanny_Automator
 */
class Automator_Error extends Error {
	/**
	 * @var
	 */
	public static $instance;

	/**
	 * @return self
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * @param $message
	 * @param $code
	 * @param null $previous
	 */
	public function __construct( $message = '', $code = 0, $previous = null ) {
		parent::__construct( $message, $code, $previous );
		automator_log( $message, $code, AUTOMATOR_DEBUG_MODE, 'error-logs' );
	}
}
