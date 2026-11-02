<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Status\Enums;

/**
 * Log_Status_Appearance Enum.
 *
 * The two row-prominence values the recipe-log wire ships on every status
 * object. CLOSED SET — mirrors the frontend Zod enum verbatim. `muted`
 * renders the node's row with reduced prominence; `normal` renders
 * unmodified.
 */
final class Log_Status_Appearance {

	/**
	 * Rendered without modification.
	 *
	 * @var string
	 */
	const NORMAL = 'normal';

	/**
	 * Rendered with reduced prominence (grayscale + lowered opacity).
	 *
	 * @var string
	 */
	const MUTED = 'muted';

	/**
	 * All appearances, in contract order.
	 *
	 * @return array<int, string>
	 */
	public static function all(): array {
		return array( self::NORMAL, self::MUTED );
	}

	/**
	 * Whether the given appearance is a member of the closed set.
	 *
	 * @param string $appearance Candidate appearance.
	 *
	 * @return bool
	 */
	public static function is_valid( string $appearance ): bool {
		return in_array( $appearance, self::all(), true );
	}
}
