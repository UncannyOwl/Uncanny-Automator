<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Field\Services;

use Uncanny_Automator\App\Recipe_Builder\Field\Enums\Field_Types;
use Uncanny_Automator\App\Recipe_Builder\Field\Field;
use Uncanny_Automator\App\Recipe_Builder\Field\Field_Transformer;
use Uncanny_Automator\App\Recipe_Builder\Field\Value_Objects\Flattened_Field_Config;

/**
 * Field Service.
 *
 * Service layer for working with Field components and DTOs.
 * Provides methods to transform fields and work with field configurations.
 *
 * @since 7.0
 */
class Field_Service {

	/**
	 * Singleton instance.
	 *
	 * @var Field_Service|null
	 */
	private static $instance = null;

	/**
	 * Field transformer.
	 *
	 * @var Field_Transformer
	 */
	private Field_Transformer $transformer;

	/**
	 * Constructor.
	 *
	 * @param Field_Transformer|null $transformer Optional transformer instance.
	 */
	public function __construct( ?Field_Transformer $transformer = null ) {
		$this->transformer = $transformer ?? new Field_Transformer();
	}

	/**
	 * Get singleton instance.
	 *
	 * @return Field_Service
	 */
	public static function instance(): Field_Service {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Create Field from array data.
	 *
	 * @param string $code Field code.
	 * @param array  $data Field data array.
	 * @return Field
	 */
	public function create_field( string $code, array $data ): Field {
		return Field::from_array( $code, $data );
	}

	/**
	 * Create multiple Fields from structured fields array.
	 *
	 * @param array<string, array> $fields Structured fields array.
	 * @return array<string, Field> Array of Field components keyed by field code.
	 */
	public function create_fields( array $fields ): array {
		$field_objects = array();

		foreach ( $fields as $field_code => $field_data ) {
			if ( ! is_array( $field_data ) ) {
				continue;
			}

			$field_objects[ $field_code ] = Field::from_array( $field_code, $field_data );
		}

		return $field_objects;
	}

	/**
	 * Transform Field to Flattened_Field_Config.
	 *
	 * @param Field  $field     Field component.
	 * @param string $transport Transport identifier (e.g., 'rest', 'mcp').
	 * @return Flattened_Field_Config Flattened field config value object.
	 */
	public function to_config( Field $field, string $transport ): Flattened_Field_Config {
		return $this->transformer->to_config( $field, $transport );
	}

	/**
	 * Transform array of Fields to flat config array.
	 *
	 * @param array<string, Field> $fields    Array of Field components.
	 * @param string                $transport Transport identifier (e.g., 'rest', 'mcp').
	 * @return array<string, mixed> Flat config array.
	 * @throws \InvalidArgumentException If non-Field objects are found in array.
	 */
	public function to_config_array( array $fields, string $transport ): array {
		return $this->transformer->to_config_array( $fields, $transport );
	}

	/**
	 * Transform structured fields array to flat config array.
	 *
	 * Creates Field components from array data and transforms them to config.
	 *
	 * @param array<string, array> $fields    Structured fields from Zod schema.
	 * @param string               $transport Transport identifier (e.g., 'rest', 'mcp').
	 * @return array<string, mixed> Flat config array for CRUD services.
	 * @throws \InvalidArgumentException If field data is invalid.
	 */
	public function flatten_fields( array $fields, string $transport ): array {
		$field_objects = $this->create_fields( $fields );
		return $this->to_config_array( $field_objects, $transport );
	}

	/**
	 * Resolve each incoming field's `type` to its internal-only type
	 * (html / markdown) from the authoritative field definitions, BEFORE
	 * flattening.
	 *
	 * The sanitizer receives pre-resolved types: a `supports_tinymce`
	 * textarea sanitized as a plain textarea loses its HTML to
	 * sanitize_textarea_field(). Resolving here routes it to the sanitizer's
	 * raw-passthrough html branch instead. Definitions are keyed by
	 * option_code and MUST come from the registry (authoritative), never the
	 * request payload — see Field_Types::resolve_internal_type().
	 *
	 * @param array<string, array> $fields              Structured wire fields (code => {type, value, ...}).
	 * @param array<string, array> $definitions_by_code option_code => authoritative field definition.
	 * @return array<string, array> Fields with internal types resolved.
	 */
	public function resolve_internal_field_types( array $fields, array $definitions_by_code ): array {
		foreach ( $fields as $code => $field_data ) {
			if ( ! is_array( $field_data ) || ! isset( $field_data['type'] ) ) {
				continue;
			}

			$definition = $definitions_by_code[ $code ] ?? null;
			if ( ! is_array( $definition ) ) {
				continue;
			}

			$fields[ $code ]['type'] = Field_Types::resolve_internal_type( (string) $field_data['type'], $definition );
		}

		return $fields;
	}

	/**
	 * Get field data from post meta.
	 *
	 * Retrieves value, readable, and custom suffixes for a field.
	 *
	 * @param int    $post_id    The post ID.
	 * @param string $field_code The field code (meta key base).
	 * @return array{value: mixed, readable: mixed, custom: mixed}
	 */
	public function get_field_from_meta( int $post_id, string $field_code ): array {
		return array(
			'value'    => get_post_meta( $post_id, $field_code, true ),
			'readable' => get_post_meta( $post_id, $field_code . '_readable', true ),
			'custom'   => get_post_meta( $post_id, $field_code . '_custom', true ),
		);
	}
}
