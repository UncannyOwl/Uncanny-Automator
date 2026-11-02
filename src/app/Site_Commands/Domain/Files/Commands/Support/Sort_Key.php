<?php
/**
 * One GNU sort key: where it starts and ends in a line, and how it compares.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Commands\Support;

/**
 * A key as GNU sort reads `-k POS1[,POS2]`, where a position is
 * `F[.C][OPTS]`: field F counted from 1, character C within it counted
 * from 1 (0 in POS2 means the end of the field), and the ordering letters
 * b (skip leading blanks), f (fold case), h (human sizes), n (numbers) and
 * r (reverse). Without POS2 the key runs to the end of the line.
 *
 * Fields are split as GNU splits them: with -t at each separator
 * character, otherwise at each change from a non-blank to a blank, so a
 * field keeps the blanks before it. Comparison is in the C locale.
 */
final class Sort_Key {

	/**
	 * Unit letters of human sizes, in increasing order.
	 */
	private const UNITS = 'KMGTPEZYRQ';

	/**
	 * Fields before the key, counted from zero.
	 *
	 * @var int
	 */
	private int $start_field;

	/**
	 * Characters skipped within the start field, counted from zero.
	 *
	 * @var int
	 */
	private int $start_char;

	/**
	 * The end field counted from zero, or null for the end of the line.
	 *
	 * @var int|null
	 */
	private ?int $end_field;

	/**
	 * Characters kept of the end field; zero for all of it.
	 *
	 * @var int
	 */
	private int $end_char;

	/**
	 * Ordering letters: any of b, f, h, n, r, plus B for blanks skipped at the end position.
	 *
	 * @var string
	 */
	private string $flags;

	/**
	 * Hold a key.
	 *
	 * @param int      $start_field Fields before the key, from zero.
	 * @param int      $start_char  Characters skipped in the start field, from zero.
	 * @param int|null $end_field   End field from zero, or null for the end of the line.
	 * @param int      $end_char    Characters kept of the end field; zero for all.
	 * @param string   $flags       Ordering letters.
	 */
	private function __construct( int $start_field, int $start_char, ?int $end_field, int $end_char, string $flags ) {
		$this->start_field = $start_field;
		$this->start_char  = $start_char;
		$this->end_field   = $end_field;
		$this->end_char    = $end_char;
		$this->flags       = $flags;
	}

	/**
	 * The whole line, compared with the given ordering letters.
	 *
	 * @param string $flags Ordering letters.
	 * @return self
	 */
	public static function whole_line( string $flags ): self {
		return new self( 0, 0, null, 0, $flags );
	}

	/**
	 * A key from its -k definition, or null when GNU would refuse it.
	 *
	 * @param string $definition Such as "2", "2,2n", "3.2,3.5" or "1rn".
	 * @return self|null
	 */
	public static function parse( string $definition ): ?self {
		if ( 1 !== preg_match( '/^(\d+)(?:\.(\d+))?([bfhnr]*)(?:,(\d+)(?:\.(\d+))?([bfhnr]*))?$/', $definition, $parts ) ) {
			return null;
		}
		$start_field = (int) $parts[1];
		$start_char  = isset( $parts[2] ) && '' !== $parts[2] ? (int) $parts[2] : 1;
		if ( $start_field < 1 || $start_char < 1 ) {
			return null; // GNU: field number is zero, or character offset is zero.
		}
		$flags     = $parts[3] ?? '';
		$end_field = null;
		$end_char  = 0;
		if ( isset( $parts[4] ) && '' !== $parts[4] ) {
			$end_field = (int) $parts[4] - 1;
			if ( $end_field < 0 ) {
				return null;
			}
			$end_char = isset( $parts[5] ) && '' !== $parts[5] ? (int) $parts[5] : 0;
			$end      = $parts[6] ?? '';
			// b after POS2 skips blanks at the end position only.
			$flags .= str_replace( 'b', 'B', $end );
		}

		return new self( $start_field - 1, $start_char - 1, $end_field, $end_char, $flags );
	}

	/**
	 * Whether the key names its own ordering, so the global options do not apply to it.
	 *
	 * @return bool
	 */
	public function has_ordering(): bool {
		return '' !== $this->flags;
	}

	/**
	 * The same key with these ordering letters.
	 *
	 * @param string $flags Ordering letters.
	 * @return self
	 */
	public function with_flags( string $flags ): self {
		$copy        = clone $this;
		$copy->flags = $flags;

		return $copy;
	}

	/**
	 * Compare two lines by this key.
	 *
	 * @param string      $a         One line, without its newline.
	 * @param string      $b         Another line, without its newline.
	 * @param string|null $separator The -t character, or null for blank-separated fields.
	 * @return int
	 */
	public function compare( string $a, string $b, ?string $separator ): int {
		$x = $this->extract( $a, $separator );
		$y = $this->extract( $b, $separator );
		if ( $this->has( 'h' ) ) {
			$result = self::unit_order( $x ) <=> self::unit_order( $y );
			if ( 0 === $result ) {
				$result = self::compare_numbers( $x, $y );
			}
		} elseif ( $this->has( 'n' ) ) {
			$result = self::compare_numbers( $x, $y );
		} elseif ( $this->has( 'f' ) ) {
			$result = strcmp( strtoupper( $x ), strtoupper( $y ) );
		} else {
			$result = strcmp( $x, $y );
		}
		$result = $result <=> 0;

		return $this->has( 'r' ) ? -$result : $result;
	}

