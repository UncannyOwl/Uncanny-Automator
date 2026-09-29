<?php

namespace Uncanny_Automator\Actionify_Triggers;

use SplObjectStorage;

/**
 * Safe argument handling for triggers.
 *
 * Handles serialization and cleaning of trigger arguments to prevent issues
 * with closures, object cycles, and other non-serializable data.
 *
 * @package Uncanny_Automator\Actionify_Triggers
 * @since 6.7
 */
class Trigger_Arguments {

	/**
	 * Maximum recursion depth for object cleaning.
	 *
	 * @var int
	 */
	const MAX_CLEAN_DEPTH = 10;

	/**
	 * Object storage for cycle detection during cleaning.
	 *
	 * @var SplObjectStorage
	 */
	private $seen;

	/**
	 * Converts hook-argument objects to plain arrays and back.
	 *
	 * @var Trigger_Object_Codec
	 */
	private $codec;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->seen  = new SplObjectStorage();
		$this->codec = new Trigger_Object_Codec();
	}

	/**
	 * Package arguments for safe storage.
	 *
	 * @param array $args Hook arguments to package.
	 * @param array $metadata Trigger metadata to include.
	 *
	 * @return string|false Serialized package or false on failure.
	 */
	public function package( array $args, array $metadata = array() ) {

		try {
			// unpack() decodes with allowed_classes => false, so objects travel as plain
			// arrays. An unsupported object throws and the package is refused.
			$package = array(
				'args'     => $this->codec->encode( $this->clean_args( $args ) ),
				'metadata' => $metadata,
			);
			return maybe_serialize( $package );
		} catch ( \Throwable $e ) {
			$this->log( 'Packager error (package): ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Unpack serialized data.
	 *
	 * @param string $payload The serialized package.
	 *
	 * @return array|false Unpacked data or false on failure.
	 */
	public function unpack( $payload ) {

		try {
			$data = automator_safe_unserialize( $payload );

			if ( ! is_array( $data ) || ! isset( $data['args'] ) ) {
				return false;
			}

			// package() never serializes objects. Any object here is a legacy or forged
			// payload and decodes as __PHP_Incomplete_Class, which would break validation.
			if ( $this->contains_object( $data['args'] ) ) {
				$this->log( 'Packager skipped (unpack): payload contains serialized objects.' );
				return false;
			}

			$data['args'] = $this->codec->decode( $data['args'] );

			return $data;
		} catch ( \Throwable $e ) {
			$this->log( 'Packager error (unpack): ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Clean a single value for safe serialization.
	 *
	 * @param mixed $val The value to clean.
	 *
	 * @return mixed The cleaned value safe for serialization.
	 */
	public function clean_value( $val ) {
		// Reset for each top-level call.
		$this->seen = new SplObjectStorage();
		return $this->clean( $val );
	}

	/**
	 * Check whether a value holds an object at any depth.
	 *
	 * @param mixed $value The value to inspect.
	 *
	 * @return bool True when an object is found.
	 */
	private function contains_object( $value ) {

		if ( is_object( $value ) ) {
			return true;
		}

		if ( ! is_array( $value ) ) {
			return false;
		}

		foreach ( $value as $item ) {
			if ( $this->contains_object( $item ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Clean arguments array for safe serialization.
	 *
	 * @param array $args Arguments to clean.
	 *
	 * @return array Cleaned arguments.
	 */
	private function clean_args( array $args ) {

		$cleaned = array();

		foreach ( $args as $index => $arg ) {
			$cleaned[ $index ] = $this->clean_value( $arg );
		}

		return $cleaned;
	}

	/**
	 * Recursively clean values for safe serialization.
	 *
	 * Handles different data types:
	 * - Scalars and null are returned as-is
	 * - Arrays are recursively cleaned
	 * - Objects are cloned and their properties cleaned
	 * - Closures are removed
	 * - Cycles are broken
	 * - Resources are removed
	 *
	 * @param mixed $val   The value to clean.
	 * @param int   $depth Current recursion depth.
	 *
	 * @return mixed The cleaned value safe for serialization.
	 */
	private function clean( $val, $depth = 0 ) {

		// Scalars & null.
		if ( is_scalar( $val ) || null === $val ) {
			return $val;
		}

		// Prevent excessive recursion on deeply nested objects.
		if ( $depth >= self::MAX_CLEAN_DEPTH ) {
			return null;
		}

		// Arrays -> recurse.
		if ( is_array( $val ) ) {
			$out = array();
			foreach ( $val as $k => $v ) {
				$out[ $k ] = $this->clean( $v, $depth + 1 );
			}
			return $out;
		}

		// Objects -> cycle-detect & clone.
		if ( is_object( $val ) ) {
			// Break cycles.
			if ( $this->seen->contains( $val ) ) {
				return null;
			}
			$this->seen->attach( $val );

			// Drop closures entirely.
			if ( $val instanceof \Closure ) {
				return null;
			}

			// Clone and scrub properties.
			try {
				$clone = clone $val;
				$ref   = new \ReflectionClass( $val );
				foreach ( $ref->getProperties() as $prop ) {
					$prop->setAccessible( true );
					$v = $prop->getValue( $val );
					if ( is_array( $v ) || is_object( $v ) ) {
						$prop->setValue( $clone, $this->clean( $v, $depth + 1 ) );
					}
					// Scalars left untouched.
				}
				return $clone;
			} catch ( \Throwable $e ) {
				// If we can't clone/access (e.g. readonly properties on PHP 8.1+), drop it.
				return null;
			}
		}

		// Drop resources and unknown types.
		return null;
	}

	/**
	 * Log errors.
	 *
	 * @param string $message Error message.
	 *
	 * @return void
	 */
	private function log( $message ) {
		if ( function_exists( 'automator_log' ) ) {
			automator_log( $message, 'safe-hook-arguments-packaging' );
		}
	}
}
