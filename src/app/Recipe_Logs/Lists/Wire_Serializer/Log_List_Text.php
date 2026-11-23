<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Lists\Wire_Serializer;

/**
 * A stored sentence, message or user name as the one-line plain text a list
 * shows.
 *
 * Stored sentences and notes may hold HTML (token pills, a link, a line
 * break) and HTML entities; WordPress stores a user's display name with its
 * `&`, `<` and `>` as entities. Tags go first, so an escaped `&lt;` in the
 * text survives as `<`; then entities are decoded once and whitespace
 * collapsed.
 *
 * @since 8.1.0
 */
final class Log_List_Text {

	/**
	 * Static-only class.
	 */
	private function __construct() {
	}

	/**
	 * The stored text as plain text.
	 *
	 * @param string $stored The stored sentence, message or user name.
	 *
	 * @return string
	 */
	public static function plain( string $stored ): string {
		$text = html_entity_decode( wp_strip_all_tags( (string) preg_replace( '#<br\s*/?>#i', ' ', $stored ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * A stored item sentence as the plain sentence a list shows: each filled
	 * field reads as its value, without its label.
	 *
	 * The HTML form marks a filled field's label in its own span; the bracket
	 * form writes a filled field as `{{Label: value}}` and an empty one as
	 * `{{a decorator}}`.
	 *
	 * @param string $stored  The stored sentence.
	 * @param bool   $is_html Whether it is the HTML form.
	 *
	 * @return string
	 */
	public static function sentence( string $stored, bool $is_html ): string {
		if ( $is_html ) {
			return self::plain( (string) preg_replace( '#<span\b[^>]*\bitem-title__token-label\b[^>]*>.*?</span>#s', '', $stored ) );
		}

		$text = preg_replace_callback(
			'/\{\{(.*?)\}\}/s',
			static function ( array $token ): string {
				$separator = strpos( $token[1], ': ' );

				return false === $separator ? $token[1] : substr( $token[1], $separator + 2 );
			},
			$stored
		);

		return self::plain( (string) $text );
	}
}
