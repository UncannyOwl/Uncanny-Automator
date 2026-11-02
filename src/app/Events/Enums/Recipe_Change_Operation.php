<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Events\Enums;

/**
 * Recipe_Change_Operation Enum.
 *
 * What a service write did to the node it addressed, as carried by the
 * `recipe_changed` event. A status change is an update.
 *
 * @since 8.0.0
 */
final class Recipe_Change_Operation {

	/**
	 * The addressed node was created.
	 */
	const CREATED = 'created';

	/**
	 * The addressed node was changed.
	 */
	const UPDATED = 'updated';

	/**
	 * The addressed node was deleted.
	 */
	const DELETED = 'deleted';

	/**
	 * All known operations.
	 *
	 * @return list<string>
	 */
	public static function get_all(): array {
		return array(
			self::CREATED,
			self::UPDATED,
			self::DELETED,
		);
	}

	/**
	 * Whether `$operation` is a known operation.
	 *
	 * @param string $operation Operation to check.
	 *
	 * @return bool
	 */
	public static function is_valid( string $operation ): bool {
		return in_array( $operation, self::get_all(), true );
	}
}
