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
	 * Object storage for cycle detection during cleaning.
	 *
	 * @var SplObjectStorage
	 */
	private $seen;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->seen = new SplObjectStorage();
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
			$package = array(
				'args'     => $this->clean_args( $args ),
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
			$data = maybe_unserialize( $payload );

			if ( is_array( $data ) && isset( $data['args'] ) ) {
				return $data;
			}
			return false;
		} catch ( \Error $e ) {
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
	 * @param mixed $val The value to clean.
	 *
	 * @return mixed The cleaned value safe for serialization.
	 */
	private function clean( $val ) {

		// Scalars & null.
		if ( is_scalar( $val ) || null === $val ) {
			return $val;
		}

		// Arrays -> recurse.
		if ( is_array( $val ) ) {
			$out = array();
			foreach ( $val as $k => $v ) {
				$out[ $k ] = $this->clean( $v );
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
						$prop->setValue( $clone, $this->clean( $v ) );
					}
					// Scalars left untouched.
				}
				return $clone;
			} catch ( \Exception $e ) {
				// If we can't clone/access, just drop it.
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
