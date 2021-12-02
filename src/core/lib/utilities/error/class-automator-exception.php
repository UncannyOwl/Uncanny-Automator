<?php


namespace Uncanny_Automator;

/**
 * Class Automator_Exception
 *
 * @package Uncanny_Automator
 */
class Automator_Exception extends \Exception {
	/**
	 * @var
	 */
	public static $instance;

	/**
	 * @return Automator_Exception
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
