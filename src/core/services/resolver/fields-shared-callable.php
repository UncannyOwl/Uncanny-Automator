<?php
namespace Uncanny_Automator\Resolver;

use Uncanny_Automator\Traits\Singleton;

/**
 * Class Fields_Shared_Callable
 *
 * Manages shared callable fields, ensuring that callables are executed only once per unique key.
 *
 * @since 5.9.0
 */
class Fields_Shared_Callable {

	use Singleton;

	/**
	 * @var string The type of the object (e.g. 'trigger', 'action').
	 */
	protected $type = '';

	/**
	 * @var string The unique action or trigger code.
	 */
	protected $code = '';

	/**
	 * @var array Maintain a single shared copy that is accessible from all instances of the class.
	 */
	protected static $shared_callables = array();

	/**
	 * Sets up which field is currently loaded.
	 *
	 * @param string $type Either 'action' or 'trigger'.
	 * @param string $code The action code or the trigger code.
	 *
	 * @return $this
	 */
	public function with_parameters( $type, $code ) {

		$this->type = $this->resolve_type( $type );
		$this->code = $code;

		return $this;

	}

	/**
	 * Executes the provided callback and stores its result if it hasn't been executed for the current key.
	 *
	 * @param callable $callback The callable to execute.
	 *
	 * @return mixed The result of the callable.
	 * @throws \InvalidArgumentException if the provided argument is not callable.
	 */
	public function get_callable( $callback ) {

		if ( ! is_callable( $callback ) ) {
			throw new \InvalidArgumentException( 'Provided argument is not callable.' );
		}

		$key = $this->generate_key();

		// Check if the callable has already been executed for this key
		if ( $this->has_loaded( $key ) ) {
			return self::$shared_callables[ $key ];
		}

		// Execute the callable and store the result
		self::$shared_callables[ $key ] = call_user_func( $callback );

		return self::$shared_callables[ $key ];

	}

	/**
	 * Resolves the type to 'action' or 'trigger'.
	 *
	 * @param string $type The type to resolve.
	 *
	 * @return string The resolved type.
	 * @throws \InvalidArgumentException if the type is not valid.
	 */
	protected function resolve_type( $type ) {

		if ( $type === 'actions' ) {
			return 'action';
		}
		if ( $type === 'triggers' ) {
			return 'trigger';
		}

		throw new \InvalidArgumentException( 'Invalid type provided. Must be "actions" or "triggers".' );

	}

	/**
	 * Checks if the callable has already been executed for the given key.
	 *
	 * @param string $key The key to check.
	 *
	 * @return bool True if the callable has been executed, false otherwise.
	 */
	protected function has_loaded( $key ) {

		return isset( self::$shared_callables[ $key ] );

	}

	/**
	 * Generates a unique key based on the type and code.
	 *
	 * @return string The generated key.
	 */
	protected function generate_key() {

		return strtolower( $this->type . '_' . $this->code );

	}
}
