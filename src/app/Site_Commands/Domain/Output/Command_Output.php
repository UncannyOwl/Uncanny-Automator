<?php
/**
 * What a command produced, before formatting.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command errors are plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Domain\Output;

/**
 * Either rows of items, formatted afterwards with --format and --fields, or
 * text that is printed as it is. Either may carry stats: lines that tell the
 * reader how much exists, so a list never has to be paged through to count.
 */
final class Command_Output {

	/**
	 * Items with every declared field, or null for text output.
	 *
	 * @var array<int, array<string, scalar|null>>|null
	 */
	private ?array $rows;

	private string $text;

	private bool $record = false;

	/**
	 * Every matching item, when the rows are one page of them.
	 *
	 * @var int|null
	 */
	private ?int $total = null;

	/**
	 * Stats lines, printed apart from the output.
	 *
	 * @var string[]
	 */
	private array $stats = array();

	/**
	 * Warnings that the output may be incomplete, printed even with a count.
	 *
	 * @var string[]
	 */
	private array $warnings = array();

	/**
	 * Whether the output reports a failure: nothing was done, and something
	 * failed. Rows are still printed.
	 *
	 * @var bool
	 */
	private bool $failure = false;

	/**
	 * Use rows(), record() or text().
	 *
	 * @param array<int, array<string, scalar|null>>|null $rows Items.
	 * @param string                                 $text Text.
	 */
	private function __construct( ?array $rows, string $text ) {
		$this->rows = $rows;
		$this->text = $text;
	}

	/**
	 * Rows of items. Cells keep their scalar type, so JSON prints false and
	 * numbers as WP-CLI does; tables and CSV print them as text.
	 *
	 * @param array<int, array<string, scalar|null>> $rows Items keyed by field.
	 * @return self
	 *
	 * @throws \LogicException When a cell is not a scalar or null.
	 */
	public static function rows( array $rows ): self {
		foreach ( $rows as $row ) {
			foreach ( $row as $field => $value ) {
				if ( null !== $value && ! is_scalar( $value ) ) {
					throw new \LogicException( 'A site command cell must be a scalar: ' . $field );
				}
			}
		}

		return new self( array_values( $rows ), '' );
	}

	/**
	 * One item, printed as Field and Value like WP-CLI's get commands.
	 *
	 * @param array<string, scalar|null> $item Item keyed by field.
	 * @return self
	 */
	public static function record( array $item ): self {
		$output         = self::rows( array( $item ) );
		$output->record = true;

		return $output;
	}

	/**
	 * Whether this is a single item.
	 *
	 * @return bool
	 */
	public function is_record(): bool {
		return $this->record;
	}

	/**
	 * Text printed as it is.
	 *
	 * @param string $text Text.
	 * @return self
	 */
	public static function text( string $text ): self {
		return new self( null, $text );
	}

	/**
	 * Whether this is text output.
	 *
	 * @return bool
	 */
	public function is_text(): bool {
		return null === $this->rows;
	}

	/**
	 * The text output.
	 *
	 * @return string
	 */
	public function text_output(): string {
		return $this->text;
	}

	/**
	 * The rows.
	 *
	 * @return array<int, array<string, scalar|null>>
	 */
	public function rows_output(): array {
		return (array) $this->rows;
	}

	/**
	 * The same rows, as one page of a larger set.
	 *
	 * @param int $total Every matching item, on all pages.
	 * @return self
	 *
	 * @throws \LogicException When this is not a list, or the total is smaller than the page.
	 */
	public function with_total( int $total ): self {
		if ( null === $this->rows || $this->record || $total < count( $this->rows ) ) {
			throw new \LogicException( 'A total belongs to a list and cannot be smaller than its page.' );
		}
		$output        = clone $this;
		$output->total = $total;

		return $output;
	}

	/**
	 * Every matching item: the total when the rows are one page, otherwise the rows.
	 *
	 * @return int
	 */
	public function total(): int {
		return $this->total ?? count( (array) $this->rows );
	}

	/**
	 * The same output with a line of stats, such as a breakdown by status or
	 * the next page to read.
	 *
	 * @param string $line One line of stats.
	 * @return self
	 */
	public function with_stats( string $line ): self {
		$output          = clone $this;
		$output->stats[] = $line;

		return $output;
	}

	/**
	 * The stats lines.
	 *
	 * @return string[]
	 */
	public function stats(): array {
		return $this->stats;
	}

	/**
	 * The same output with a warning that it may be incomplete, such as
	 * entries left out because they could not be read.
	 *
	 * @param string $line One line of warning.
	 * @return self
	 */
	public function with_warning( string $line ): self {
		$output             = clone $this;
		$output->warnings[] = $line;

		return $output;
	}

	/**
	 * The warning lines.
	 *
	 * @return string[]
	 */
	public function warnings(): array {
		return $this->warnings;
	}

	/**
	 * The same output, reported as a failure.
	 *
	 * @return self
	 */
	public function with_failure(): self {
		$output          = clone $this;
		$output->failure = true;

		return $output;
	}

	/**
	 * Whether the output reports a failure.
	 *
	 * @return bool
	 */
	public function is_failure(): bool {
		return $this->failure;
	}
}
