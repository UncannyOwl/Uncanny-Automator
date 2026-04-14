<?php
namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol;

use Exception;
use InvalidArgumentException;

/**
 * Schema Validator for MCP tools.
 *
 * Enforces the input schema declared by each tool: unknown parameters,
 * required fields, types, enums, numeric bounds, string lengths, and
 * regex patterns. Validation is flat — nested object properties are
 * checked by the tool or its service layer.
 *
 * @since 7.0.0
 */
class Schema_Validator {

	/**
	 * Validate params against a tool's input schema.
	 *
	 * @param array $params Input parameters from the MCP client.
	 * @param array $schema Tool schema (top-level `properties`, `required`).
	 *
	 * @return true
	 * @throws Exception On schema shape errors.
	 * @throws InvalidArgumentException On parameter validation errors.
	 */
	public static function validate_mcp_params( array $params, array $schema ) {

		$properties = (array) ( $schema['properties'] ?? array() );
		$allowed    = array_keys( $properties );

		// Schema declares no properties but caller sent some.
		if ( empty( $allowed ) && ! empty( $params ) ) {
			throw new Exception( 'Schema properties are not defined properly. Schema expects no parameters but some were provided.', 400 );
		}

		// Reject unknown parameters (catches agent hallucinations).
		$unknown = array_diff( array_keys( $params ), $allowed );
		if ( ! empty( $unknown ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Unknown parameter(s): %s', esc_html( implode( ', ', $unknown ) ) ),
				400
			);
		}

		// Required fields must be present.
		$required = (array) ( $schema['required'] ?? array() );
		$missing  = array_diff( $required, array_keys( $params ) );
		if ( ! empty( $missing ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Missing required parameter(s): %s', esc_html( implode( ', ', $missing ) ) ),
				400
			);
		}

		// Per-field validation.
		foreach ( $params as $key => $value ) {
			self::validate_field( (string) $key, $value, (array) $properties[ $key ] );
		}

		return true;
	}

	/**
	 * Validate a single parameter value against its field schema.
	 *
	 * @param string $key   Parameter name.
	 * @param mixed  $value Parameter value.
	 * @param array  $field Field schema from `properties[$key]`.
	 *
	 * @throws InvalidArgumentException When validation fails.
	 */
	private static function validate_field( string $key, $value, array $field ): void {

		// Type check (string | integer | number | boolean | array | object | null).
		if ( isset( $field['type'] ) && ! self::matches_type( $value, $field['type'] ) ) {
			throw new InvalidArgumentException(
				sprintf(
					'Parameter "%s" must be of type %s, got %s.',
					esc_html( $key ),
					esc_html( is_array( $field['type'] ) ? implode( '|', $field['type'] ) : (string) $field['type'] ),
					esc_html( gettype( $value ) )
				),
				400
			);
		}

		// Enum check.
		if ( isset( $field['enum'] ) && is_array( $field['enum'] ) && ! in_array( $value, $field['enum'], true ) ) {
			throw new InvalidArgumentException(
				sprintf(
					'Parameter "%s" must be one of: %s.',
					esc_html( $key ),
					esc_html( implode( ', ', array_map( 'strval', $field['enum'] ) ) )
				),
				400
			);
		}

		// Numeric bounds.
		if ( is_int( $value ) || is_float( $value ) ) {
			if ( isset( $field['minimum'] ) && $value < $field['minimum'] ) {
				throw new InvalidArgumentException(
					sprintf( 'Parameter "%s" must be >= %s.', esc_html( $key ), esc_html( (string) $field['minimum'] ) ),
					400
				);
			}
			if ( isset( $field['maximum'] ) && $value > $field['maximum'] ) {
				throw new InvalidArgumentException(
					sprintf( 'Parameter "%s" must be <= %s.', esc_html( $key ), esc_html( (string) $field['maximum'] ) ),
					400
				);
			}
		}

		// String bounds and pattern.
		if ( is_string( $value ) ) {
			if ( isset( $field['minLength'] ) && mb_strlen( $value ) < $field['minLength'] ) {
				throw new InvalidArgumentException(
					sprintf( 'Parameter "%s" must be at least %d characters.', esc_html( $key ), (int) $field['minLength'] ),
					400
				);
			}
			if ( isset( $field['maxLength'] ) && mb_strlen( $value ) > $field['maxLength'] ) {
				throw new InvalidArgumentException(
					sprintf( 'Parameter "%s" must not exceed %d characters.', esc_html( $key ), (int) $field['maxLength'] ),
					400
				);
			}
			if ( isset( $field['pattern'] ) && is_string( $field['pattern'] ) && '' !== $field['pattern'] ) {
				$delimited = '/' . str_replace( '/', '\\/', $field['pattern'] ) . '/';
				// Pattern comes from the tool's own schema_definition(), not user input. An invalid
				// regex would surface as a PHP warning during development, which is desired.
				if ( 1 !== preg_match( $delimited, $value ) ) {
					throw new InvalidArgumentException(
						sprintf( 'Parameter "%s" does not match the required format.', esc_html( $key ) ),
						400
					);
				}
			}
		}
	}

	/**
	 * Check whether a value matches a JSON Schema type (or array of types).
	 *
	 * @param mixed        $value Value to test.
	 * @param string|array $type  JSON Schema type name or list of alternatives.
	 *
	 * @return bool
	 */
	private static function matches_type( $value, $type ): bool {

		if ( is_array( $type ) ) {
			foreach ( $type as $alternative ) {
				if ( self::matches_type( $value, $alternative ) ) {
					return true;
				}
			}
			return false;
		}

		switch ( (string) $type ) {
			case 'string':
				return is_string( $value );
			case 'integer':
				// Accept only true integers. Reject numeric strings and floats.
				return is_int( $value );
			case 'number':
				return is_int( $value ) || is_float( $value );
			case 'boolean':
				return is_bool( $value );
			case 'array':
				// JSON arrays decode to PHP indexed arrays (sequential 0..n-1 keys).
				if ( ! is_array( $value ) ) {
					return false;
				}
				if ( array() === $value ) {
					return true;
				}
				return array_keys( $value ) === range( 0, count( $value ) - 1 );
			case 'object':
				// JSON objects decode to PHP associative arrays.
				return is_array( $value );
			case 'null':
				return null === $value;
		}

		// Unknown type — be permissive.
		return true;
	}
}
