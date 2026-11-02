<?php
/**
 * The find -size comparison.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Commands\Support;

/**
 * find's `-size` with GNU rounding: the file size is rounded up to the
 * unit before comparison, except for `c` (bytes).
 */
final class Size_Test {

	/** Bytes per unit; no suffix means 512-byte blocks. */
	private const UNITS = array(
		'c' => 1,
		'k' => 1024,
		'M' => 1048576,
		'G' => 1073741824,
		'b' => 512,
		'w' => 2,
	);

	/**
	 * "+", "-", or "" for exactly.
	 *
	 * @var string
	 */
	private string $comparison;

	/**
	 * The number of units compared with.
	 *
	 * @var int
	 */
	private int $value;

	/**
	 * Bytes per unit.
	 *
	 * @var int
	 */
	private int $unit;

	/**
	 * Hold the parsed test.
	 *
	 * @param string $comparison "+", "-", or "".
	 * @param int    $value      The number of units compared with.
	 * @param int    $unit       Bytes per unit.
	 */
	private function __construct( string $comparison, int $value, int $unit ) {
		$this->comparison = $comparison;
		$this->value      = $value;
		$this->unit       = $unit;
	}

	/**
	 * Parse `[+-]n[ckMGbw]`, or null when malformed.
	 *
	 * @param string $spec The test as given.
	 * @return self|null
	 */
	public static function parse( string $spec ): ?self {
		if ( ! preg_match( '/^([+-]?)(\d+)([ckMGbw]?)$/', trim( $spec ), $match ) ) {
			return null;
		}
		$unit = '' === $match[3] ? 'b' : $match[3];

		return new self( $match[1], (int) $match[2], self::UNITS[ $unit ] );
	}

	/**
	 * Whether a file of this size passes.
	 *
	 * @param int $bytes Size in bytes.
	 * @return bool
	 */
	public function accepts( int $bytes ): bool {
		$units = 1 === $this->unit ? $bytes : (int) ceil( $bytes / $this->unit );

		switch ( $this->comparison ) {
			case '+':
				return $units > $this->value;
			case '-':
				return $units < $this->value;
			default:
				return $units === $this->value;
		}
	}
}
