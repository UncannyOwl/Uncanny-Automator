<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
/**
 * Security class for sanitizing AI-generated input.
 *
 * This is a helper for sanitizing AI-generated input, NOT a firewall.
 * Developers must still use WordPress' escaping and database APIs.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Security;

use InvalidArgumentException;
use WP_Error;

/**
 * Security helper for AI input sanitization.
 *
 * WARNING: This is NOT comprehensive protection. Use proper WordPress
 * escaping functions and prepared statements for actual security.
 */
final class Security {

	// Context flags for output escaping
	const HTML_OUTPUT     = 0x01;  // Use esc_html()
	const ATTR_OUTPUT     = 0x02;  // Use esc_attr()
	const HTML_POST       = 0x04;  // Use wp_kses_post()
	const URL_OUTPUT      = 0x08;  // Use esc_url_raw()
	const EMAIL_INPUT     = 0x10;  // Use sanitize_email()
	const FILE_PATH       = 0x20;  // Validate file paths
	const PRESERVE_RAW    = 0x40;  // Preserve raw data, escape later
	const STRICT_MODE     = 0x80;  // Throw exceptions on invalid data
	const REJECT_BAD_KEYS = 0x100; // Reject bad array keys instead of sanitizing

	// Security limits
	const MAX_STRING_LENGTH   = 65535;  // 64KB limit
	const MAX_ARRAY_ELEMENTS  = 1000;
	const MAX_RECURSION_DEPTH = 10;

	/**
	 * Sanitize data based on output context.
	 *
	 * @param mixed $data   Data to sanitize.
	 * @param int   $flags  Context flags.
	 * @return mixed Sanitized data preserving type.
	 * @throws \InvalidArgumentException In strict mode.
	 */
	public static function sanitize( $data, int $flags = 0 ) {
		return self::sanitize_recursive( $data, $flags, 0 );
	}

	/**
	 * Recursive sanitization with depth tracking.
	 *
	 * @param mixed $data  Data to sanitize.
	 * @param int   $flags Context flags.
	 * @param int   $depth Current recursion depth.
	 * @return mixed Sanitized data.
	 * @throws \InvalidArgumentException In strict mode.
	 */
	private static function sanitize_recursive( $data, int $flags, int $depth ) {
		// Prevent deep recursion
		if ( $depth > self::MAX_RECURSION_DEPTH ) {
			if ( $flags & self::STRICT_MODE ) {
				throw new InvalidArgumentException( 'Maximum recursion depth exceeded' );
			}
			return null;
		}

		// Handle arrays
		if ( is_array( $data ) ) {
			return self::sanitize_array( $data, $flags, $depth );
		}

		// Handle strings
		if ( is_string( $data ) ) {
			return self::sanitize_string( $data, $flags );
		}

		// Handle integers
		if ( is_int( $data ) || is_numeric( $data ) ) {
			return self::sanitize_numeric( $data, $flags );
		}

		// Handle booleans
		if ( is_bool( $data ) ) {
			return $data;
		}

		// Handle null
		if ( null === $data ) {
			return null;
		}

		// Reject objects, resources, and unknown types
		if ( $flags & self::STRICT_MODE ) {
			throw new InvalidArgumentException( 'Unsupported data type: ' . gettype( $data ) );
		}

		return null;
	}

