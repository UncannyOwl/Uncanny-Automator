<?php
/**
 * The per-call bounds of a file command.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Budget;

/**
 * The per-call bounds. Immutable. Defaults sit under a 256M PHP memory
 * limit and a 10 second caller timeout.
 */
final class Limits {

	/**
	 * Most bytes a command prints by default. site_command returns at most
	 * 60000 bytes (Output_Budget) and cuts the end off anything longer, so
	 * this stays well under it: the output, then the partial notices and
	 * the exit code the runners add after it, always reach the caller.
	 */
	public const DEFAULT_MAX_OUTPUT_BYTES = 49152;

	/**
	 * Most bytes of output.
	 *
	 * @var int
	 */
	private int $max_output_bytes;

	/**
	 * Most lines of output.
	 *
	 * @var int
	 */
	private int $max_output_lines;

	/**
	 * Largest file cat prints.
	 *
	 * @var int
	 */
	private int $max_cat_file_bytes;

	/**
	 * Most files one call may visit.
	 *
	 * @var int
	 */
	private int $max_files_scanned;

	/**
	 * Most grep matches.
	 *
	 * @var int
	 */
	private int $max_grep_matches;

	/**
	 * Most grep context lines.
	 *
	 * @var int
	 */
	private int $max_context_lines;

	/**
	 * Most lines tail reads.
	 *
	 * @var int
	 */
	private int $max_tail_lines;

	/**
	 * Most lines sed prints.
	 *
	 * @var int
	 */
	private int $max_sed_lines;

	/**
	 * Largest file a checksum reads.
	 *
	 * @var int
	 */
	private int $max_hash_file_bytes;

	/**
	 * Wall time one call may use.
	 *
	 * @var float
	 */
	private float $wall_time_seconds;

	/**
	 * Memory kept free below PHP's limit.
	 *
	 * @var int
	 */
	private int $memory_headroom_bytes;

	/**
	 * Set the bounds.
	 *
	 * @param int   $max_output_bytes      Most bytes of output.
	 * @param int   $max_output_lines      Most lines of output.
	 * @param int   $max_cat_file_bytes    Largest file cat prints.
	 * @param int   $max_files_scanned     Most files one call may visit.
	 * @param int   $max_grep_matches      Most grep matches.
	 * @param int   $max_context_lines     Most grep context lines.
	 * @param int   $max_tail_lines        Most lines tail reads.
	 * @param int   $max_sed_lines         Most lines sed prints.
	 * @param int   $max_hash_file_bytes   Largest file a checksum reads.
	 * @param float $wall_time_seconds     Wall time one call may use.
	 * @param int   $memory_headroom_bytes Memory kept free below PHP's limit.
	 */
	public function __construct(
		int $max_output_bytes = self::DEFAULT_MAX_OUTPUT_BYTES,
		int $max_output_lines = 2000,
		int $max_cat_file_bytes = 262144,
		int $max_files_scanned = 150000,
		int $max_grep_matches = 2000,
		int $max_context_lines = 50,
		int $max_tail_lines = 5000,
		int $max_sed_lines = 2000,
		int $max_hash_file_bytes = 67108864,
		float $wall_time_seconds = 5.0,
		int $memory_headroom_bytes = 16777216
	) {
		$this->max_output_bytes      = $max_output_bytes;
		$this->max_output_lines      = $max_output_lines;
		$this->max_cat_file_bytes    = $max_cat_file_bytes;
		$this->max_files_scanned     = $max_files_scanned;
		$this->max_grep_matches      = $max_grep_matches;
		$this->max_context_lines     = $max_context_lines;
		$this->max_tail_lines        = $max_tail_lines;
		$this->max_sed_lines         = $max_sed_lines;
		$this->max_hash_file_bytes   = $max_hash_file_bytes;
		$this->wall_time_seconds     = $wall_time_seconds;
		$this->memory_headroom_bytes = $memory_headroom_bytes;
	}

	/**
	 * The default bounds.
	 *
	 * @return self
	 */
	public static function defaults(): self {
		return new self();
	}

	/**
	 * Limits for a pipeline stage whose output feeds the next stage rather
	 * than the model. Sixty-four times the bytes, lines, and matches;
	 * everything else unchanged.
	 *
	 * @return self
	 */
	public function for_intermediate_stage(): self {
		return new self(
			$this->max_output_bytes * 64,
			$this->max_output_lines * 64,
			$this->max_cat_file_bytes,
			$this->max_files_scanned,
			$this->max_grep_matches * 64,
			$this->max_context_lines,
			$this->max_tail_lines,
			$this->max_sed_lines,
			$this->max_hash_file_bytes,
			$this->wall_time_seconds,
			$this->memory_headroom_bytes
		);
	}

	/**
	 * The same limits with another wall time, for a command that waits on
	 * the network before it reads.
	 *
	 * @param float $seconds Wall time one call may use.
	 * @return self
	 */
	public function with_wall_time_seconds( float $seconds ): self {
		$copy                    = clone $this;
		$copy->wall_time_seconds = $seconds;

		return $copy;
	}

	/**
	 * Most bytes of output.
	 *
	 * @return int
	 */
	public function max_output_bytes(): int {
		return $this->max_output_bytes;
	}

	/**
	 * Most lines of output.
	 *
	 * @return int
	 */
	public function max_output_lines(): int {
		return $this->max_output_lines;
	}

	/**
	 * Largest file cat prints.
	 *
	 * @return int
	 */
	public function max_cat_file_bytes(): int {
		return $this->max_cat_file_bytes;
	}

	/**
	 * Most files one call may visit.
	 *
	 * @return int
	 */
	public function max_files_scanned(): int {
		return $this->max_files_scanned;
	}

	/**
	 * Most grep matches.
	 *
	 * @return int
	 */
	public function max_grep_matches(): int {
		return $this->max_grep_matches;
	}

	/**
	 * Most grep context lines.
	 *
	 * @return int
	 */
	public function max_context_lines(): int {
		return $this->max_context_lines;
	}

	/**
	 * Most lines tail reads.
	 *
	 * @return int
	 */
	public function max_tail_lines(): int {
		return $this->max_tail_lines;
	}

	/**
	 * Most lines sed prints.
	 *
	 * @return int
	 */
	public function max_sed_lines(): int {
		return $this->max_sed_lines;
	}

	/**
	 * Largest file a checksum reads.
	 *
	 * @return int
	 */
	public function max_hash_file_bytes(): int {
		return $this->max_hash_file_bytes;
	}

	/**
	 * Wall time one call may use.
	 *
	 * @return float
	 */
	public function wall_time_seconds(): float {
		return $this->wall_time_seconds;
	}

	/**
	 * Memory kept free below PHP's limit.
	 *
	 * @return int
	 */
	public function memory_headroom_bytes(): int {
		return $this->memory_headroom_bytes;
	}
}
