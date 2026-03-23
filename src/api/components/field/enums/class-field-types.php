<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Field\Enums;

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
}
