<?php
/**
 * Where the readable roots of file commands come from.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Ports\Files;

/**
 * Where the readable roots come from. WordPress in production, a temporary
 * directory in tests.
 */
interface Sandbox_Roots {

	/**
	 * Absolute, canonical directories. The first is the base for relative
	 * paths.
	 *
	 * @return string[]
	 */
	public function roots(): array;
}
