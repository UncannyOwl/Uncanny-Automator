<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol;

use InvalidArgumentException;
use RuntimeException;

// Validation messages are protocol data, not HTML output. Escaping here would
// alter JSON paths, parameter names, and the established Agent error contract.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * Isolated, bounded JSON Schema validator for MCP tool data.
 *
 * This lives in Automator's namespace and does not register a third-party
 * autoloader, which avoids cross-plugin Composer version collisions. It
 * implements the assertion keywords used by the supported JSON Schema
 * dialects while treating unknown annotation keywords as annotations.
 */
final class Schema_Validator {

	private const MAX_SCHEMA_DEPTH = 64;

	private const MAX_SCHEMA_NODES = 10000;

	private const MAX_SCHEMA_BYTES = 1048576;

	private const SUPPORTED_DIALECTS = array(
		'https://json-schema.org/draft/2020-12/schema',
		'https://json-schema.org/draft/2019-09/schema',
		'http://json-schema.org/draft-07/schema#',
		'http://json-schema.org/draft-06/schema#',
	);

	private const DEFAULT_DIALECT = 'https://json-schema.org/draft/2020-12/schema';

	/**
	 * Validate MCP tool arguments.
	 *
	 * @throws InvalidArgumentException When arguments do not match the schema.
	 * @throws RuntimeException When the server-owned schema is unsafe or invalid.
	 */
	public static function validate_mcp_params( array $params, array $schema ) {
		self::validate( $params, $schema, 'Tool input' );

		return true;
	}

	/**
	 * Reject schemas that could perform I/O or consume unbounded work.
	 *
	 * @throws RuntimeException When a schema is unsafe.
	 */
	public static function assert_schema_is_safe( array $schema ): void {
		$encoded = wp_json_encode( $schema );
		if ( ! is_string( $encoded ) || strlen( $encoded ) > self::MAX_SCHEMA_BYTES ) {
			throw new RuntimeException( 'MCP tool schema exceeds the maximum size.' );
		}

		if ( isset( $schema['$schema'] ) && ( ! is_string( $schema['$schema'] ) || ! in_array( $schema['$schema'], self::SUPPORTED_DIALECTS, true ) ) ) {
			throw new RuntimeException( 'The declared JSON Schema dialect is not supported.' );
		}

		$nodes = 0;
		self::inspect_schema_node( $schema, 0, $nodes );
	}

	/**
	 * Run one bounded schema validation.
	 *
	 * @param mixed $value Candidate JSON value.
	 * @throws InvalidArgumentException When the value is invalid.
	 */
	private static function validate( $value, array $schema, string $label ): void {
		self::assert_schema_is_safe( $schema );
		if ( 'Tool input' === $label && is_array( $value ) ) {
			self::validate_top_level_input_contract( $value, $schema );
		}

		try {
			self::validate_node( $value, $schema, $schema, '$', 0 );
		} catch ( InvalidArgumentException $error ) {
			throw new InvalidArgumentException( $label . ' ' . $error->getMessage(), 400, $error );
		}
	}

	/**
	 * Preserve the established Automator tool error contract for top-level
	 * arguments while the full validator handles nested JSON Schema keywords.
	 *
	 * These messages are consumed by Uncanny Agent and are intentionally more
	 * specific than the path-oriented errors used for nested structures.
	 *
	 * @throws InvalidArgumentException When a top-level argument is invalid.
	 */
	private static function validate_top_level_input_contract( array $value, array $schema ): void {
		$properties = isset( $schema['properties'] ) && is_array( $schema['properties'] )
			? $schema['properties']
			: array();

		if ( false === ( $schema['additionalProperties'] ?? true ) ) {
			$unknown = array_diff( array_keys( $value ), array_keys( $properties ) );
			if ( ! empty( $unknown ) ) {
				throw new InvalidArgumentException(
					sprintf( 'Unknown parameter(s): %s', implode( ', ', array_map( 'strval', $unknown ) ) ),
					400
				);
			}
		}

		$required = isset( $schema['required'] ) && is_array( $schema['required'] )
			? $schema['required']
			: array();
		$missing  = array_diff( $required, array_keys( $value ) );
		if ( ! empty( $missing ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Missing required parameter(s): %s', implode( ', ', array_map( 'strval', $missing ) ) ),
				400
			);
		}

		foreach ( $value as $name => $candidate ) {
			if ( ! isset( $properties[ $name ] ) || ! is_array( $properties[ $name ] ) ) {
				continue;
			}
			self::validate_top_level_input_value( (string) $name, $candidate, $properties[ $name ] );
		}
	}

