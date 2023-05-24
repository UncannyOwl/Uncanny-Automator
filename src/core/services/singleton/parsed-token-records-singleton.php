<?php
namespace Uncanny_Automator\Singleton;

/**
 * Class Parsed_Token_Records_Singleton
 *
 * This is a singleton class that allows recording and retrieving token records.
 *
 * It also provides a method to interpolate a given text using an array of values.
 *
 * @since 4.12
 */
class Parsed_Token_Records_Singleton {

	/**
	 * @var Parsed_Token_Records_Singleton $instance. Defaults to null.
	 */
	private static $instance = null;

	/**
	 * @var mixed[]
	 */
	private static $token_record = array();

	/**
	 * Returns an instance of the class
	 *
	 * @return Parsed_Token_Records_Singleton
	 */
	public static function get_instance() {
		return null === self::$instance ? self::$instance = new self() : self::$instance;
	}

	/**
	 * Records the given token and its parsed value
	 *
	 * @param string $raw
	 * @param mixed $parsed
	 * @param mixed[] $args
	 *
	 * @return void
	 */
	public function record_token( $raw, $parsed, $args ) {
		self::$token_record[ $raw ] = $parsed;
	}

	/**
	 * Returns all the recorded tokens
	 *
	 * @return mixed[]
	 */
	public function get_tokens() {
		return self::$token_record;
	}

	/**
	 * Interpolates a given text using an array of values
	 *
	 * @param string $field_text
	 * @param mixed[] $interpolated
	 *
	 * @return string
	 */
	public static function interpolate( $field_text, $interpolated ) {
		return strtr( $field_text, $interpolated );
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

}
