<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Settings\Enums;

use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Recipe_Store;

/**
 * Recipe Setting Id Enum.
 *
 * Single source of truth for the IDs of every recipe-level setting the
 * publish-settings panel reads or writes. Mirrors the frontend's
 * `RecipeSettingID`
 * (`src/assets/src/domains/recipe/types/models/recipe/setting-id.ts`); the
 * two are mirrors — either side moving creates parity drift.
 *
 * Three editable settings (writable through Recipe_Settings_Service and
 * persisted via WP_Recipe_Store's targeted writers) map to post-meta keys
 * via `meta_key()`. Three derived / read-only settings return null — they
 * are projected by the wire serializer from other recipe fields and
 * rejected on write.
 *
 * The meta-key map points at WP_Recipe_Store's existing `_META_*` constants
 * so a single source declares the wire vocabulary AND the storage shape;
 * when meta keys move, only the store's constants change.
 */
final class Recipe_Setting_Id {

	/**
	 * Editable: rate-limit configuration.
	 */
	const RECIPE_THROTTLE = 'recipe_throttle';

	/**
	 * Editable: global per-recipe run limit.
	 */
	const RECIPE_TOTAL_TIMES = 'recipe_total_times';

	/**
	 * Editable: per-user run limit.
	 */
	const RECIPE_TIMES_PER_USER = 'recipe_times_per_user';

	/**
	 * Derived / read-only: wraps `recipe_type` ('user' / 'anonymous').
	 */
	const RECIPE_USER_TYPE = 'recipe_user_type';

	/**
	 * Derived / read-only: wraps `miscellaneous.created_on_date`.
	 */
	const RECIPE_CREATED_ON = 'recipe_created_on';

	/**
	 * Derived / read-only: wraps `stats.total_runs`.
	 */
	const RECIPE_COMPLETED_RUNS = 'recipe_completed_runs';

	/**
	 * @return array<string> All recipe-setting IDs in declaration order.
	 *                       Matches the FE `RecipeSettingID` enum order.
	 */
	public static function get_all(): array {
		return array(
			self::RECIPE_THROTTLE,
			self::RECIPE_TOTAL_TIMES,
			self::RECIPE_TIMES_PER_USER,
			self::RECIPE_USER_TYPE,
			self::RECIPE_CREATED_ON,
			self::RECIPE_COMPLETED_RUNS,
		);
	}

	/**
	 * The settings an author may change; everything else on the strip is derived.
	 *
	 * @return array<string> The three editable recipe-setting IDs.
	 */
	public static function editable_ids(): array {
		return array(
			self::RECIPE_THROTTLE,
			self::RECIPE_TOTAL_TIMES,
			self::RECIPE_TIMES_PER_USER,
		);
	}

	/**
	 * Return true when $id is one of the six declared setting IDs.
	 */
	public static function is_valid( string $id ): bool {
		return in_array( $id, self::get_all(), true );
	}

	/**
	 * Return true when $id is one of the three writable (non-derived) setting IDs.
	 */
	public static function is_editable( string $id ): bool {
		return in_array( $id, self::editable_ids(), true );
	}

	/**
	 * Post-meta key for an editable setting, or null for derived /
	 * read-only ids (and unknown ids).
	 */
	public static function meta_key( string $id ): ?string {
		switch ( $id ) {
			case self::RECIPE_THROTTLE:
				return WP_Recipe_Store::_META_RECIPE_THROTTLE;
			case self::RECIPE_TOTAL_TIMES:
				return WP_Recipe_Store::_META_RECIPE_TOTAL_TIMES;
			case self::RECIPE_TIMES_PER_USER:
				return WP_Recipe_Store::_META_RECIPE_TIMES_PER_USER;
			default:
				return null;
		}
	}
}
