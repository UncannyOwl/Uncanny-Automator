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
	 * The run (recipe log ID) the errors belong to.
	 *
	 * @var int
	 */
	protected $recipe_log_id = 0;

	/**
	 * Hold the failed conditions of the given run. A different run starts with none, so
	 * runs in one PHP process (a queue drain, cron or WP-CLI) never share errors.
	 *
	 * @since 8.0.0
	 *
	 * @param int $recipe_log_id The run's recipe log ID.
	 *
	 * @return void
	 */
	public function use_run( $recipe_log_id ) {

		$recipe_log_id = absint( $recipe_log_id );

		if ( $recipe_log_id === $this->recipe_log_id ) {
			return;
		}

		$this->recipe_log_id = $recipe_log_id;
		$this->errors        = array();
	}

	/**
	 * The failed conditions of the given run: none when the registry holds another run's.
	 *
	 * @since 8.0.0
	 *
	 * @param int $recipe_log_id The run's recipe log ID.
	 *
	 * @return string[]
	 */
	public function get_run_errors( $recipe_log_id ) {
		return absint( $recipe_log_id ) === $this->recipe_log_id ? $this->errors : array();
	}

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