	/**
	 * Validate scalar assertions with the existing client-facing wording.
	 *
	 * @param mixed $value Candidate argument value.
	 * @throws InvalidArgumentException When the argument is invalid.
	 */
	private static function validate_top_level_input_value( string $name, $value, array $schema ): void {
		if ( isset( $schema['type'] ) && ! self::matches_type( $value, $schema['type'] ) ) {
			$type = is_array( $schema['type'] ) ? implode( '|', $schema['type'] ) : (string) $schema['type'];
			throw new InvalidArgumentException(
				sprintf( 'Parameter "%s" must be of type %s, got %s.', $name, $type, gettype( $value ) ),
				400
			);
		}

		if ( isset( $schema['enum'] ) && is_array( $schema['enum'] ) && ! in_array( $value, $schema['enum'], true ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Parameter "%s" must be one of: %s.', $name, implode( ', ', array_map( 'strval', $schema['enum'] ) ) ),
				400
			);
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			if ( isset( $schema['minimum'] ) && $value < $schema['minimum'] ) {
				throw new InvalidArgumentException( sprintf( 'Parameter "%s" must be >= %s.', $name, (string) $schema['minimum'] ), 400 );
			}
			if ( isset( $schema['maximum'] ) && $value > $schema['maximum'] ) {
				throw new InvalidArgumentException( sprintf( 'Parameter "%s" must be <= %s.', $name, (string) $schema['maximum'] ), 400 );
			}
		}

		if ( ! is_string( $value ) ) {
			return;
		}

		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
		if ( isset( $schema['minLength'] ) && $length < (int) $schema['minLength'] ) {
			throw new InvalidArgumentException( sprintf( 'Parameter "%s" must be at least %d characters.', $name, (int) $schema['minLength'] ), 400 );
		}
		if ( isset( $schema['maxLength'] ) && $length > (int) $schema['maxLength'] ) {
			throw new InvalidArgumentException( sprintf( 'Parameter "%s" must not exceed %d characters.', $name, (int) $schema['maxLength'] ), 400 );
		}
		if ( isset( $schema['pattern'] ) && is_string( $schema['pattern'] ) && ! self::pattern_matches( $schema['pattern'], $value ) ) {
			throw new InvalidArgumentException( sprintf( 'Parameter "%s" does not match the required format.', $name ), 400 );
		}
	}

