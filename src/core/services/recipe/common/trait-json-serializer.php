<?php
namespace Uncanny_Automator\Services\Recipe\Common;

/**
 * A reusable trait for importing the jsonSerialize method for serializing the object.
 *
 * @package Uncanny_Automator\Services\Recipe\Common\Trait_JSON_Serializer
 * @since 5.0
 */
trait Trait_JSON_Serializer {
	#[\ReturnTypeWillChange] // PHP 8 Compatibility.
	public function jsonSerialize() { //phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return get_object_vars( $this );
	}
}
