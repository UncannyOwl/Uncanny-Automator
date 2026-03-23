<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Field;

use Uncanny_Automator\Api\Components\Field\Field;
use Uncanny_Automator\Api\Components\Field\Enums\Field_Types;

/**
 * Field Sanitizer Service.
 *
 * Handles WordPress-specific sanitization for field values.
 * Separates WordPress dependencies from pure domain logic.
 *
 * Field type transformation (textarea → markdown/html) is handled
 * at the transport layer. This sanitizer receives pre-resolved types
 * and applies the automator_field_type filter for all transports.
 *
 * @since 7.0
 */
class Field_Sanitizer {

	/**
	 * Sanitize field value based on type.
	 *
	 * Applies appropriate WordPress sanitization based on the field type.
	 * Token handling is type-specific: some sanitizers preserve tokens
	 * (like sanitize_text_field), while others would destroy them
	 * (like sanitize_email, esc_url_raw, intval).
	 *
	 * Field type transformation (textarea → markdown/html) is expected to be
	 * done before calling this method. The automator_field_type filter allows
	 * overriding the field type for all transports.
	 *
	 * @param Field  $field     Field component.
	 * @param string $transport Transport identifier (e.g., 'rest', 'mcp').
	 *
	 * @return mixed Sanitized value.
	 */
	public function sanitize_value( Field $field, string $transport ) {
		$field_value = $field->get_value();
		$value       = $field_value->get_value();
		$has_tokens  = $field_value->contains_automator_token();

		$field_type = $field->get_type();
		$type_value = $field_type->get_value();

		$field_code = $field->get_code();

		/**
		 * Filters the field type before sanitization.
		 *
		 * Allows overriding the field type for all transports.
		 * Field type transformation at the transport layer (e.g., REST)
		 * should be done before fields reach the sanitizer.
		 *
		 * @since 7.0
		 *
		 * @param string $type       The field type (e.g., 'text', 'textarea', 'markdown', 'html').
		 * @param string $field_code The field code.
		 * @param Field  $field      The Field object.
		 * @param string $transport  The transport identifier ('rest', 'mcp').
		 */
		$type_value = apply_filters(
			'automator_field_type',
			$type_value,
			$field_code,
			$field,
			$transport
		);

		/**
		 * Filters the field type for a specific field code.
		 *
		 * Dynamic hook that allows field-code-specific type overrides.
		 * Follows WordPress pattern (e.g., pre_update_option_{$option}).
		 *
		 * @since 7.0
		 *
		 * @param string $type      The field type (e.g., 'text', 'textarea', 'markdown', 'html').
		 * @param Field  $field     The Field object.
		 * @param string $transport The transport identifier ('rest', 'mcp').
		 */
		$type_value = apply_filters(
			"automator_field_type_{$field_code}",
			$type_value,
			$field,
			$transport
		);

		$sanitized = $this->sanitize_by_type( $value, $type_value, $has_tokens );

		/**
		 * Filters the sanitized field value before it's processed.
		 *
		 * Allows third-party plugins and internal code to customize
		 * sanitization logic for specific transports or use cases.
		 *
		 * @since 7.0
		 *
		 * @param mixed  $sanitized  The sanitized value.
		 * @param mixed  $original   The original value before sanitization.
		 * @param string $type       The resolved field type (may be 'markdown' or 'html').
		 * @param string $field_code The field code (meta key).
		 * @param string $transport  The transport identifier (e.g., 'rest', 'mcp').
		 * @param Field  $field      The field object (for advanced use).
		 */
		$sanitized = apply_filters(
			'automator_field_value_sanitized',
			$sanitized,
			$value,
			$type_value,
			$field_code,
			$transport,
			$field
		);

		/**
		 * Filters the sanitized value for a specific field code.
		 *
		 * Dynamic hook that allows field-code-specific value modifications.
		 * Follows WordPress pattern (e.g., pre_update_option_{$option}).
		 *
		 * @since 7.0
		 *
		 * @param mixed  $sanitized The sanitized value.
		 * @param mixed  $original  The original value before sanitization.
		 * @param string $type      The resolved field type.
		 * @param string $transport The transport identifier (e.g., 'rest', 'mcp').
		 * @param Field  $field     The field object (for advanced use).
		 */
		return apply_filters(
			"automator_field_value_sanitized_{$field_code}",
			$sanitized,
			$value,
			$type_value,
			$transport,
			$field
		);
	}

