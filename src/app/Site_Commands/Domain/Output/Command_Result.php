<?php
/**
 * The printed result of one site command.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Output;

/**
 * Success or failure, the printed text, and whether it was shortened. A
 * truncated result's text must end with a notice saying so; Output_Budget
 * is the code that creates them.
 */
final class Command_Result {

	private string $output;
	private bool $success;
	private bool $truncated;
	private string $stats = '';

	/**
	 * Use the named constructors.
	 *
	 * @param string $output    Printed text.
	 * @param bool   $success   Whether the command succeeded.
	 * @param bool   $truncated Whether the text was shortened.
	 */
	private function __construct( string $output, bool $success, bool $truncated ) {
		$this->output    = $output;
		$this->success   = $success;
		$this->truncated = $truncated;
	}

	/**
	 * A complete successful result.
	 *
	 * @param string $output Printed text.
	 * @return self
	 */
	public static function success( string $output ): self {
		return new self( $output, true, false );
	}

	/**
	 * A successful result that was shortened; the text includes the notice.
	 *
	 * @param string $output Printed text, ending with the truncation notice.
	 * @return self
	 */
	public static function truncated( string $output ): self {
		return new self( $output, true, true );
	}

	/**
	 * A failure whose output is printed as it is, such as a table of items
	 * that all failed.
	 *
	 * @param string $output    Printed text.
	 * @param bool   $truncated Whether the text was shortened; it then ends with the notice.
	 * @return self
	 */
	public static function failed( string $output, bool $truncated = false ): self {
		return new self( $output, false, $truncated );
	}

	/**
	 * A failure, printed the way WP-CLI prints one.
	 *
	 * @param string $message Reason.
	 * @return self
	 */
	public static function error( string $message ): self {
		return new self( 'Error: ' . $message, false, false );
	}

	/**
	 * Printed text.
	 *
	 * @return string
	 */
	public function output(): string {
		return $this->output;
	}

	/**
	 * Whether the command succeeded.
	 *
	 * @return bool
	 */
	public function is_success(): bool {
		return $this->success;
	}

	/**
	 * Whether the text was shortened.
	 *
	 * @return bool
	 */
	public function is_truncated(): bool {
		return $this->truncated;
	}

	/**
	 * The same result with stats, kept apart from the output so that JSON
	 * output stays valid JSON.
	 *
	 * @param string $stats Stats lines.
	 * @return self
	 */
	public function with_stats( string $stats ): self {
		$result        = clone $this;
		$result->stats = $stats;

		return $result;
	}

	/**
	 * Stats about the output, such as how many items exist; empty when there are none.
	 *
	 * @return string
	 */
	public function stats(): string {
		return $this->stats;
	}
}
