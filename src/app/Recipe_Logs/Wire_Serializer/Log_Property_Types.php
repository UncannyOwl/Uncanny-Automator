<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Wire_Serializer;

use Uncanny_Automator\App\Recipe_Builder\Field\Enums\Field_Types;

/**
 * The recipe-log PROPERTY renderer-type enum — the frozen mirror of the
 * FE's closed ModelRecipeLogPropertyType (properties.ts: text, textarea,
 * code, file, repeater). This is a DIFFERENT wire vocabulary from the
 * field-type enum: `code` is a property-renderer concept, not an
 * authorable field type, so it lives here and NOT in Field_Types (keeping
 * it out of the builder wire the same way Log_Status_Registry keeps the
 * status wire away from the filterable Automator_Status map). The shared
 * members compose from Field_Types constants so every literal has exactly
 * one definition in the codebase.
 */
final class Log_Property_Types {

	/**
	 * Plain-text property row.
	 *
	 * @var string
	 */
	const TEXT = Field_Types::TEXT;

	/**
	 * Multi-line text property row.
	 *
	 * @var string
	 */
	const TEXTAREA = Field_Types::TEXTAREA;

	/**
	 * Syntax-highlighted code viewer row (property-only concept — not an
	 * authorable field type; `attributes.code_language` picks the grammar).
	 *
	 * @var string
	 */
	const CODE = 'code';

	/**
	 * File-card property row.
	 *
	 * @var string
	 */
	const FILE = Field_Types::FILE;

	/**
	 * Repeater-table property row.
	 *
	 * @var string
	 */
	const REPEATER = Field_Types::REPEATER;

	/**
	 * Static-only class.
	 */
	private function __construct() {
	}

	/**
	 * Every member of the closed wire enum, in the FE declaration order.
	 *
	 * @return array<int,string> The member values.
	 */
	public static function get_all(): array {
		return array( self::TEXT, self::TEXTAREA, self::CODE, self::FILE, self::REPEATER );
	}

	/**
	 * Whether a value is a member of the closed enum.
	 *
	 * @param string $type Candidate type.
	 * @return bool True when the value is a member.
	 */
	public static function is_valid( string $type ): bool {
		return in_array( $type, self::get_all(), true );
	}

	/**
	 * Coerce any value onto the closed enum, degrading unknowns to text —
	 * the same contract the FE applies via `.catch('text')`, enforced at
	 * the producing boundary so the wire stays honest.
	 *
	 * @param string $type Candidate type.
	 * @return string The value itself when a member, otherwise text.
	 */
	public static function coerce( string $type ): string {
		return self::is_valid( $type ) ? $type : self::TEXT;
	}
}
