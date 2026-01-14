<?php
namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol;

use Exception;
use InvalidArgumentException;

/**
 * Schema Validator for MCP tools.
 *
 * Prevents tools confabulation by hard failing on unknown parameters.
 *
 * @since 7.0.0
 */
class Schema_Validator {

	/**
	 * Validate params against schema. Hard fail on unknown params.
	 *
	 * @param array $params Input parameters.
	 * @param array $schema Tool schema with 'properties' key.
	 * @return true|\WP_Error
	 */
	public static function validate_mcp_params( array $params, array $schema ) {

		$allowed = array_keys( (array) $schema['properties'] ?? array() );
		$params  = array_keys( (array) $params );

		$both_empty = empty( $allowed ) && empty( $params );

		// If schema has no properties but params are provided, throw error.
		if ( empty( $allowed ) && ! $both_empty ) {
			throw new Exception( 'Schema properties are not defined properly. Schema expects no parameters but some were provided.', 400 );
		}

		$unknown = array_diff( $params, $allowed );

		if ( ! empty( $unknown ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Unknown parameter(s): %s', esc_html( implode( ', ', $unknown ) ) ),
				400,
			);
		}

		return true;
	}
}
