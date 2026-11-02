<?php
/**
 * The find n, +n, -n comparison.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Commands\Support;

/**
 * find's `n`, `+n`, `-n` comparison: exactly, more than, less than.
 */
final class Numeric_Test {

	/**
	 * "+", "-", or "" for exactly.
	 *
	 * @var string
	 */
	private string $comparison;

	/**
	 * The number compared with.
	 *
	 * @var int
	 */
	private int $value;

	/**
	 * Hold the parsed test.
	 *
	 * @param string $comparison "+", "-", or "".
	 * @param int    $value      The number compared with.
	 */
	private function __construct( string $comparison, int $value ) {
		$this->comparison = $comparison;
		$this->value      = $value;
	}

	/**
	 * Parse `n`, `+n` or `-n`, or null when malformed.
	 *
	 * @param string $spec The test as given.
	 * @return self|null
	 */
	public static function parse( string $spec ): ?self {
		if ( ! preg_match( '/^([+-]?)(\d+)$/', trim( $spec ), $match ) ) {
			return null;
		}

		return new self( $match[1], (int) $match[2] );
	}

	/**
	 * Whether the actual number passes.
	 *
	 * @param int $actual The number found.
	 * @return bool
	 */
	public function accepts( int $actual ): bool {
		switch ( $this->comparison ) {
			case '+':
				return $actual > $this->value;
			case '-':
				return $actual < $this->value;
			default:
				return $actual === $this->value;
		}
	}
}
