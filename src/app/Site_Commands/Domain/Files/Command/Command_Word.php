<?php
/**
 * One word of a file-command line, after quote removal.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Command;

/**
 * A word as the command receives it, and whether a shell would expand it
 * into path names.
 *
 * A shell expands `*`, `?` and `[...]` only where they were typed without
 * quotes. The word keeps that knowledge as a pattern: the word's text with
 * every character that was quoted, and every backslash, escaped by a
 * backslash, so those characters match only themselves. A word without an
 * unquoted wildcard has no pattern and is never expanded.
 */
final class Command_Word {

	/**
	 * The word with quotes removed.
	 *
	 * @var string
	 */
	private string $text;

	/**
	 * The path-name pattern, or null when no wildcard was typed unquoted.
	 *
	 * @var string|null
	 */
	private ?string $glob;

	/**
	 * Hold one word.
	 *
	 * @param string      $text The word with quotes removed.
	 * @param string|null $glob The path-name pattern, or null.
	 */
	public function __construct( string $text, ?string $glob = null ) {
		$this->text = $text;
		$this->glob = $glob;
	}

	/**
	 * A word from its characters, each marked quoted or not.
	 *
	 * @param array<int, array{0: string, 1: bool}> $pieces Characters and whether each was quoted.
	 * @return self
	 */
	public static function from_pieces( array $pieces ): self {
		$text     = '';
		$pattern  = '';
		$wildcard = false;
		foreach ( $pieces as $piece ) {
			list( $char, $quoted ) = $piece;
			$text                 .= $char;
			if ( ! $quoted && in_array( $char, array( '*', '?', '[' ), true ) ) {
				$wildcard = true;
			}
			$pattern .= '\\' === $char || ( $quoted && false !== strpos( '*?[]', $char ) ) ? '\\' . $char : $char;
		}

		return new self( $text, $wildcard ? $pattern : null );
	}

	/**
	 * The word with quotes removed.
	 *
	 * @return string
	 */
	public function text(): string {
		return $this->text;
	}

	/**
	 * The path-name pattern: the text with quoted wildcards and every
	 * backslash escaped; null when no wildcard was typed unquoted.
	 *
	 * @return string|null
	 */
	public function glob(): ?string {
		return $this->glob;
	}
}
