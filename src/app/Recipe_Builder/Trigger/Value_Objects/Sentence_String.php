<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Trigger\Value_Objects;

use Uncanny_Automator\App\Recipe_Builder\Shared\Polyfill\Str;

/**
 * Sentence String Value Object.
 *
 * Validates the sentences a definition registers and catalog search results carry.
 * Enforces length limits and basic content validation.
 * A stored trigger's sentences are display snapshots and never pass through here.
 *
 * @since 7.0.0
 */
class Sentence_String {

	private string $value;

	/**
	 * Whether this is an HTML sentence (relaxed validation).
	 *
	 * @var bool
	 */
	private bool $is_html = false;

	/**
	 * Constructor.
	 *
	 * @param string $sentence Sentence text (5-250 characters for plain text).
	 * @param bool   $is_html  Whether this is an HTML sentence (relaxed validation).
	 * @throws \InvalidArgumentException If sentence format is invalid.
	 */
	public function __construct( string $sentence, bool $is_html = false ) {
		$this->is_html = $is_html;
		$this->validate_and_set( $sentence );
	}

	/**
	 * Get sentence value.
	 *
	 * @return string Validated sentence.
	 */
	public function get_value(): string {
		return $this->value;
	}

	/**
	 * Check if sentence contains token placeholders.
	 *
	 * @return bool True if sentence has {{TOKEN}} patterns.
	 */
	public function has_tokens(): bool {
		return false !== strpos( $this->value, '{{' );
	}

	/**
	 * Get token count in sentence.
	 *
	 * @return int Number of {{TOKEN}} patterns found.
	 */
	public function get_token_count(): int {
		return preg_match_all( '/\{\{[^}]+\}\}/', $this->value );
	}

	/**
	 * Check if sentence is question format.
	 *
	 * @return bool True if sentence ends with question mark.
	 */
	public function is_question(): bool {
		return Str::ends_with( $this->value, '?' );
	}

	/**
	 * To string.
	 *
	 * @return string Sentence text.
	 */
	public function __toString(): string {
		return $this->value;
	}

	/**
	 * Validate and set sentence value.
	 *
	 * @param string $sentence Sentence to validate.
	 * @throws \InvalidArgumentException If validation fails.
	 */
	private function validate_and_set( string $sentence ): void {
		// Trim whitespace
		$sentence = trim( $sentence );

		// Check if empty
		if ( empty( $sentence ) ) {
			throw new \InvalidArgumentException( 'Sentence cannot be empty' );
		}

		// Check minimum length. Bytes on purpose: a short sentence in a script of
		// several bytes per character, such as four Chinese characters, is complete.
		// Trade-off: two Chinese characters also pass, where two Latin letters don't.
		if ( strlen( $sentence ) < 5 ) {
			throw new \InvalidArgumentException( 'Sentence must be at least 5 characters long' );
		}

		// HTML sentences have relaxed length/character validation since they contain markup.
		if ( $this->is_html ) {
			// For HTML: only check for dangerous script patterns, allow all other HTML.
			if ( preg_match( '/<script|javascript:|onload=|onerror=/i', $sentence ) ) {
				throw new \InvalidArgumentException( 'Sentence contains potentially malicious content' );
			}

			// Store validated HTML sentence
			$this->value = $sentence;
			return;
		}

		// Plain text validation below (stricter).

		// Check maximum length
		if ( $this->character_count( $sentence ) > 250 ) {
			throw new \InvalidArgumentException( 'Sentence cannot exceed 250 characters' );
		}

		// Check for malicious content (basic XSS prevention)
		if ( preg_match( '/<script|javascript:|onload=|onerror=/i', $sentence ) ) {
			throw new \InvalidArgumentException( 'Sentence contains potentially malicious content' );
		}

		// No SQL-pattern check: a sentence is display text from an item's
		// definition, and every query that stores it is prepared. A word
		// check here only hid real items ("Delete {{a term}} from {{a taxonomy}}").

		// Check for excessive special characters (AI drift protection)
		if ( $this->special_character_count( $sentence ) > 30 ) {
			throw new \InvalidArgumentException( 'Sentence contains too many special characters (possible AI drift)' );
		}

		// Store validated sentence
		$this->value = $sentence;
	}

	/**
	 * Length in characters, so a sentence in any script has the same limit.
	 *
	 * Trade-off: text that isn't valid UTF-8 counts bytes, as this check always
	 * did, so an older encoding keeps working but reaches the limit sooner.
	 *
	 * @param string $sentence Sentence to measure.
	 * @return int Characters, or bytes when the text isn't valid UTF-8.
	 */
	private function character_count( string $sentence ): int {
		$count = preg_match_all( '/./su', $sentence );

		return false === $count ? strlen( $sentence ) : $count;
	}

	/**
	 * Characters that are neither letters, marks, digits nor ordinary punctuation.
	 *
	 * Letters and marks of every script count as text, so a translated sentence
	 * isn't mistaken for drift.
	 *
	 * Trade-off: drift made of letters from any script now passes, because this
	 * check can't tell it from a translation. Punctuation outside ASCII, such as
	 * the Chinese comma, still counts, so a long sentence full of it can be refused.
	 *
	 * @param string $sentence Sentence to check.
	 * @return int Special characters, or bytes outside ASCII text when the text isn't valid UTF-8.
	 */
	private function special_character_count( string $sentence ): int {
		$count = preg_match_all( '/[^\p{L}\p{M}\p{N}\s{}.,!?()-]/u', $sentence );

		if ( false !== $count ) {
			return $count;
		}

		return strlen( $sentence ) - strlen( (string) preg_replace( '/[^a-zA-Z0-9\s{}.,!?()-]/', '', $sentence ) );
	}

	/**
	 * Create from string (factory method).
	 *
	 * @param string $sentence Sentence text.
	 * @return self New instance.
	 */
	public static function from_string( string $sentence ): self {
		return new self( $sentence );
	}

	/**
	 * Create for human readable format.
	 *
	 * @param string $sentence Human readable sentence.
	 * @return self New instance with extra validation for UI display.
	 */
	public static function for_human_readable( string $sentence ): self {
		$instance = new self( $sentence );

		// Additional validation for human readable sentences here.

		return $instance;
	}

	/**
	 * Create for HTML format.
	 *
	 * HTML sentences have relaxed validation since they contain markup tags,
	 * attributes, and CSS classes which would fail the special character check.
	 *
	 * @param string $html HTML sentence content.
	 * @return self New instance with HTML-appropriate validation.
	 */
	public static function for_html( string $html ): self {
		return new self( $html, true );
	}
}
