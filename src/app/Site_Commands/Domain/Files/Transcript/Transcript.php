<?php
/**
 * The ordered output of one file command.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Transcript;

use Generator;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Exit_Status;

/**
 * The ordered output of one invocation, and the rule for rendering it as
 * terminal text: chunks in the order they happened, and `Exit code N` on
 * the last line only when N is not zero.
 */
final class Transcript {

	/**
	 * Chunks in order, each a stream and its text.
	 *
	 * @var array<int, array{0: string, 1: string}>
	 */
	private array $chunks = array();

	/**
	 * The exit status, once finished.
	 *
	 * @var Exit_Status|null
	 */
	private ?Exit_Status $exit_status = null;

	/**
	 * True when a budget cut the output.
	 *
	 * @var bool
	 */
	private bool $truncated = false;

	/**
	 * The last byte written; a newline before anything is written.
	 *
	 * @var string
	 */
	private string $tail = "\n";

	/**
	 * Append text to one stream. Adjacent writes to a stream share a chunk, so
	 * memory follows the bytes written, not how many writes made them: a file
	 * of 120,000 two-byte lines is 240 KB of text, not 120,000 arrays.
	 *
	 * @param string $stream One of the Stream constants.
	 * @param string $text   Output text.
	 * @return void
	 */
	public function write( string $stream, string $text ): void {
		if ( '' === $text ) {
			return;
		}
		$last = count( $this->chunks ) - 1;
		if ( $last >= 0 && $stream === $this->chunks[ $last ][0] ) {
			$this->chunks[ $last ][1] .= $text;
		} else {
			$this->chunks[] = array( $stream, $text );
		}
		$this->tail = substr( $text, -1 );
	}

	/**
	 * Ordered output for bounded pipeline merging.
	 *
	 * @return Generator<array{0: string, 1: string}>
	 */
	public function chunks(): Generator {
		yield from $this->chunks;
	}

	/**
	 * Record the exit status.
	 *
	 * @param Exit_Status $exit_status The status.
	 * @return void
	 */
	public function finish( Exit_Status $exit_status ): void {
		$this->exit_status = $exit_status;
	}

	/**
	 * Record that a budget cut the output.
	 *
	 * @return void
	 */
	public function mark_truncated(): void {
		$this->truncated = true;
	}

	/**
	 * Whether a budget cut the output.
	 *
	 * @return bool
	 */
	public function is_truncated(): bool {
		return $this->truncated;
	}

	/**
	 * The exit status; success until finished.
	 *
	 * @return Exit_Status
	 */
	public function exit_status(): Exit_Status {
		return $this->exit_status ?? Exit_Status::success();
	}

	/**
	 * Whether the output so far ends with a newline, or is empty.
	 *
	 * @return bool
	 */
	public function ends_with_newline(): bool {
		return "\n" === $this->tail;
	}

	/**
	 * Everything written to standard output.
	 *
	 * @return string
	 */
	public function stdout(): string {
		return $this->collect( Stream::STDOUT );
	}

	/**
	 * Everything written to standard error.
	 *
	 * @return string
	 */
	public function stderr(): string {
		return $this->collect( Stream::STDERR );
	}

	/**
	 * The text the model sees.
	 *
	 * @return string
	 */
	public function render(): string {
		$text = '';
		foreach ( $this->chunks as $chunk ) {
			$text .= $chunk[1];
		}

		$status = $this->exit_status();
		if ( ! $status->is_success() ) {
			if ( '' !== $text && "\n" !== substr( $text, -1 ) ) {
				$text .= "\n";
			}
			$text .= sprintf( 'Exit code %d', $status->code() );
		}

		return Utf8::sanitize( $text );
	}

	/**
	 * Everything written to one stream.
	 *
	 * @param string $stream One of the Stream constants.
	 * @return string
	 */
	private function collect( string $stream ): string {
		$text = '';
		foreach ( $this->chunks as $chunk ) {
			if ( $chunk[0] === $stream ) {
				$text .= $chunk[1];
			}
		}

		return $text;
	}
}