	/**
	 * Validate one value against one schema node.
	 *
	 * @param mixed            $value Candidate value.
	 * @param array|bool|mixed $schema Schema node.
	 * @throws InvalidArgumentException When validation fails.
	 * @throws RuntimeException When the schema is malformed.
	 */
	private static function validate_node( $value, $schema, array $root, string $path, int $depth ): void {
		if ( $depth > self::MAX_SCHEMA_DEPTH ) {
			throw new RuntimeException( 'JSON Schema validation exceeded its depth limit.' );
		}

		if ( true === $schema ) {
			return;
		}
		if ( false === $schema ) {
			throw new InvalidArgumentException( 'is not allowed at ' . $path . '.' );
		}
		if ( ! is_array( $schema ) ) {
			throw new RuntimeException( 'JSON Schema nodes must be objects or booleans.' );
		}

		if ( isset( $schema['$ref'] ) ) {
			$resolved = self::resolve_reference( $root, $schema['$ref'] );
			self::validate_node( $value, $resolved, $root, $path, $depth + 1 );
		}
		if ( isset( $schema['$dynamicRef'] ) ) {
			$resolved = self::resolve_reference( $root, $schema['$dynamicRef'] );
			self::validate_node( $value, $resolved, $root, $path, $depth + 1 );
		}

		self::validate_composition( $value, $schema, $root, $path, $depth );

		if ( array_key_exists( 'const', $schema ) && $value !== $schema['const'] ) {
			throw new InvalidArgumentException( 'must equal its constant value at ' . $path . '.' );
		}
		if ( isset( $schema['enum'] ) && ( ! is_array( $schema['enum'] ) || ! in_array( $value, $schema['enum'], true ) ) ) {
			throw new InvalidArgumentException( 'is not one of the allowed values at ' . $path . '.' );
		}

		if ( isset( $schema['type'] ) && ! self::matches_type( $value, $schema['type'] ) ) {
			throw new InvalidArgumentException( 'has the wrong type at ' . $path . '.' );
		}

		if ( is_array( $value ) && self::schema_allows_type( $schema, 'object' ) && ( array() === $value || ! self::is_list( $value ) ) ) {
			self::validate_object( $value, $schema, $root, $path, $depth );
		}
		if ( is_array( $value ) && self::schema_allows_type( $schema, 'array' ) && ( array() === $value || self::is_list( $value ) ) ) {
			self::validate_array( $value, $schema, $root, $path, $depth );
		}
		if ( is_string( $value ) ) {
			self::validate_string( $value, $schema, $path );
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			self::validate_number( $value, $schema, $path );
		}
	}

	/**
	 * Validate all composition and conditional keywords.
	 *
	 * @param mixed $value Candidate value.
	 */
	private static function validate_composition( $value, array $schema, array $root, string $path, int $depth ): void {
		if ( isset( $schema['allOf'] ) ) {
			self::require_schema_list( $schema['allOf'], 'allOf' );
			foreach ( $schema['allOf'] as $child ) {
				self::validate_node( $value, $child, $root, $path, $depth + 1 );
			}
		}

		foreach ( array(
			'anyOf' => 1,
			'oneOf' => 1,
		) as $keyword => $minimum ) {
			if ( ! isset( $schema[ $keyword ] ) ) {
				continue;
			}
			self::require_schema_list( $schema[ $keyword ], $keyword );
			$matches = 0;
			foreach ( $schema[ $keyword ] as $child ) {
				if ( self::node_matches( $value, $child, $root, $path, $depth + 1 ) ) {
					++$matches;
				}
			}
			if ( $matches < $minimum || ( 'oneOf' === $keyword && 1 !== $matches ) ) {
				throw new InvalidArgumentException( 'does not satisfy ' . $keyword . ' at ' . $path . '.' );
			}
		}

		if ( isset( $schema['not'] ) && self::node_matches( $value, $schema['not'], $root, $path, $depth + 1 ) ) {
			throw new InvalidArgumentException( 'matches a forbidden schema at ' . $path . '.' );
		}

		if ( isset( $schema['if'] ) ) {
			$matches = self::node_matches( $value, $schema['if'], $root, $path, $depth + 1 );
			$branch  = $matches ? 'then' : 'else';
			if ( isset( $schema[ $branch ] ) ) {
				self::validate_node( $value, $schema[ $branch ], $root, $path, $depth + 1 );
			}
		}
	}

