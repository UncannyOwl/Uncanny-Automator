<?php
namespace Uncanny_Automator\Services\Recipe\Common;

/**
 * A reusable trait for importing the jsonSerialize method for serializing the object.
 *
 * @package Uncanny_Automator\Services\Recipe\Common\Trait_JSON_Serializer
 * @since 5.0
 */
trait Trait_JSON_Serializer {

	/**
	 * Properties to exclude from JSON serialization.
	 *
	 * These are used internally by PHP for configuration but shouldn't
	 * be exposed to the frontend.
	 *
	 * @var string[]
	 */
	private static $excluded_from_json = array(
		'_config', // Internal configuration for Structure class.
	);

	/**
	 * Properties that should serialize as {} when empty (key-value maps).
	 *
	 * These are associative array properties where an empty value should be
	 * represented as an empty object {} in JSON, not an empty array [].
	 *
	 * Note: Only 'fields' qualifies as a key-value map. Properties like 'tokens',
	 * 'filters', and 'items' are sequential arrays (lists) and should remain [].
	 *
	 * @var string[]
	 */
	private static $empty_as_object_properties = array(
		'fields', // Field configurations (key-value maps).
	);

	/**
	 * Serializes the object to JSON, excluding internal properties.
	 *
	 * Attributes will be treated as comments on versions of PHP that support them.
	 *
	 * @return array
	 */
	#[\ReturnTypeWillChange] // phpcs:ignore Uncanny_Automator.PHP.PHP80Features.Attribute
	public function jsonSerialize() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid

		$vars = get_object_vars( $this );

		// Remove explicitly excluded internal properties.
		foreach ( self::$excluded_from_json as $excluded ) {
			unset( $vars[ $excluded ] );
		}

		// Convert empty key-value map properties to stdClass for {} serialization.
		foreach ( self::$empty_as_object_properties as $property ) {
			if ( isset( $vars[ $property ] ) && is_array( $vars[ $property ] ) && empty( $vars[ $property ] ) ) {
				$vars[ $property ] = new \stdClass();
			}
		}

		return $vars;
	}
}
