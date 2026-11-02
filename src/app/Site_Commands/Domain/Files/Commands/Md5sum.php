<?php
/**
 * md5sum: print MD5 checksums.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Commands;

/**
 * The MD5 checksum command.
 */
final class Md5sum extends Abstract_Checksum {

	/**
	 * The hash() algorithm name.
	 *
	 * @return string
	 */
	protected static function algorithm(): string {
		return 'md5';
	}

	/**
	 * The command name.
	 *
	 * @return string
	 */
	protected static function command_name(): string {
		return 'md5sum';
	}
}
