<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Node_Tests\Value_Objects;

use InvalidArgumentException;

/**
 * One node-test run outcome — the run-result wire object.
 *
 * Enforces the FE presence rules at construction: terminal statuses
 * (passed/failed) always carry output with a non-empty heading, plus
 * completed_at/formatted_date when built through the named constructors
 * (a degraded from_stored() hydration may omit the optional timestamps);
 * nonterminal statuses (running/not_run) carry nothing but the status.
 * Neither construction nor hydration can produce a wire-invalid result.
 */
final class Run_Result {

	/**
	 * Lifecycle status: not_run | running | passed | failed.
	 *
	 * @var string
	 */
	private string $status;

	/**
	 * Report copy: heading (required on terminal) + optional content.
	 *
	 * @var array|null
	 */
	private ?array $output;

	/**
	 * Completion instant, Unix epoch seconds (UTC by definition).
	 *
	 * @var int|null
	 */
	private ?int $completed_at;

	/**
	 * Backend-formatted absolute timestamp for the hover title.
	 *
	 * @var string|null
	 */
	private ?string $formatted_date;

	/**
	 * Private: use the named constructors.
	 *
	 * @param string      $status         Lifecycle status.
	 * @param array|null  $output         Report copy or null.
	 * @param int|null    $completed_at   Epoch seconds or null.
	 * @param string|null $formatted_date Formatted timestamp or null.
	 */
	private function __construct( string $status, ?array $output, ?int $completed_at, ?string $formatted_date ) {
		$this->status         = $status;
		$this->output         = $output;
		$this->completed_at   = $completed_at;
		$this->formatted_date = $formatted_date;
	}

	/**
	 * A passed run, stamped now.
	 *
	 * @param string      $heading One-line summary (markdown, em/strong only).
	 * @param string|null $content Optional rich body markdown.
	 * @return self
	 */
	public static function passed( string $heading, ?string $content = null ): self {
		return self::terminal( 'passed', $heading, $content );
	}

	/**
	 * A failed run, stamped now.
	 *
	 * @param string      $heading One-line summary (markdown, em/strong only).
	 * @param string|null $content Optional rich body markdown.
	 * @return self
	 */
	public static function failed( string $heading, ?string $content = null ): self {
		return self::terminal( 'failed', $heading, $content );
	}

	/**
	 * An in-flight listen run.
	 *
	 * @return self
	 */
	public static function running(): self {
		return new self( 'running', null, null, null );
	}

	/**
	 * The reset state (cancelled listen, never run).
	 *
	 * @return self
	 */
	public static function not_run(): self {
		return new self( 'not_run', null, null, null );
	}

	/**
	 * Build a terminal result with a now() timestamp pair.
	 *
	 * @param string      $status  passed|failed.
	 * @param string      $heading Non-empty heading.
	 * @param string|null $content Optional body.
	 * @return self
	 * @throws InvalidArgumentException When the heading is empty.
	 */
	private static function terminal( string $status, string $heading, ?string $content ): self {
		if ( '' === trim( $heading ) ) {
			throw new InvalidArgumentException( 'A terminal run result requires a non-empty heading.' );
		}

		$output = array( 'heading' => $heading );
		if ( null !== $content && '' !== trim( $content ) ) {
			$output['content'] = $content;
		}

		// Epoch seconds, never current_time('timestamp') — the FE renders live relative time.
		$now = time();

		$formatted = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $now );

		return new self(
			$status,
			$output,
			$now,
			is_string( $formatted ) ? $formatted : gmdate( 'Y-m-d H:i:s', $now )
		);
	}

	/**
	 * The lifecycle status.
	 *
	 * @return string
	 */
	public function get_status(): string {
		return $this->status;
	}

	/**
	 * Whether the run reached a terminal status.
	 *
	 * @return bool
	 */
	public function is_terminal(): bool {
		return in_array( $this->status, array( 'passed', 'failed' ), true );
	}

	/**
	 * The wire projection, presence rules applied.
	 *
	 * The timestamp keys are optional on the wire and must never be null —
	 * a hydrated legacy row may lack them, so they are omitted when unset.
	 *
	 * @return array
	 */
	public function to_wire(): array {
		if ( ! $this->is_terminal() ) {
			return array( 'status' => $this->status );
		}

		$wire = array(
			'status' => $this->status,
			'output' => $this->output,
		);

		if ( null !== $this->completed_at ) {
			$wire['completed_at'] = $this->completed_at;
		}
		if ( null !== $this->formatted_date ) {
			$wire['formatted_date'] = $this->formatted_date;
		}

		return $wire;
	}

	/**
	 * The storage projection. Deliberately its own literal — the stored row
	 * and the wire shape are independent contracts even while identical.
	 *
	 * @return array
	 */
	public function to_stored(): array {
		if ( ! $this->is_terminal() ) {
			return array( 'status' => $this->status );
		}

		$stored = array(
			'status' => $this->status,
			'output' => $this->output,
		);

		if ( null !== $this->completed_at ) {
			$stored['completed_at'] = $this->completed_at;
		}
		if ( null !== $this->formatted_date ) {
			$stored['formatted_date'] = $this->formatted_date;
		}

		return $stored;
	}

	/**
	 * Rebuild from a stored row, failing safe: a row that cannot produce a
	 * wire-valid result (unknown status, terminal without a usable heading)
	 * hydrates as not_run, so a corrupted postmeta row degrades to "not
	 * run" instead of emitting a shape the FE contract rejects. Output is
	 * rebuilt to exactly heading + optional content.
	 *
	 * @param array $row Stored run row.
	 * @return self
	 */
	public static function from_stored( array $row ): self {
		$status = (string) ( $row['status'] ?? '' );

		if ( ! in_array( $status, array( 'passed', 'failed' ), true ) ) {
			return self::not_run();
		}

		$output  = isset( $row['output'] ) && is_array( $row['output'] ) ? $row['output'] : array();
		$heading = isset( $output['heading'] ) && is_string( $output['heading'] ) ? $output['heading'] : '';

		if ( '' === trim( $heading ) ) {
			return self::not_run();
		}

		$clean_output = array( 'heading' => $heading );
		if ( isset( $output['content'] ) && is_string( $output['content'] ) && '' !== trim( $output['content'] ) ) {
			$clean_output['content'] = $output['content'];
		}

		$completed_at = isset( $row['completed_at'] ) && is_numeric( $row['completed_at'] ) && 0 < (int) $row['completed_at'] ? (int) $row['completed_at'] : null;
		$formatted    = isset( $row['formatted_date'] ) && is_string( $row['formatted_date'] ) && '' !== $row['formatted_date'] ? $row['formatted_date'] : null;

		return new self( $status, $clean_output, $completed_at, $formatted );
	}
}