	/**
	 * The text of the key in a line, as GNU's begfield and limfield find it.
	 *
	 * @param string      $line      The line, without its newline.
	 * @param string|null $separator The -t character, or null.
	 * @return string
	 */
	public function extract( string $line, ?string $separator ): string {
		$length = strlen( $line );

		$start = 0;
		for ( $count = $this->start_field; $start < $length && $count > 0; --$count ) {
			$start = self::field_end( $line, $start, $separator );
			if ( null !== $separator && $start < $length ) {
				++$start;
			}
		}
		if ( $this->has( 'b' ) ) {
			$start = self::skip_blanks( $line, $start );
		}
		$start = min( $length, $start + $this->start_char );

		$end = $length;
		if ( null !== $this->end_field ) {
			$end = 0;
			// A character count of zero means all of the end field.
			$count = 0 === $this->end_char ? $this->end_field + 1 : $this->end_field;
			while ( $end < $length && $count > 0 ) {
				--$count;
				$end = self::field_end( $line, $end, $separator );
				if ( null !== $separator && $end < $length && ( $count > 0 || 0 !== $this->end_char ) ) {
					++$end;
				}
			}
			if ( 0 !== $this->end_char ) {
				if ( $this->has( 'B' ) ) {
					$end = self::skip_blanks( $line, $end );
				}
				$end = min( $length, $end + $this->end_char );
			}
		}

		return $end > $start ? substr( $line, $start, $end - $start ) : '';
	}

	/**
	 * The offset where the field at this offset ends: the next separator,
	 * or without one the end of the blanks and then the non-blanks.
	 *
	 * @param string      $line      The line.
	 * @param int         $offset    Where the field starts.
	 * @param string|null $separator The -t character, or null.
	 * @return int
	 */
	private static function field_end( string $line, int $offset, ?string $separator ): int {
		$length = strlen( $line );
		if ( null !== $separator ) {
			$next = strpos( $line, $separator, $offset );

			return false === $next ? $length : $next;
		}
		$offset = self::skip_blanks( $line, $offset );
		while ( $offset < $length && ' ' !== $line[ $offset ] && "\t" !== $line[ $offset ] ) {
			++$offset;
		}

		return $offset;
	}

	/**
	 * The offset after the blanks at this one.
	 *
	 * @param string $line   The line.
	 * @param int    $offset Where to start.
	 * @return int
	 */
	private static function skip_blanks( string $line, int $offset ): int {
		$length = strlen( $line );
		while ( $offset < $length && ( ' ' === $line[ $offset ] || "\t" === $line[ $offset ] ) ) {
			++$offset;
		}

		return $offset;
	}

	/**
	 * Whether an ordering letter is set.
	 *
	 * @param string $flag The letter.
	 * @return bool
	 */
	private function has( string $flag ): bool {
		return false !== strpos( $this->flags, $flag );
	}

	/**
	 * Compare the leading numbers of two keys as GNU's strnumcmp does: by
	 * their digits, never through floating point, so 9007199254740993 and
	 * 9007199254740992 differ. Leading blanks are skipped; a key without a
	 * number, "-" and "-0" are all zero; leading zeros of the integer part
	 * and trailing zeros of the fraction do not count.
	 *
	 * @param string $a One key.
	 * @param string $b Another key.
	 * @return int
	 */
	private static function compare_numbers( string $a, string $b ): int {
		list( $a_negative, $a_integer, $a_fraction ) = self::number( $a );
		list( $b_negative, $b_integer, $b_fraction ) = self::number( $b );
		if ( $a_negative !== $b_negative ) {
			return $a_negative ? -1 : 1;
		}
		$result = strlen( $a_integer ) <=> strlen( $b_integer );
		if ( 0 === $result ) {
			$result = strcmp( $a_integer, $b_integer ) <=> 0;
		}
		if ( 0 === $result ) {
			$result = strcmp( $a_fraction, $b_fraction ) <=> 0;
		}

		return $a_negative ? -$result : $result;
	}

	/**
	 * The leading number of a key as -n reads it: whether it is below zero,
	 * the integer digits without leading zeros, and the fraction digits
	 * without trailing zeros. Zero is never negative.
	 *
	 * @param string $text The key.
	 * @return array{0: bool, 1: string, 2: string}
	 */
	private static function number( string $text ): array {
		preg_match( '/^[ \t]*(-?)(\d*)(?:\.(\d*))?/', $text, $found );
		$integer  = ltrim( $found[2] ?? '', '0' );
		$fraction = rtrim( $found[3] ?? '', '0' );

		return array( '-' === ( $found[1] ?? '' ) && ( '' !== $integer || '' !== $fraction ), $integer, $fraction );
	}

	/**
	 * The unit a human size is ordered by first, as GNU's find_unit_order
	 * reads it: 0 without a unit or for a zero value, 1 for K (or k), 2 for
	 * M, and so on, negated for a negative number.
	 *
	 * @param string $text The key, such as "4.0K" or "12M".
	 * @return int
	 */
	private static function unit_order( string $text ): int {
		if ( 1 !== preg_match( '/^[ \t]*(-?)(\d*(?:\.\d*)?)([kKMGTPEZYRQ]?)/', $text, $found ) || '' === $found[3] || '' === trim( $found[2], '0.' ) ) {
			return 0;
		}
		$order = (int) strpos( self::UNITS, strtoupper( $found[3] ) ) + 1;

		return '-' === $found[1] ? -$order : $order;
	}
}
