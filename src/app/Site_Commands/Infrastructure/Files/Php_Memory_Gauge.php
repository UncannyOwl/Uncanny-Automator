<?php
/**
 * PHP's memory use and limit, so file commands stop before a fatal error.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Infrastructure\Files;

use Uncanny_Automator\App\Site_Commands\Domain\Files\Budget\Memory_Gauge;

/**
 * How much memory the process has used and may use.
 */
final class Php_Memory_Gauge implements Memory_Gauge {

	/**
	 * Bytes in use.
	 *
	 * @return int
	 */
	public function used(): int {
		return memory_get_usage( true );
	}

	/**
	 * The limit in bytes; zero when unlimited.
	 *
	 * @return int
	 */
	public function limit(): int {
		return self::bytes( (string) ini_get( 'memory_limit' ) );
	}

	/**
	 * "256M", "1G", "-1", "" as PHP reads them. Zero means unlimited.
	 *
	 * @param string $ini The ini value.
	 * @return int
	 */
	public static function bytes( string $ini ): int {
		$ini = trim( $ini );
		if ( '' === $ini || '-1' === $ini ) {
			return 0;
		}
		$unit   = strtoupper( substr( $ini, -1 ) );
		$number = (int) $ini;
		if ( $number <= 0 ) {
			return 0;
		}
		switch ( $unit ) {
			case 'G':
				return $number * 1073741824;
			case 'M':
				return $number * 1048576;
			case 'K':
				return $number * 1024;
			default:
				return $number;
		}
	}
}