	/**
	 * Process field value for config output.
	 *
	 * Handles repeater encoding, JSON slashing, and applies WordPress filters.
	 *
	 * @param Field  $field     Field component.
	 * @param string $transport Transport identifier (e.g., 'rest', 'mcp').
	 *
	 * @return mixed Processed value ready for config.
	 */
	public function process_value( Field $field, string $transport ) {
		$value = $this->sanitize_value( $field, $transport );

		$field_type = $field->get_type();
		$type_value = $field_type->get_value();

		// Repeater fields need JSON encoding if they're arrays.
		if ( Field_Types::REPEATER === $type_value && is_array( $value ) ) {
			$value = wp_json_encode( $value );
		}

		// Slash JSON values for proper database storage.
		// update_post_meta() calls wp_unslash(), so we need to pre-slash JSON.
		if ( is_string( $value ) && $this->is_json_string( $value ) ) {
			$value = wp_slash( $value );
		}

		$field_code = $field->get_code();

		/**
		 * Filters the processed field value before it's added to config.
		 *
		 * Allows third-party plugins and internal code to customize
		 * value processing for specific transports or use cases.
		 * Useful for populating values from WordPress database or
		 * applying custom transformations.
		 *
		 * @since 7.0
		 *
		 * @param mixed  $value      The processed value.
		 * @param string $type       The field type.
		 * @param string $transport  The transport identifier (e.g., 'rest', 'mcp').
		 * @param Field  $field      The field object.
		 * @param string $field_code The field code.
		 */
		$value = apply_filters(
			'automator_field_value_processed',
			$value,
			$type_value,
			$transport,
			$field,
			$field_code
		);

		/**
		 * Filters the processed value for a specific field code.
		 *
		 * Dynamic hook that allows field-code-specific processing modifications.
		 * Follows WordPress pattern (e.g., pre_update_option_{$option}).
		 *
		 * @since 7.0
		 *
		 * @param mixed  $value     The processed value.
		 * @param string $type      The field type.
		 * @param string $transport The transport identifier (e.g., 'rest', 'mcp').
		 * @param Field  $field     The field object.
		 */
		return apply_filters(
			"automator_field_value_processed_{$field_code}",
			$value,
			$type_value,
			$transport,
			$field
		);
	}

	/**
	 * Check if a string is valid JSON.
	 *
	 * @param string $input The input to check.
	 *
	 * @return bool True if valid JSON string, false otherwise.
	 */
	private function is_json_string( string $input ): bool {
		json_decode( $input, true );
		return JSON_ERROR_NONE === json_last_error();
	}

	/**
	 * Sanitize value based on field type.
	 *
	 * Token handling is type-specific:
	 * - Types where sanitizer would DESTROY tokens (email, url, int, float, checkbox/radio):
	 *   Apply safe sanitization that strips HTML but preserves tokens.
	 * - Types where sanitizer is SAFE with tokens (text, textarea):
	 *   Always sanitize - sanitize_text_field() preserves {{token}} patterns.
	 * - Types that return as-is (html, markdown, select, repeater arrays):
	 *   No sanitization applied.
	 *
	 * @param mixed  $value      The value to sanitize.
	 * @param string $field_type The field type.
	 * @param bool   $has_tokens Whether the value contains Automator tokens.
	 *
	 * @return mixed Sanitized value.
	 */
	private function sanitize_by_type( $value, string $field_type, bool $has_tokens = false ) {
		switch ( $field_type ) {
			// Internal types - preserve content without sanitization.
			case Field_Types::MARKDOWN:
			case Field_Types::HTML:
			case Field_Types::SELECT:
				return $value;

			// Types where format-specific sanitizer would DESTROY tokens.
			// Apply safe sanitization: strip HTML tags, then sanitize_text_field.
			case Field_Types::EMAIL:
				return $has_tokens ? $this->sanitize_with_tokens( $value ) : sanitize_email( $value );

			case Field_Types::URL:
				return $has_tokens ? $this->sanitize_with_tokens( $value ) : esc_url_raw( $value );

			case Field_Types::INTEGER:
				return $has_tokens ? $this->sanitize_with_tokens( $value ) : intval( $value );

			case Field_Types::FLOAT:
				return $has_tokens ? $this->sanitize_with_tokens( $value ) : floatval( $value );

			case Field_Types::CHECKBOX:
			case Field_Types::RADIO:
				return $has_tokens ? $this->sanitize_with_tokens( $value ) : sanitize_key( $value );

			// Types where sanitizer is SAFE with tokens - always sanitize.
			case Field_Types::TEXTAREA:
				return is_string( $value ) ? sanitize_textarea_field( $value ) : $value;

			case Field_Types::REPEATER:
				// Repeater arrays are returned as-is.
				// String values get safe sanitization if tokens present.
				if ( is_string( $value ) ) {
					return $has_tokens ? $this->sanitize_with_tokens( $value ) : sanitize_text_field( $value );
				}
				return $value;

			case Field_Types::TEXT:
			case Field_Types::DATE:
			case Field_Types::TIME:
			case Field_Types::FILE:
			default:
				return is_string( $value ) ? sanitize_text_field( $value ) : $value;
		}
	}

	/**
	 * Safely sanitize a value that contains Automator tokens.
	 *
	 * Follows the legacy mixed-type fallback logic:
	 * - Strip HTML tags (prevents XSS)
	 * - Apply sanitize_text_field if no tags were present
	 *
	 * This preserves {{token}} patterns while removing dangerous content.
	 *
	 * @param mixed $value The value to sanitize.
	 *
	 * @return mixed Sanitized value.
	 */
	private function sanitize_with_tokens( $value ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}

		$stripped = wp_strip_all_tags( $value );

		// If stripping tags resulted in same string, apply text sanitization.
		// This matches the legacy mixed-type fallback behavior.
		if ( $stripped === $value ) {
			return sanitize_text_field( $value );
		}

		return $stripped;
	}
}
