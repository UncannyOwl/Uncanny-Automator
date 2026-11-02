<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools;

/**
 * Extract and validate protocol-defined x-mcp-header annotations.
 */
final class Tool_Header_Map {

	private const MAX_SAFE_INTEGER = 9007199254740991;

	/**
	 * Build a header map keyed by the declared case-preserving suffix.
	 *
	 * @return array<string,array{path:string[],type:string}>
	 * @throws \RuntimeException When any annotation makes the tool invalid.
	 */
	public function extract( array $input_schema ): array {
		$map       = array();
		$collected = 0;
		$this->collect_properties( $input_schema, array(), $map, $collected );

		$found = $this->count_annotations( $input_schema );
		if ( $found !== $collected ) {
			throw new \RuntimeException( 'x-mcp-header is only valid on statically reachable properties.' );
		}

		return $map;
	}

	/**
	 * Return the largest integer that can be mirrored safely through JavaScript.
	 */
	public static function get_max_safe_integer(): int {
		return self::MAX_SAFE_INTEGER;
	}

	/**
	 * Collect annotations reachable through properties keys only.
	 *
	 * @param string[] $path Current property path.
	 * @param array<string,array{path:string[],type:string}> $map Header map.
	 */
	private function collect_properties( array $schema, array $path, array &$map, int &$collected ): void {
		$properties = $schema['properties'] ?? array();
		if ( ! is_array( $properties ) ) {
			return;
		}

		foreach ( $properties as $property_name => $property_schema ) {
			if ( ! is_string( $property_name ) || ! is_array( $property_schema ) ) {
				continue;
			}

			$property_path = array_merge( $path, array( $property_name ) );
			if ( array_key_exists( 'x-mcp-header', $property_schema ) ) {
				++$collected;
				$name = $property_schema['x-mcp-header'];
				$type = $property_schema['type'] ?? null;

				if ( ! is_string( $name ) || '' === $name || 1 !== preg_match( "/^[!#$%&'*+\\-.^_`|~0-9A-Za-z]+$/D", $name ) ) {
					throw new \RuntimeException( 'x-mcp-header must be a valid HTTP field-name token.' );
				}
				if ( ! in_array( $type, array( 'string', 'integer', 'boolean' ), true ) ) {
					throw new \RuntimeException( 'x-mcp-header requires a string, integer, or boolean property.' );
				}

				$lookup = strtolower( $name );
				foreach ( array_keys( $map ) as $existing ) {
					if ( strtolower( $existing ) === $lookup ) {
						throw new \RuntimeException( 'x-mcp-header names must be case-insensitively unique.' );
					}
				}

				$map[ $name ] = array(
					'path' => $property_path,
					'type' => $type,
				);
			}

			$this->collect_properties( $property_schema, $property_path, $map, $collected );
		}
	}

	/**
	 * Count annotations anywhere, including invalid schema locations.
	 *
	 * @param mixed $node Schema node.
	 */
	private function count_annotations( $node ): int {
		if ( ! is_array( $node ) ) {
			return 0;
		}

		$count = array_key_exists( 'x-mcp-header', $node ) ? 1 : 0;
		foreach ( $node as $child ) {
			$count += $this->count_annotations( $child );
		}

		return $count;
	}
}
