<?php
/**
 * sha256sum: print SHA256 checksums.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Commands;

/**
 * The SHA256 checksum command.
 */
final class Sha256sum extends Abstract_Checksum {

	/**
	 * The hash() algorithm name.
	 *
	 * @return string
	 */
	protected static function algorithm(): string {
		return 'sha256';
	}

	/**
	 * The command name.
	 *
	 * @return string
	 */
	protected static function command_name(): string {
		return 'sha256sum';
	}
}