	/**
	 * Validate object keywords.
	 */
	private static function validate_object( array $value, array $schema, array $root, string $path, int $depth ): void {
		$count = count( $value );
		if ( isset( $schema['minProperties'] ) && $count < (int) $schema['minProperties'] ) {
			throw new InvalidArgumentException( 'has too few properties at ' . $path . '.' );
		}
		if ( isset( $schema['maxProperties'] ) && $count > (int) $schema['maxProperties'] ) {
			throw new InvalidArgumentException( 'has too many properties at ' . $path . '.' );
		}

		$required = $schema['required'] ?? array();
		if ( ! is_array( $required ) ) {
			throw new RuntimeException( 'required must be an array.' );
		}
		foreach ( $required as $name ) {
			if ( ! is_string( $name ) || ! array_key_exists( $name, $value ) ) {
				throw new InvalidArgumentException( 'is missing a required property at ' . $path . '.' );
			}
		}

		$properties         = isset( $schema['properties'] ) && is_array( $schema['properties'] ) ? $schema['properties'] : array();
		$pattern_properties = isset( $schema['patternProperties'] ) && is_array( $schema['patternProperties'] ) ? $schema['patternProperties'] : array();
		$evaluated          = array();

		foreach ( $value as $name => $child_value ) {
			$child_path = $path . '.' . (string) $name;
			if ( array_key_exists( $name, $properties ) ) {
				self::validate_node( $child_value, $properties[ $name ], $root, $child_path, $depth + 1 );
				$evaluated[ $name ] = true;
			}

			foreach ( $pattern_properties as $pattern => $child_schema ) {
				if ( self::pattern_matches( (string) $pattern, (string) $name ) ) {
					self::validate_node( $child_value, $child_schema, $root, $child_path, $depth + 1 );
					$evaluated[ $name ] = true;
				}
			}

			if ( isset( $schema['propertyNames'] ) ) {
				self::validate_node( (string) $name, $schema['propertyNames'], $root, $child_path, $depth + 1 );
			}
		}

		$additional = $schema['additionalProperties'] ?? true;
		foreach ( $value as $name => $child_value ) {
			if ( isset( $evaluated[ $name ] ) ) {
				continue;
			}
			if ( false === $additional ) {
				throw new InvalidArgumentException( 'contains an unknown property at ' . $path . '.' );
			}
			if ( is_array( $additional ) || is_bool( $additional ) ) {
				self::validate_node( $child_value, $additional, $root, $path . '.' . (string) $name, $depth + 1 );
			}
		}

		if ( isset( $schema['dependentRequired'] ) && is_array( $schema['dependentRequired'] ) ) {
			foreach ( $schema['dependentRequired'] as $name => $dependencies ) {
				if ( ! array_key_exists( $name, $value ) ) {
					continue;
				}
				if ( ! is_array( $dependencies ) ) {
					throw new RuntimeException( 'dependentRequired entries must be arrays.' );
				}
				foreach ( $dependencies as $dependency ) {
					if ( ! is_string( $dependency ) || ! array_key_exists( $dependency, $value ) ) {
						throw new InvalidArgumentException( 'is missing a dependent property at ' . $path . '.' );
					}
				}
			}
		}

		if ( isset( $schema['dependentSchemas'] ) && is_array( $schema['dependentSchemas'] ) ) {
			foreach ( $schema['dependentSchemas'] as $name => $dependent_schema ) {
				if ( array_key_exists( $name, $value ) ) {
					self::validate_node( $value, $dependent_schema, $root, $path, $depth + 1 );
				}
			}
		}
	}