	/**
	 * Sanitize array data.
	 *
	 * @param array $data  Array to sanitize.
	 * @param int   $flags Context flags.
	 * @param int   $depth Current depth.
	 * @return array Sanitized array.
	 */
	private static function sanitize_array( array $data, int $flags, int $depth ): array {
		// Check array size limit
		if ( count( $data ) > self::MAX_ARRAY_ELEMENTS ) {
			if ( $flags & self::STRICT_MODE ) {
				throw new InvalidArgumentException( 'Array too large' );
			}
			$data = array_slice( $data, 0, self::MAX_ARRAY_ELEMENTS );
		}

		$sanitized = array();
		foreach ( $data as $key => $value ) {
			// Handle array keys
			if ( is_string( $key ) ) {
				if ( $flags & self::REJECT_BAD_KEYS ) {
					// Reject keys that would be modified by sanitize_text_field
					$sanitized_key = sanitize_text_field( $key );
					if ( $sanitized_key !== $key ) {
						if ( $flags & self::STRICT_MODE ) {
							throw new InvalidArgumentException( 'Invalid array key: ' . $key );
						}
						// Skip this key-value pair
						continue;
					}
					$clean_key = $key;
				} else {
					// Sanitize key (default behavior)
					$clean_key = sanitize_text_field( $key );
				}
			} else {
				$clean_key = $key;
			}

			// Sanitize value recursively
			$clean_value = self::sanitize_recursive( $value, $flags, $depth + 1 );

			if ( null !== $clean_value ) {
				$sanitized[ $clean_key ] = $clean_value;
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize string data based on context.
	 *
	 * @param string $data  String to sanitize.
	 * @param int    $flags Context flags.
	 * @return string Sanitized string.
	 */
	private static function sanitize_string( string $data, int $flags ): string {
		// Check string length
		if ( strlen( $data ) > self::MAX_STRING_LENGTH ) {
			if ( $flags & self::STRICT_MODE ) {
				throw new InvalidArgumentException( 'String too long' );
			}
			$data = substr( $data, 0, self::MAX_STRING_LENGTH );
		}

		// Context-specific sanitization
		if ( $flags & self::HTML_OUTPUT ) {
			return esc_html( $data );
		}

		if ( $flags & self::ATTR_OUTPUT ) {
			return esc_attr( $data );
		}

		if ( $flags & self::HTML_POST ) {
			return wp_kses_post( $data );
		}

		if ( $flags & self::URL_OUTPUT ) {
			$clean = esc_url_raw( $data );
			if ( ! filter_var( $clean, FILTER_VALIDATE_URL ) ) {
				if ( $flags & self::STRICT_MODE ) {
					throw new InvalidArgumentException( 'Invalid URL' );
				}
				return '';
			}
			return $clean;
		}

		if ( $flags & self::EMAIL_INPUT ) {
			$clean = sanitize_email( $data );
			if ( ! is_email( $clean ) ) {
				if ( $flags & self::STRICT_MODE ) {
					throw new InvalidArgumentException( 'Invalid email' );
				}
				return '';
			}
			return $clean;
		}

		if ( $flags & self::FILE_PATH ) {
			return self::sanitize_file_path( $data, $flags );
		}

		if ( $flags & self::PRESERVE_RAW ) {
			// Just trim length, preserve raw data for later escaping
			if ( strlen( $data ) > self::MAX_STRING_LENGTH ) {
				// Use multibyte-safe truncation if available
				if ( function_exists( 'mb_substr' ) ) {
					return mb_substr( $data, 0, self::MAX_STRING_LENGTH, 'UTF-8' );
				}
				return substr( $data, 0, self::MAX_STRING_LENGTH );
			}
			return $data;
		}

		// Default: basic text sanitization
		return sanitize_text_field( $data );
	}

	/**
	 * Sanitize file paths with upload directory jail.
	 *
	 * Only allows files within WordPress uploads directory.
	 * Uses multi-pass decoding to prevent double-encoding bypasses.
	 *
	 * @param string $path  File path.
	 * @param int    $flags Context flags.
	 * @return string Safe file path.
	 */
	private static function sanitize_file_path( string $path, int $flags ): string {
		$uploads_dir = realpath( wp_upload_dir()['basedir'] );
		if ( false === $uploads_dir ) {
			if ( $flags & self::STRICT_MODE ) {
				throw new InvalidArgumentException( 'Uploads directory not accessible' );
			}
			return '';
		}

		// Try realpath first (for existing files)
		$real_path = realpath( $path );
		if ( false !== $real_path ) {
			// File exists, validate it's in uploads
			if ( 0 === strncmp( $real_path, $uploads_dir, strlen( $uploads_dir ) ) ) {
				return $real_path;
			}
			// Existing file outside uploads
			if ( $flags & self::STRICT_MODE ) {
				throw new InvalidArgumentException( 'File path outside uploads directory: ' . $path );
			}
			return '';
		}

		// File doesn't exist, validate intended path
		$normalized_path = self::normalize_upload_path( $path );
		if ( empty( $normalized_path ) ) {
			if ( $flags & self::STRICT_MODE ) {
				throw new InvalidArgumentException( 'Invalid file path: ' . $path );
			}
			return '';
		}

		// Check if intended path would be within uploads
		if ( 0 === strncmp( $normalized_path, $uploads_dir, strlen( $uploads_dir ) ) ) {
			return $normalized_path;
		}

		// Intended path outside uploads
		if ( $flags & self::STRICT_MODE ) {
			throw new InvalidArgumentException( 'Intended file path outside uploads directory: ' . $path );
		}

		return '';
	}

	/**
	 * Normalize intended file path for uploads directory.
	 *
	 * @param string $path Intended file path.
	 * @return string Normalized path or empty string if invalid.
	 */
	private static function normalize_upload_path( string $path ): string {
		// Multi-pass URL decoding to handle double-encoding bypasses
		$decoded_path = $path;
		for ( $i = 0; $i < 3; $i++ ) {
			$new_path = rawurldecode( $decoded_path );
			if ( $new_path === $decoded_path ) {
				break; // No more changes
			}
			$decoded_path = $new_path;
		}

		// Normalize directory separators (Windows compatibility)
		$decoded_path = str_replace( '\\', '/', $decoded_path );

		// Remove path traversal sequences (multiple passes)
		do {
			$clean_path   = $decoded_path;
			$decoded_path = str_replace(
				array( '../', '..%2f', '..%2F', '..%5c', '..%5C' ),
				'',
				$decoded_path
			);
		} while ( $clean_path !== $decoded_path );

		// Get directory and filename
		$dirname  = dirname( $decoded_path );
		$basename = basename( $decoded_path );

		// Validate directory exists
		$real_dirname = realpath( $dirname );
		if ( false === $real_dirname ) {
			return '';
		}

		// Validate filename
		if ( empty( $basename ) || '.' === $basename || '..' === $basename ) {
			return '';
		}

		// Reject null bytes and control characters
		if ( false !== strpos( $basename, "\0" ) || preg_match( '/[\x00-\x1f\x7f]/', $basename ) ) {
			return '';
		}

		return $real_dirname . DIRECTORY_SEPARATOR . $basename;
	}

	/**
	 * Sanitize numeric data.
	 *
	 * @param mixed $data  Numeric data.
	 * @param int   $flags Context flags.
	 * @return int|float|null Sanitized number or null if invalid.
	 */
	private static function sanitize_numeric( $data, int $flags ) {
		// Try integer first
		$int_val = filter_var( $data, FILTER_VALIDATE_INT );
		if ( false !== $int_val ) {
			return $int_val;
		}

		// Try float
		$float_val = filter_var( $data, FILTER_VALIDATE_FLOAT );
		if ( false !== $float_val ) {
			return $float_val;
		}

		// Invalid numeric data
		if ( $flags & self::STRICT_MODE ) {
			throw new InvalidArgumentException( 'Invalid numeric data: ' . $data );
		}

		return null;
	}

	/**
	 * Prepare SQL safely (wrapper for $wpdb->prepare).
	 *
	 * @param string $query SQL query with placeholders.
	 * @param mixed  ...$args Values for placeholders.
	 * @return string Prepared SQL.
	 */
	public static function prepare_sql( string $query, ...$args ): string {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- False positive: $query is the template string for prepare()
		return $wpdb->prepare( $query, ...$args );
	}

	/**
	 * Log security event (opt-in only).
	 *
	 * Only logs if 'automator_security_event' action has handlers.
	 * Never spams error_log() by default.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public static function log_security_event( string $message, array $context = array() ): void {
		// Only log if someone is listening
		if ( ! has_action( 'automator_security_event' ) ) {
			return;
		}

		$log_data = array(
			'message'   => $message,
			'timestamp' => time(),
			'context'   => $context,
		);

		do_action( 'automator_security_event', $log_data );
	}

	/**
	 * Validate data against explicit schema structure.
	 *
	 * Schema format:
	 * array(
	 *   'field_name' => array(
	 *     'type' => 'string|int|float|bool|array|email',
	 *     'required' => true,        // default: true
	 *     'allow_empty' => false,    // default: false
	 *     'exact_keys' => true,      // reject extra keys (default: false)
	 *     'union_types' => array('string', 'int'), // allow multiple types
	 *     'pattern' => '/^[a-z]+$/', // regex validation for strings
	 *     'min_length' => 1,         // string constraints
	 *     'max_length' => 100,
	 *     'min' => 0,                // numeric constraints
	 *     'max' => 999,
	 *     'element_type' => 'string', // for arrays
	 *     'schema' => array(...)      // nested validation
	 *   )
	 * )
	 *
	 * @param mixed $data   Data to validate.
	 * @param array $schema Schema definition.
	 * @return bool True if valid.
	 */
	/**
	 * Validate schema.
	 *
	 * @param mixed $data The data.
	 * @param array $schema The schema.
	 * @return bool
	 */
	public static function validate_schema( $data, array $schema ): bool {
		if ( ! is_array( $data ) ) {
			return false;
		}

		// Check for exact key enforcement
		$exact_keys = false;
		if ( isset( $schema['_config']['exact_keys'] ) ) {
			$exact_keys = $schema['_config']['exact_keys'];
			unset( $schema['_config'] ); // Remove config from validation
		}

		// Reject extra keys if exact_keys is enabled
		if ( $exact_keys ) {
			$allowed_keys = array_keys( $schema );
			$data_keys    = array_keys( $data );
			$extra_keys   = array_diff( $data_keys, $allowed_keys );
			if ( ! empty( $extra_keys ) ) {
				return false; // Extra keys found
			}
		}

		foreach ( $schema as $key => $field_schema ) {
			$required = $field_schema['required'] ?? true;

			if ( ! array_key_exists( $key, $data ) ) {
				if ( $required ) {
					return false; // Required field missing
				}
				continue;
			}

			$value = $data[ $key ];

			// Check empty values
			$allow_empty = $field_schema['allow_empty'] ?? false;
			if ( ! $allow_empty && self::is_empty_value( $value ) ) {
				return false;
			}

			// Validate type (including union types)
			if ( ! self::validate_field_type( $value, $field_schema ) ) {
				return false;
			}

			// String-specific validations
			if ( is_string( $value ) ) {
				if ( ! self::validate_string_constraints( $value, $field_schema ) ) {
					return false;
				}
			}

			// Numeric validations
			if ( is_numeric( $value ) ) {
				if ( ! self::validate_numeric_constraints( $value, $field_schema ) ) {
					return false;
				}
			}

			// Array validations
			if ( is_array( $value ) ) {
				if ( ! self::validate_array_constraints( $value, $field_schema ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Check if value is considered empty.
	 *
	 * @param mixed $value Value to check.
	 * @return bool True if empty.
	 */
	private static function is_empty_value( $value ): bool {
		if ( null === $value ) {
			return true;
		}
		if ( is_string( $value ) && '' === $value ) {
			return true;
		}
		if ( is_array( $value ) && empty( $value ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Validate field type including union types.
	 *
	 * @param mixed $value Value to validate.
	 * @param array $field_schema Field schema.
	 * @return bool True if valid.
	 */
	private static function validate_field_type( $value, array $field_schema ): bool {
		// Check union types first
		if ( isset( $field_schema['union_types'] ) ) {
			foreach ( $field_schema['union_types'] as $type ) {
				if ( self::validate_single_type( $value, $type ) ) {
					return true; // Matches one of the union types
				}
			}
			return false; // Doesn't match any union type
		}

		// Check single type
		$type = $field_schema['type'] ?? 'mixed';
		return self::validate_single_type( $value, $type );
	}

	/**
	 * Validate single type.
	 *
	 * @param mixed  $value Value to validate.
	 * @param string $type  Expected type.
	 * @return bool True if valid.
	 */
	private static function validate_single_type( $value, string $type ): bool {
		// Type validation mapping for cleaner logic
		$type_validators = array(
			'string'  => 'is_string',
			'int'     => 'is_int',
			'integer' => 'is_int',
			'float'   => 'is_float',
			'bool'    => 'is_bool',
			'boolean' => 'is_bool',
			'array'   => 'is_array',
			'mixed'   => '__return_true',
		);

		// Handle email as special case (requires both string check and email validation)
		if ( 'email' === $type ) {
			return is_string( $value ) && is_email( $value );
		}

		// Use mapped validator function if available
		if ( isset( $type_validators[ $type ] ) ) {
			$validator = $type_validators[ $type ];
			return $validator( $value );
		}

		// Unknown type
		return false;
	}

	/**
	 * Validate string constraints.
	 *
	 * @param string $value String to validate.
	 * @param array  $field_schema Field schema.
	 * @return bool True if valid.
	 */
	private static function validate_string_constraints( string $value, array $field_schema ): bool {
		// Length constraints
		if ( isset( $field_schema['min_length'] ) && strlen( $value ) < $field_schema['min_length'] ) {
			return false;
		}
		if ( isset( $field_schema['max_length'] ) && strlen( $value ) > $field_schema['max_length'] ) {
			return false;
		}

		// Pattern validation
		if ( isset( $field_schema['pattern'] ) ) {
			if ( ! preg_match( $field_schema['pattern'], $value ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate numeric constraints.
	 *
	 * @param int|float $value Numeric value.
	 * @param array     $field_schema Field schema.
	 * @return bool True if valid.
	 */
	private static function validate_numeric_constraints( $value, array $field_schema ): bool {
		if ( isset( $field_schema['min'] ) && $value < $field_schema['min'] ) {
			return false;
		}
		if ( isset( $field_schema['max'] ) && $value > $field_schema['max'] ) {
			return false;
		}
		return true;
	}

	/**
	 * Validate array constraints.
	 *
	 * @param array $value Array to validate.
	 * @param array $field_schema Field schema.
	 * @return bool True if valid.
	 */
	private static function validate_array_constraints( array $value, array $field_schema ): bool {
		// Element type validation
		if ( isset( $field_schema['element_type'] ) ) {
			foreach ( $value as $element ) {
				if ( ! self::validate_single_type( $element, $field_schema['element_type'] ) ) {
					return false;
				}
			}
		}

		// Nested schema validation
		if ( isset( $field_schema['schema'] ) ) {
			foreach ( $value as $element ) {
				if ( ! self::validate_schema( $element, $field_schema['schema'] ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Ban all unserialization - use JSON instead.
	 *
	 * @param string $serialized_data Serialized data.
	 * @throws \InvalidArgumentException Always.
	 */
	public static function unserialize( string $serialized_data ) {
		throw new InvalidArgumentException(
			'Unserialization of untrusted data is forbidden. Use json_decode() instead.'
		);
	}

	/**
	 * Safe JSON decode with depth limits and exception handling.
	 *
	 * @param string $json JSON string.
	 * @param int    $flags JSON decode flags.
	 * @return mixed Decoded data or WP_Error on failure.
	 */
	public static function json_decode( string $json, int $flags = 0 ) {
		try {
			return json_decode( $json, true, 10, $flags | JSON_THROW_ON_ERROR );
		} catch ( \JsonException $e ) {
			return new WP_Error(
				'json_decode_error',
				'JSON decode failed: ' . $e->getMessage()
			);
		} catch ( \Exception $e ) {
			return new WP_Error(
				'json_decode_exception',
				'JSON decode exception: ' . $e->getMessage()
			);
		}
	}
}
