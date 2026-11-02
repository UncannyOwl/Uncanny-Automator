<?php
/**
 * Splits text into bounded lines.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem;

use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Line_Read_Limit_Exceeded;

/**
 * Complete logical lines, never chunks mistaken for additional lines.
 */
final class Bounded_Lines {

	/** The longest line a read may hold, terminator included. */
	public const MAX_BYTES = 1048576;

	/**
	 * Complete lines with their terminators intact.
	 *
	 * @param string $text Any text.
	 * @return iterable<string>
	 *
	 * @throws Line_Read_Limit_Exceeded Before yielding any part of a line over MAX_BYTES.
	 */
	public static function from_string( string $text ): iterable {
		$length = strlen( $text );
		$offset = 0;
		while ( $offset < $length ) {
			$newline = strpos( $text, "\n", $offset );
			$end     = false === $newline ? $length : $newline + 1;
			if ( $end - $offset > self::MAX_BYTES ) {
				throw new Line_Read_Limit_Exceeded();
			}
			yield substr( $text, $offset, $end - $offset );
			$offset = $end;
		}
	}
}
