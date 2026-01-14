<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Closure\Value_Objects;

/**
 * Closure Sentence Value Object.
 *
 * Immutable value object that encapsulates closure sentence templates.
 * Stores human-readable and HTML versions of closure descriptions.
 *
 * @since 7.0.0
 */
class Closure_Sentence {

	private string $human_readable;
	private string $human_readable_html;

	/**
	 * Constructor.
	 *
	 * @param string $human_readable Human-readable sentence.
	 * @param string $human_readable_html HTML version of sentence.
	 * @throws \InvalidArgumentException If sentences are invalid.
	 */
	public function __construct( string $human_readable, string $human_readable_html ) {
		$this->validate_and_set( $human_readable, $human_readable_html );
	}

	/**
	 * Get human-readable sentence.
	 *
	 * @return string
	 */
	public function get_human_readable(): string {
		return $this->human_readable;
	}

	/**
	 * Get HTML sentence.
	 *
	 * @return string
	 */
	public function get_human_readable_html(): string {
		return $this->human_readable_html;
	}

	/**
	 * Check if sentences are equal.
	 *
	 * @param Closure_Sentence $other Other sentence.
	 * @return bool
	 */
	public function equals( Closure_Sentence $other ): bool {
		return $this->human_readable === $other->get_human_readable()
			&& $this->human_readable_html === $other->get_human_readable_html();
	}

	/**
	 * Validate and set sentences.
	 *
	 * @param string $human_readable Human-readable sentence.
	 * @param string $human_readable_html HTML sentence.
	 * @throws \InvalidArgumentException If validation fails.
	 */
	private function validate_and_set( string $human_readable, string $human_readable_html ): void {
		// Sentences can be individually empty - defaults will fill in missing values
		// This allows flexible usage:
		// - Both empty: use defaults
		// - Human readable only: defaults will generate HTML
		// - Both provided: use as-is

		$this->human_readable      = $human_readable;
		$this->human_readable_html = $human_readable_html;
	}

	/**
	 * Check if sentence is empty (defaults will be used).
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return empty( trim( $this->human_readable ) ) && empty( trim( $this->human_readable_html ) );
	}

	/**
	 * Create from sentences (factory method).
	 *
	 * @param string $human_readable Human-readable sentence.
	 * @param string $human_readable_html HTML sentence.
	 * @return self New instance.
	 */
	public static function from_sentences( string $human_readable, string $human_readable_html ): self {
		return new self( $human_readable, $human_readable_html );
	}

	/**
	 * Create empty sentence (will trigger defaults).
	 *
	 * @return self New empty instance.
	 */
	public static function empty(): self {
		return new self( '', '' );
	}
}