	/**
	 * Validate array keywords.
	 */
	private static function validate_array( array $value, array $schema, array $root, string $path, int $depth ): void {
		$count = count( $value );
		if ( isset( $schema['minItems'] ) && $count < (int) $schema['minItems'] ) {
			throw new InvalidArgumentException( 'has too few items at ' . $path . '.' );
		}
		if ( isset( $schema['maxItems'] ) && $count > (int) $schema['maxItems'] ) {
			throw new InvalidArgumentException( 'has too many items at ' . $path . '.' );
		}
		if ( ! empty( $schema['uniqueItems'] ) ) {
			$encoded = array_map( 'wp_json_encode', $value );
			if ( count( $encoded ) !== count( array_unique( $encoded ) ) ) {
				throw new InvalidArgumentException( 'contains duplicate items at ' . $path . '.' );
			}
		}

		$dialect_uses_prefix_items = self::dialect_uses_prefix_items( $root );
		$prefix                    = array();
		$tuple                     = array();
		$items                     = $schema['items'] ?? true;

		if ( $dialect_uses_prefix_items ) {
			if ( isset( $schema['prefixItems'] ) ) {
				self::require_schema_list( $schema['prefixItems'], 'prefixItems' );
				$prefix = $schema['prefixItems'];
			}

			// Draft 2020-12 replaced array-valued `items` with `prefixItems`.
			// Rejecting the obsolete shape prevents a malformed modern schema from
			// being silently interpreted with Draft-07 tuple semantics.
			if ( is_array( $items ) && self::is_list( $items ) ) {
				throw new RuntimeException( 'items must be one schema in JSON Schema 2020-12.' );
			}
		} elseif ( is_array( $items ) && self::is_list( $items ) ) {
			// Draft-06, Draft-07 and 2019-09 express tuples as an array in `items`.
			// `prefixItems` is an unknown keyword in those dialects and has no effect.
			$tuple = $items;
		}

		foreach ( $value as $index => $child_value ) {
			if ( $dialect_uses_prefix_items ) {
				$child_schema = $prefix[ $index ] ?? $items;
			} elseif ( ! empty( $tuple ) ) {
				$child_schema = $tuple[ $index ] ?? ( $schema['additionalItems'] ?? true );
			} else {
				$child_schema = $items;
			}
			self::validate_node( $child_value, $child_schema, $root, $path . '[' . $index . ']', $depth + 1 );
		}

		if ( isset( $schema['contains'] ) ) {
			$matches = 0;
			foreach ( $value as $index => $child_value ) {
				if ( self::node_matches( $child_value, $schema['contains'], $root, $path . '[' . $index . ']', $depth + 1 ) ) {
					++$matches;
				}
			}
			$minimum = isset( $schema['minContains'] ) ? (int) $schema['minContains'] : 1;
			$maximum = isset( $schema['maxContains'] ) ? (int) $schema['maxContains'] : PHP_INT_MAX;
			if ( $matches < $minimum || $matches > $maximum ) {
				throw new InvalidArgumentException( 'does not satisfy contains at ' . $path . '.' );
			}
		}
	}

	/**
	 * Whether the root dialect uses the 2020-12 array keyword model.
	 *
	 * MCP tool schemas without an explicit declaration use the current supported
	 * dialect. Older declared dialects retain their own tuple semantics throughout
	 * the document, including nested schemas.
	 */
	private static function dialect_uses_prefix_items( array $root ): bool {
		$dialect = isset( $root['$schema'] ) && is_string( $root['$schema'] )
			? $root['$schema']
			: self::DEFAULT_DIALECT;

		return self::DEFAULT_DIALECT === $dialect;
	}

