<?php
/**
 * GNU -h size formatting.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Commands\Support;

/**
 * GNU `-h` formatting, as `ls -lh` and `du -h` print sizes: powers of 1024,
 * always rounded up, one decimal below ten. A size that rounds up to 1024
 * of one unit is printed as 1.0 of the next, as GNU's human_readable()
 * does with its default ceiling rounding.
 */
final class Human_Size {

	/**
	 * The unit suffixes, from 1024 bytes up. PHP's largest integer is below
	 * 8 EiB, so no size needs a larger one.
	 */
	private const UNITS = array( 'K', 'M', 'G', 'T', 'P', 'E' );

	/**
	 * The size as GNU -h prints it, such as 4.0K, 12M or 1.0M for 1,047,553 bytes.
	 *
	 * Integer arithmetic, as GNU's: the integer part, the first decimal and
	 * whether anything was left below it are carried through each division,
	 * so no floating-point rounding can move a size to the next tenth.
	 *
	 * @param int $bytes Size in bytes.
	 * @return string
	 */
	public static function format( int $bytes ): string {
		if ( $bytes < 1024 ) {
			return (string) $bytes;
		}

		$units    = count( self::UNITS );
		$amount   = $bytes;
		$tenths   = 0;
		$inexact  = false;
		$exponent = 0;
		do {
			$remainder = ( $amount % 1024 ) * 10 + $tenths;
			$inexact   = $inexact || 0 !== $remainder % 1024;
			$amount    = intdiv( $amount, 1024 );
			$tenths    = intdiv( $remainder, 1024 );
			++$exponent;
		} while ( $amount >= 1024 && $exponent < $units );

		if ( $amount < 10 ) {
			if ( $inexact ) {
				++$tenths;
				$inexact = false;
				if ( 10 === $tenths ) {
					++$amount;
					$tenths = 0;
				}
			}
			if ( $amount < 10 ) {
				return $amount . '.' . $tenths . self::UNITS[ $exponent - 1 ];
			}
		}

		if ( $tenths > 0 || $inexact ) {
			++$amount;
			if ( 1024 === $amount && $exponent < $units ) {
				return '1.0' . self::UNITS[ $exponent ];
			}
		}

		return $amount . self::UNITS[ $exponent - 1 ];
	}
}
