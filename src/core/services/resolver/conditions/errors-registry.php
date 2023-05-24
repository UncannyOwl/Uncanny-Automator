<?php
namespace Uncanny_Automator\Resolver\Conditions;

/**
 * Conditions_Errors_Registry
 *
 * This is the registry class for the conditions error messages.
 *
 * `Usage:`\
 * $rec = new Conditions_Errors_Record();\
 * $rec->add('condition_id', 'Your error message here');\
 * \
 * `Retrieve:`\
 * $this->get_error( $error )\
 * `Aggregate:`\
 * $rec::get_errors()\
 *
 * @since 4.12
 */
class Errors_Registry {

	/**
	 * @var self
	 */
	protected static $instance;

	/**
	 * @var string[] $errors
	 */
	protected $errors = array();

	/**
	 * @param string $condition_id
	 * @param string $error_message
	 *
	 * @return void
	 */
	public function add( $condition_id, $error_message ) {
		$this->errors[ $condition_id ] = $error_message;
	}

	/**
	 * @param string $condition_id
	 *
	 * @return bool
	 */
	public function has_error( $condition_id ) {
		return isset( $this->errors[ $condition_id ] );
	}

	/**
	 * @param string $condition_id
	 *
	 * @return string|false The condition error if it has. Otherwise, returns false.
	 */
	public function get_error( $condition_id ) {
		return $this->has_error( $condition_id ) ? $this->errors[ $condition_id ] : false;
	}

	/**
	 * Directly injects a set of errors into the object.
	 *
	 * @param string[] $errors
	 *
	 * @return void
	 */
	public function set_errors( $errors ) {
		$this->errors = $errors;
	}

	/**
	 * @return mixed[]
	 */
	public function get_errors() {
		return $this->errors;
	}

	/**
	 * Prevents the object from being unserialized
	 *
	 * @throws \Exception
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize a singleton.' );
	}

	/**
	 * Prevents the object from being directly instantiated
	 */
	protected function __construct() {}

	/**
	 * Prevents the object from being cloned
	 */
	protected function __clone() {}

	/**
	 * Returns an instance of the class
	 *
	 * @return self
	 */
	public static function get_instance() {
		return null === self::$instance ? self::$instance = new self() : self::$instance;
	}

}
