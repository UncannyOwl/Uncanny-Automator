<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Field\Enums;

/**
 * Field Types Enum.
 *
 * Represents the type of field in a recipe item.
 *
 * @since 7.0
 */
class Field_Types {

	/**
	 * Text field type.
	 *
	 * @var string
	 */
	const TEXT = 'text';

	/**
	 * Textarea field type.
	 *
	 * @var string
	 */
	const TEXTAREA = 'textarea';

	/**
	 * Select field type.
	 *
	 * @var string
	 */
	const SELECT = 'select';

	/**
	 * URL field type.
	 *
	 * @var string
	 */
	const URL = 'url';

	/**
	 * Email field type.
	 *
	 * @var string
	 */
	const EMAIL = 'email';

	/**
	 * Integer field type.
	 *
	 * @var string
	 */
	const INTEGER = 'integer';

	/**
	 * Float field type.
	 *
	 * @var string
	 */
	const FLOAT = 'float';

	/**
	 * Checkbox field type.
	 *
	 * @var string
	 */
	const CHECKBOX = 'checkbox';

	/**
	 * Radio field type.
	 *
	 * @var string
	 */
	const RADIO = 'radio';

	/**
	 * Date field type.
	 *
	 * @var string
	 */
	const DATE = 'date';

	/**
	 * Time field type.
	 *
	 * @var string
	 */
	const TIME = 'time';

	/**
	 * Repeater field type.
	 *
	 * @var string
	 */
	const REPEATER = 'repeater';

	/**
	 * File field type.
	 *
	 * @var string
	 */
	const FILE = 'file';

	/**
	 * Markdown field type.
	 *
	 * Internal-only type resolved from integration definition (supports_markdown).
	 * Not accepted from REST requests - use TEXTAREA instead.
	 *
	 * @var string
	 */
	const MARKDOWN = 'markdown';

	/**
	 * HTML field type.
	 *
	 * Internal-only type resolved from integration definition (supports_tinymce).
	 * Not accepted from REST requests - use TEXTAREA instead.
	 *
	 * @var string
	 */
	const HTML = 'html';

	/**
	 * Wire aliases accepted for canonical types. The FE FieldTypeSchema
	 * enum (and the integration catalog it mirrors) says 'int'; this enum's
	 * canonical value is 'integer'. FieldsOutput saves echo the catalog
	 * value back, so the boundary must accept the alias.
	 *
	 * @var array<string, string>
	 */
	const ALIASES = array(
		'int' => self::INTEGER,
	);

	/**
	 * Resolve a wire alias to its canonical type value.
	 *
	 * Unknown values pass through unchanged — validation stays the caller's
	 * concern.
	 *
	 * @param string $value Raw field type value.
	 * @return string Canonical field type value.
	 */
	public static function normalize( string $value ): string {
		return self::ALIASES[ $value ] ?? $value;
	}

	/**
	 * Validate field type value.
	 *
	 * @param string $value Field type value to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public static function is_valid( string $value ): bool {
		return in_array( $value, self::get_all(), true );
	}

	/**
	 * Get all valid field type values (including internal types).
	 *
	 * @return array<string> Array of valid field type values.
	 */
	public static function get_all(): array {
		return array(
			self::TEXT,
			self::TEXTAREA,
			self::SELECT,
			self::URL,
			self::EMAIL,
			self::INTEGER,
			self::FLOAT,
			self::CHECKBOX,
			self::RADIO,
			self::DATE,
			self::TIME,
			self::REPEATER,
			self::FILE,
			self::MARKDOWN,
			self::HTML,
		);
	}

	/**
	 * Get field types that can be received from REST requests.
	 *
	 * Excludes internal types like MARKDOWN and HTML which are
	 * resolved from integration definitions, not passed by frontend.
	 *
	 * @return array<string> Array of REST-valid field type values.
	 */
	public static function get_rest_valid(): array {
		return array(
			self::TEXT,
			self::TEXTAREA,
			self::SELECT,
			self::URL,
			self::EMAIL,
			self::INTEGER,
			self::FLOAT,
			self::CHECKBOX,
			self::RADIO,
			self::DATE,
			self::TIME,
			self::REPEATER,
			self::FILE,
		);
	}

	/**
	 * Validate if a type can be received from REST requests.
	 *
	 * @param string $value Field type value to validate.
	 * @return bool True if valid for REST, false otherwise.
	 */
	public static function is_valid_rest( string $value ): bool {
		return in_array( $value, self::get_rest_valid(), true );
	}

	/**
	 * Check if a type is internal-only (not from REST).
	 *
	 * Internal types are resolved from integration definitions
	 * (supports_markdown, supports_tinymce) and should not be
	 * sent directly from the frontend.
	 *
	 * @param string $value Field type value to check.
	 * @return bool True if internal-only type.
	 */
	public static function is_internal( string $value ): bool {
		return in_array( $value, array( self::MARKDOWN, self::HTML ), true );
	}

	/**
	 * Resolve a wire-input type to its internal-only type from the field's
	 * AUTHORITATIVE definition.
	 *
	 * A `textarea` becomes `html` when the definition declares
	 * `supports_tinymce`, or `markdown` when it declares `supports_markdown`
	 * (tinymce wins if both). Every other type — and a plain textarea — is
	 * returned unchanged. Only `textarea` is eligible, so a text/email/url
	 * field can never be promoted into the sanitizer's raw-passthrough
	 * html/markdown branch.
	 *
	 * The definition MUST come from the registry, never from request-supplied
	 * data: html/markdown route to raw pass-through in Field_Sanitizer, so
	 * trusting a client-sent flag would let any field bypass sanitization.
	 *
	 * @param string $wire_type  The request-supplied field type (e.g. 'textarea').
	 * @param array  $definition The authoritative field definition.
	 * @return string The resolved (possibly internal) field type.
	 */
	public static function resolve_internal_type( string $wire_type, array $definition ): string {
		if ( self::TEXTAREA !== $wire_type ) {
			return $wire_type;
		}
		if ( ! empty( $definition['supports_tinymce'] ) ) {
			return self::HTML;
		}
		if ( ! empty( $definition['supports_markdown'] ) ) {
			return self::MARKDOWN;
		}
		return $wire_type;
	}
}
