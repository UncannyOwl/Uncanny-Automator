<?php
/**
 * The type of one file command option.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Command;

/**
 * The JSON Schema type an option takes. The values are what the tool
 * contract prints as the parameter type.
 */
final class Option_Type {

	/** A true or false switch. */
	public const FLAG = 'boolean';

	/** A whole number within bounds. */
	public const INTEGER = 'integer';

	/** One string, optionally from a fixed list. */
	public const TEXT = 'string';

	/** A bounded list of strings. */
	public const TEXT_LIST = 'array';
}
