<?php
/**
 * Read values from WordPress and other plugins defensively.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Infrastructure\Commands;

use Uncanny_Automator\App\Site_Commands\Domain\Output\Command_Output;

/**
 * Headers, options and objects can hold anything another plugin put there.
 * Only scalars become text, and whole numbers are checked without ctype,
 * which some hosts do not provide.
 */
final class Values {

	/**
	 * A scalar as text; anything else as an empty string.
	 *
	 * @param mixed $value Value from a header, option or object.
	 * @return string
	 */
	public static function text( $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Whether the text is a whole number such as "42".
	 *
	 * @param string $value Text.
	 * @return bool
	 */
	public static function is_whole_number( string $value ): bool {
		return 1 === preg_match( '/^[0-9]{1,18}$/', $value );
	}

	/**
	 * A yes-or-no setting. A scalar reads the way PHP and WordPress read it;
	 * anything else is unknown (null, printed empty), never true.
	 *
	 * @param mixed $value Value from an object or option.
	 * @return bool|null
	 */
	public static function flag( $value ): ?bool {
		return is_scalar( $value ) ? (bool) $value : null;
	}

	/**
	 * A count or other whole number of 0 or more, or null when it is not one.
	 *
	 * @param mixed $value Value from an object or option.
	 * @return int|null
	 */
	public static function whole_number( $value ): ?int {
		if ( is_int( $value ) ) {
			return $value >= 0 ? $value : null;
		}

		return is_string( $value ) && self::is_whole_number( $value ) ? (int) $value : null;
	}

	/**
	 * Declare entries that were left out because they could not be read, so a
	 * short or empty list is never mistaken for a complete one.
	 *
	 * @param Command_Output $output  Output.
	 * @param int            $skipped Entries left out.
	 * @return Command_Output
	 */
	public static function with_skipped( Command_Output $output, int $skipped ): Command_Output {
		if ( 0 === $skipped ) {
			return $output;
		}

		return $output->with_warning( sprintf( 'Skipped %d unreadable %s. Another plugin may have changed the data.', $skipped, 1 === $skipped ? 'entry' : 'entries' ) );
	}
}
