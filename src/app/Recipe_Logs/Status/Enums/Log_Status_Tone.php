<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Status\Enums;

/**
 * Log_Status_Tone Enum.
 *
 * The five chip-color tones the recipe-log wire ships on every status
 * object. CLOSED SET — mirrors the frontend Zod enum verbatim. Tone maps to
 * a design-system color token and is independent of appearance (a skipped
 * node is tone `neutral` + appearance `muted`).
 */
final class Log_Status_Tone {

	/**
	 * Success color token.
	 *
	 * @var string
	 */
	const SUCCESS = 'success';

	/**
	 * Error color token.
	 *
	 * @var string
	 */
	const ERROR = 'error';

	/**
	 * Warning color token.
	 *
	 * @var string
	 */
	const WARNING = 'warning';

	/**
	 * Neutral color token.
	 *
	 * @var string
	 */
	const NEUTRAL = 'neutral';

	/**
	 * Muted color token.
	 *
	 * @var string
	 */
	const MUTED = 'muted';

	/**
	 * All tones, in contract order.
	 *
	 * @return array<int, string>
	 */
	public static function all(): array {
		return array(
			self::SUCCESS,
			self::ERROR,
			self::WARNING,
			self::NEUTRAL,
			self::MUTED,
		);
	}

	/**
	 * Whether the given tone is a member of the closed set.
	 *
	 * @param string $tone Candidate tone.
	 *
	 * @return bool
	 */
	public static function is_valid( string $tone ): bool {
		return in_array( $tone, self::all(), true );
	}
}
