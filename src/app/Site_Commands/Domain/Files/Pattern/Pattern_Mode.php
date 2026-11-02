<?php
/**
 * The grep pattern syntax.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Pattern;

/**
 * GNU basic, GNU extended, or fixed strings.
 */
final class Pattern_Mode {

	/** GNU basic regular expressions, grep's default. */
	public const BASIC = 'basic';

	/** GNU extended regular expressions, grep -E. */
	public const EXTENDED = 'extended';

	/** Fixed strings, grep -F. */
	public const FIXED = 'fixed';
}
