<?php
/**
 * The ls -l timestamp.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.DateTime.RestrictedFunctions.date_date -- GNU ls prints server-local time, which is what date() formats.

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Commands\Support;

/**
 * The `ls -l` timestamp: recent files show the time, older ones the year.
 */
final class Ls_Date {

	/** GNU's six months, in seconds. */
	private const SIX_MONTHS = 15778476;

	/**
	 * The timestamp as ls -l prints it.
	 *
	 * @param int $mtime Modification time.
	 * @param int $now   Current Unix time.
	 * @return string
	 */
	public static function format( int $mtime, int $now ): string {
		$recent = ! ( $mtime < $now - self::SIX_MONTHS || $mtime > $now + 3600 );

		return $recent
			? date( 'M', $mtime ) . ' ' . str_pad( (string) (int) date( 'j', $mtime ), 2, ' ', STR_PAD_LEFT ) . ' ' . date( 'H:i', $mtime )
			: date( 'M', $mtime ) . ' ' . str_pad( (string) (int) date( 'j', $mtime ), 2, ' ', STR_PAD_LEFT ) . '  ' . date( 'Y', $mtime );
	}
}
