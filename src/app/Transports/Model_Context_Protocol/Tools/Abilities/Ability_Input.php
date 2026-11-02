<?php
/**
 * What an AI app's empty input means to a WordPress ability.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abilities;

/**
 * An MCP client that has nothing to pass leaves the input out, or sends {}.
 * Both mean "no arguments". WordPress reads them differently: a missing
 * input reaches the ability as null, which only an ability without an input
 * schema, or one whose schema declares a default, accepts; and any input,
 * even {}, is refused by an ability without an input schema.
 *
 * Here "no arguments" becomes what the ability's own schema accepts as
 * none. Supplied values are never changed, and WordPress still validates
 * the result, so a missing required field is still refused, now by name.
 */
final class Ability_Input {

	/**
	 * The input to run an ability with.
	 *
	 * @param mixed $input  Input from the AI app; null when it sent none.
	 * @param array $schema The ability's input schema.
	 * @return mixed
	 */
	public static function normalize( $input, array $schema ) {
		// No input schema: the ability takes no input, so {} means none.
		if ( array() === $input && empty( $schema ) ) {
			return null;
		}
		// An object schema without a default would refuse null as "not of
		// type object". No arguments is an empty object. With a default,
		// null lets WordPress apply the author's own.
		if ( null === $input && ! array_key_exists( 'default', $schema ) && in_array( 'object', (array) ( $schema['type'] ?? array() ), true ) ) {
			return array();
		}

		return $input;
	}
}
