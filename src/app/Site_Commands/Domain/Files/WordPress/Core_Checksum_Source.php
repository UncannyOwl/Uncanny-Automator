<?php
/**
 * Where the official checksums of WordPress core come from.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\WordPress;

/**
 * The checksum list of the installed release.
 */
interface Core_Checksum_Source {

	/**
	 * Fetch the list within this many seconds.
	 *
	 * @param float $seconds_budget Seconds the fetch may take in all.
	 * @return Core_Checksums|null Null when WordPress is not running here.
	 */
	public function core_checksums( float $seconds_budget ): ?Core_Checksums;
}
