<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Loop\Enums;

/**
 * Why a loopable token of the recipe is not valid at a loop.
 *
 * A loop can iterate a loopable token only when the trigger or action that
 * produces it has run by the time the loop starts. Each value names one way
 * the producer misses that.
 *
 * PHP 7.4 compatible class-based enum.
 *
 * @since 8.0.1
 */
final class Loop_Token_Refusal {

	/**
	 * The producer is an action inside the loop itself.
	 *
	 * @var string
	 */
	const INSIDE_LOOP = 'inside_loop';

	/**
	 * The producer runs after the loop.
	 *
	 * @var string
	 */
	const AFTER_LOOP = 'after_loop';

	/**
	 * The producer is inside another block that runs before the loop; a token from inside a block is not available after that block.
	 *
	 * @var string
	 */
	const INSIDE_OTHER_BLOCK = 'inside_other_block';

	/**
	 * The producer is in another path of a block the loop is in, so it does not run in the same run as the loop.
	 *
	 * @var string
	 */
	const OTHER_PATH = 'other_path';

	/**
	 * The producer is a trigger or action this site cannot run.
	 *
	 * @var string
	 */
	const PRODUCER_UNAVAILABLE = 'producer_unavailable';

	/**
	 * Every value.
	 *
	 * @return string[]
	 */
	public static function get_all(): array {
		return array(
			self::INSIDE_LOOP,
			self::AFTER_LOOP,
			self::INSIDE_OTHER_BLOCK,
			self::OTHER_PATH,
			self::PRODUCER_UNAVAILABLE,
		);
	}

	/**
	 * Whether a value is one of these.
	 *
	 * @param string $value Value to check.
	 *
	 * @return bool
	 */
	public static function is_valid( string $value ): bool {
		return in_array( $value, self::get_all(), true );
	}
}