	/**
	 * Validate string assertion keywords.
	 */
	private static function validate_string( string $value, array $schema, string $path ): void {
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
		if ( isset( $schema['minLength'] ) && $length < (int) $schema['minLength'] ) {
			throw new InvalidArgumentException( 'is too short at ' . $path . '.' );
		}
		if ( isset( $schema['maxLength'] ) && $length > (int) $schema['maxLength'] ) {
			throw new InvalidArgumentException( 'is too long at ' . $path . '.' );
		}
		if ( isset( $schema['pattern'] ) && ( ! is_string( $schema['pattern'] ) || ! self::pattern_matches( $schema['pattern'], $value ) ) ) {
			throw new InvalidArgumentException( 'does not match the required pattern at ' . $path . '.' );
		}

		if ( ! isset( $schema['format'] ) || ! is_string( $schema['format'] ) ) {
			return;
		}

		$valid = true;
		switch ( $schema['format'] ) {
			case 'email':
				$valid = false !== filter_var( $value, FILTER_VALIDATE_EMAIL );
				break;
			case 'uri':
				$valid = false !== filter_var( $value, FILTER_VALIDATE_URL );
				break;
			case 'date':
				$valid = 1 === preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $parts )
					&& checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] );
				break;
			case 'date-time':
				$valid = false !== strtotime( $value );
				break;
		}

		if ( ! $valid ) {
			throw new InvalidArgumentException( 'has an invalid format at ' . $path . '.' );
		}
	}

	/**
	 * Validate numeric assertion keywords.
	 *
	 * @param int|float $value Candidate number.
	 */
	private static function validate_number( $value, array $schema, string $path ): void {
		if ( isset( $schema['minimum'] ) && $value < $schema['minimum'] ) {
			throw new InvalidArgumentException( 'is below the minimum at ' . $path . '.' );
		}
		if ( isset( $schema['maximum'] ) && $value > $schema['maximum'] ) {
			throw new InvalidArgumentException( 'is above the maximum at ' . $path . '.' );
		}
		if ( isset( $schema['exclusiveMinimum'] ) && $value <= $schema['exclusiveMinimum'] ) {
			throw new InvalidArgumentException( 'is below the exclusive minimum at ' . $path . '.' );
		}
		if ( isset( $schema['exclusiveMaximum'] ) && $value >= $schema['exclusiveMaximum'] ) {
			throw new InvalidArgumentException( 'is above the exclusive maximum at ' . $path . '.' );
		}
		if ( isset( $schema['multipleOf'] ) ) {
			$multiple = (float) $schema['multipleOf'];
			if ( $multiple <= 0 || abs( ( $value / $multiple ) - round( $value / $multiple ) ) > 1.0E-9 ) {
				throw new InvalidArgumentException( 'is not a valid multiple at ' . $path . '.' );
			}
		}
	}

	/**
	 * Test a child schema without leaking its validation error.
	 *
	 * @param mixed $value Candidate value.
	 * @param mixed $schema Child schema.
	 */
	private static function node_matches( $value, $schema, array $root, string $path, int $depth ): bool {
		try {
			self::validate_node( $value, $schema, $root, $path, $depth );
			return true;
		} catch ( InvalidArgumentException $error ) {
			return false;
		}
	}

	/**
	 * Resolve a local JSON Pointer or anchor.
	 *
	 * @param mixed $reference Reference value.
	 * @return array|bool
	 */
	private static function resolve_reference( array $root, $reference ) {
		if ( ! is_string( $reference ) || 0 !== strpos( $reference, '#' ) ) {
			throw new RuntimeException( 'External JSON Schema references are not supported.' );
		}

		if ( '#' === $reference ) {
			return $root;
		}

		$fragment = substr( $reference, 1 );
		if ( '' !== $fragment && '/' !== $fragment[0] ) {
			$resolved = self::find_anchor( $root, rawurldecode( $fragment ) );
			if ( null === $resolved ) {
				throw new RuntimeException( 'JSON Schema anchor could not be resolved.' );
			}
			return $resolved;
		}

		$current = $root;
		foreach ( explode( '/', ltrim( $fragment, '/' ) ) as $segment ) {
			$segment = str_replace( array( '~1', '~0' ), array( '/', '~' ), rawurldecode( $segment ) );
			if ( ! is_array( $current ) || ! array_key_exists( $segment, $current ) ) {
				throw new RuntimeException( 'JSON Schema reference could not be resolved.' );
			}
			$current = $current[ $segment ];
		}

		if ( ! is_array( $current ) && ! is_bool( $current ) ) {
			throw new RuntimeException( 'JSON Schema reference does not target a schema.' );
		}

		return $current;
	}

	/**
	 * Find a local $anchor or $dynamicAnchor recursively.
	 *
	 * @param mixed $node Schema node.
	 * @return array|bool|null
	 */
	private static function find_anchor( $node, string $anchor ) {
		if ( ! is_array( $node ) ) {
			return null;
		}
		if ( ( $node['$anchor'] ?? null ) === $anchor || ( $node['$dynamicAnchor'] ?? null ) === $anchor ) {
			return $node;
		}
		foreach ( $node as $child ) {
			$found = self::find_anchor( $child, $anchor );
			if ( null !== $found ) {
				return $found;
			}
		}

		return null;
	}

	/**
	 * Inspect a schema recursively without following references.
	 *
	 * @param mixed $node Schema node.
	 * @throws RuntimeException When the schema exceeds a bound or uses an external reference.
	 */
	private static function inspect_schema_node( $node, int $depth, int &$nodes ): void {
		++$nodes;
		if ( $depth > self::MAX_SCHEMA_DEPTH || $nodes > self::MAX_SCHEMA_NODES ) {
			throw new RuntimeException( 'MCP tool schema exceeds validation complexity limits.' );
		}
		if ( ! is_array( $node ) ) {
			return;
		}
		foreach ( $node as $key => $value ) {
			if (
				in_array( $key, array( '$ref', '$dynamicRef', '$recursiveRef' ), true )
				&& ( ! is_string( $value ) || 0 !== strpos( $value, '#' ) )
			) {
				throw new RuntimeException( 'External JSON Schema references are not supported.' );
			}
			self::inspect_schema_node( $value, $depth + 1, $nodes );
		}
	}

	/**
	 * Require a non-empty list of child schemas.
	 *
	 * @param mixed $value Candidate schema list.
	 */
	private static function require_schema_list( $value, string $keyword ): void {
		if ( ! is_array( $value ) || empty( $value ) || ! self::is_list( $value ) ) {
			throw new RuntimeException( $keyword . ' must be a non-empty schema array.' );
		}
	}

	/**
	 * Check a regular expression supplied by a server-owned schema.
	 */
	private static function pattern_matches( string $pattern, string $value ): bool {
		$result = @preg_match( '~(*LIMIT_MATCH=100000)(*LIMIT_DEPTH=1000)' . str_replace( '~', '\\~', $pattern ) . '~u', $value ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid schema expressions are converted to RuntimeException below.
		if ( false === $result ) {
			throw new RuntimeException( 'JSON Schema contains an invalid regular expression.' );
		}

		return 1 === $result;
	}

	/**
	 * Determine whether a value matches a JSON Schema type or type union.
	 *
	 * @param mixed        $value Candidate value.
	 * @param string|array $type Declared type.
	 */
	private static function matches_type( $value, $type ): bool {
		if ( is_array( $type ) ) {
			foreach ( $type as $candidate ) {
				if ( self::matches_type( $value, $candidate ) ) {
					return true;
				}
			}
			return false;
		}

		switch ( $type ) {
			case 'null':
				return null === $value;
			case 'boolean':
				return is_bool( $value );
			case 'object':
				return is_array( $value ) && ( array() === $value || ! self::is_list( $value ) );
			case 'array':
				return is_array( $value ) && ( array() === $value || self::is_list( $value ) );
			case 'number':
				return is_int( $value ) || is_float( $value );
			case 'integer':
				return is_int( $value );
			case 'string':
				return is_string( $value );
		}

		throw new RuntimeException( 'JSON Schema declares an unknown type.' );
	}

	/**
	 * Determine whether type-specific keywords should be evaluated.
	 */
	private static function schema_allows_type( array $schema, string $type ): bool {
		if ( ! isset( $schema['type'] ) ) {
			return true;
		}

		return is_array( $schema['type'] )
			? in_array( $type, $schema['type'], true )
			: $type === $schema['type'];
	}

	/**
	 * Determine whether a PHP array represents a JSON list.
	 */
	private static function is_list( array $value ): bool {
		return array() !== $value && array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
