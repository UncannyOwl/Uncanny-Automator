<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Services;

use RuntimeException;

/**
 * Removes Gemini URL keys before historical run errors are copied or rewritten.
 *
 * @since 8.0.0
 */
final class Gemini_Key_Redactor {

	public const MARKER = '[redacted]';

	public const KEY_PATTERN = '~(generativelanguage\.googleapis\.com[^\s?"\'<>]*+\?key=)[A-Za-z0-9_-]+~i';

	/**
	 * Replace Gemini URL keys while preserving the rest of the message.
	 *
	 * @since 8.0.0
	 *
	 * @param string $text Error message.
	 * @return string
	 * @throws RuntimeException When the pattern cannot be applied.
	 */
	public static function scrub( string $text ): string {
		$scrubbed = preg_replace( self::KEY_PATTERN, '${1}' . self::MARKER, $text );

		if ( null === $scrubbed ) {
			throw new RuntimeException( 'The Gemini key pattern failed with PCRE error ' . (int) preg_last_error() . '.' );
		}

		return $scrubbed;
	}
}
