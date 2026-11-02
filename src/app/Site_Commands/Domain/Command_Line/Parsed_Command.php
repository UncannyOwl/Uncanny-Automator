<?php
/**
 * A command line split into words and options.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Command_Line;

/**
 * Words are the command path followed by positional arguments; the catalog
 * decides where the path ends. Options are --name=value pairs, with true for
 * a bare --name and false for --no-name.
 */
final class Parsed_Command {

	/** @var string[] */
	private array $words;

	/** @var array<string, string|bool> */
	private array $options;

	/**
	 * @param string[]                   $words   Words in order.
	 * @param array<string, string|bool> $options Options by name.
	 */
	public function __construct( array $words, array $options ) {
		$this->words   = array_values( $words );
		$this->options = $options;
	}

	/**
	 * @return string[]
	 */
	public function words(): array {
		return $this->words;
	}

	/**
	 * @return array<string, string|bool>
	 */
	public function options(): array {
		return $this->options;
	}
}
