<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Block\Enums;

/**
 * Block_Path_Code Enum.
 *
 * Authoritative path-code constants for the four buckets the recipe tree
 * emits as children of a block. Mirrors the `path_code` values declared
 * inside `set_paths(...)` in each block-registry block class — per Rule 17,
 * those block files are the source of truth and this enum is the
 * grep-stable handle every downstream reader (Recipe_Tree_Builder,
 * migrators, projector) keys off.
 *
 * Parity with the registry is enforced by
 * `tests/wpunit/app/recipe-builder/block/enums/Block_Path_Code_Test.php`.
 */
final class Block_Path_Code {

	/**
	 * Filter block — branch taken when conditions evaluate true.
	 */
	const FILTER_CONDITIONS_MET = 'FILTER-CONDITIONS-MET';

	/**
	 * Filter block — branch taken when conditions evaluate false.
	 */
	const FILTER_CONDITIONS_NOT_MET = 'FILTER-CONDITIONS-NOT-MET';

	/**
	 * Loop block — branch taken once per iteration.
	 */
	const LOOP_ITERATION_COMPLETED = 'LOOP-ITERATION-COMPLETED';

	/**
	 * Delay_schedule block — branch taken when the scheduled time arrives.
	 */
	const DELAY_COMPLETED = 'DELAY-COMPLETED';

	/**
	 * All known path codes.
	 *
	 * @return list<string>
	 */
	public static function get_all(): array {
		return array(
			self::FILTER_CONDITIONS_MET,
			self::FILTER_CONDITIONS_NOT_MET,
			self::LOOP_ITERATION_COMPLETED,
			self::DELAY_COMPLETED,
		);
	}

	/**
	 * Whether `$code` is a known path code.
	 */
	public static function is_valid( string $code ): bool {
		return in_array( $code, self::get_all(), true );
	}

	/**
	 * Path codes declared by a given block type, in catalog order.
	 *
	 * Unknown block types return an empty list — callers that need a
	 * single default should use {@see default_for_block_type()} instead.
	 *
	 * @return list<string>
	 */
	public static function for_block_type( string $block_type ): array {
		switch ( $block_type ) {
			case Block_Type::FILTER:
				return array(
					self::FILTER_CONDITIONS_MET,
					self::FILTER_CONDITIONS_NOT_MET,
				);
			case Block_Type::LOOP:
				return array( self::LOOP_ITERATION_COMPLETED );
			case Block_Type::DELAY_SCHEDULE:
				return array( self::DELAY_COMPLETED );
			default:
				return array();
		}
	}

	/**
	 * Default (first declared) path code for a given block type.
	 *
	 * Used by the tree builder when storage doesn't stamp an explicit
	 * path_code on a child entry. Returns empty string for unknown block
	 * types.
	 */
	public static function default_for_block_type( string $block_type ): string {
		$paths = self::for_block_type( $block_type );
		return $paths[0] ?? '';
	}
}
