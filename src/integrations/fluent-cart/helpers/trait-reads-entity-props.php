<?php

namespace Uncanny_Automator\Integrations\Fluent_Cart;

/**
 * Trait Reads_Entity_Props
 *
 * Shared reader for a property on a FluentCart model (object) or a serialized
 * array. Used by both Fluent_Cart_Helpers and Fluent_Cart_Tokens so the
 * object/array access logic is maintained in one place.
 *
 * @package Uncanny_Automator\Integrations\Fluent_Cart
 */
trait Reads_Entity_Props {

	/**
	 * Read a property from a FluentCart model (object) or a serialized array.
	 *
	 * Deliberately avoids `isset()` on objects. FluentCart's models keep their
	 * columns in a protected attribute bag exposed through `__get`, and the ORM
	 * does not answer `isset()` for them — so an isset-based read reports every
	 * column as missing and silently returns the fallback. Read the property
	 * directly instead, then fall back to the attribute bag itself.
	 *
	 * @param mixed  $entity
	 * @param string $key
	 * @param mixed  $fallback
	 *
	 * @return mixed
	 */
	public function prop( $entity, $key, $fallback = '' ) {

		if ( is_array( $entity ) ) {
			return array_key_exists( $key, $entity ) && null !== $entity[ $key ] ? $entity[ $key ] : $fallback;
		}

		if ( ! is_object( $entity ) ) {
			return $fallback;
		}

		$value = $this->read_object_prop( $entity, $key );

		if ( null !== $value ) {
			return $value;
		}

		// Attribute-bag models: ask the model for the whole bag.
		foreach ( array( 'getAttributes', 'toArray' ) as $method ) {
			if ( ! method_exists( $entity, $method ) ) {
				continue;
			}
			$data = $this->read_object_bag( $entity, $method );
			if ( is_array( $data ) && array_key_exists( $key, $data ) && null !== $data[ $key ] ) {
				return $data[ $key ];
			}
		}

		return $fallback;
	}

	/**
	 * Read a single property, tolerating magic getters that warn or throw.
	 *
	 * @param object $entity
	 * @param string $key
	 *
	 * @return mixed Null when the property cannot be read.
	 */
	private function read_object_prop( $entity, $key ) {
		try {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a magic __get may notice on unknown keys.
			return @$entity->{$key};
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * Read a model's attribute bag, tolerating serializers that throw.
	 *
	 * @param object $entity
	 * @param string $method
	 *
	 * @return array|null
	 */
	private function read_object_bag( $entity, $method ) {
		try {
			return $entity->$method();
		} catch ( \Throwable $e ) {
			return null;
		}
	}
}
