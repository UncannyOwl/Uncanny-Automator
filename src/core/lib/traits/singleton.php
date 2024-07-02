<?php
namespace Uncanny_Automator\Traits;

/**
 * Trait Singleton
 *
 * Provides a singleton pattern implementation.
 *
 * Ensures a class has only one instance and provides a global point of access to it.
 *
 * @since 5.9.0
 */
trait Singleton {

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 */
	private static $instance = null;

	/**
	 * Get the single instance of the class.
	 *
	 * @return self The single instance of the class.
	 */
	public static function get_instance() {

		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of the instance.
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization of the instance.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize a singleton.' );
	}
}
