<?php
/**
 * Makes command output valid UTF-8.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Transcript;

use Throwable;

/**
 * Replace invalid UTF-8 sequences with U+FFFD so the text survives JSON
 * serialisation. Line endings and everything else are preserved.
 */
final class Utf8 {

	/**
	 * The text with every invalid sequence replaced.
	 *
	 * @param string $text Any bytes.
	 * @return string
	 *
	 * @throws Throwable When mb_scrub fails; the substitute character is restored first.
	 */
	public static function sanitize( string $text ): string {
		if ( '' === $text || preg_match( '//u', $text ) ) {
			return $text;
		}

		if ( function_exists( 'mb_scrub' ) && function_exists( 'mb_substitute_character' ) ) {
			$previous = mb_substitute_character();
			mb_substitute_character( 0xFFFD );
			try {
				return mb_scrub( $text, 'UTF-8' );
			} catch ( Throwable $failure ) {
				// Keep the original failure for the outer response handler; cleanup still runs.
				throw $failure;
			} finally {
				mb_substitute_character( $previous );
			}
		}

		return (string) preg_replace_callback(
			'/[\x00-\x7F]+|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2}|(.)/s',
			static fn ( array $match ): string => isset( $match[1] ) ? "\u{FFFD}" : $match[0],
			$text
		);
	}
}
