<?php
/**
 * The two output streams of a file command.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Transcript;

/**
 * Standard output or standard error.
 */
final class Stream {

	/** Standard output. */
	public const STDOUT = 'stdout';

	/** Standard error. */
	public const STDERR = 'stderr';
}
