<?php
/**
 * One file command to run, with its arguments.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Application\Files;

/**
 * A command name and its arguments, keyed by option and operand name, as
 * the command's specification names them.
 */
final class File_Command_Request {

	/**
	 * The command name, such as "grep".
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * Arguments by option or operand name.
	 *
	 * @var array<string, mixed>
	 */
	private array $arguments;

	/**
	 * Path-name patterns of path operands typed with unquoted wildcards,
	 * by operand name and position.
	 *
	 * @var array<string, array<int, string>>
	 */
	private array $globs;

	/**
	 * Constructor.
	 *
	 * @param string                            $name      The command name.
	 * @param array<string, mixed>              $arguments Arguments by option or operand name.
	 * @param array<string, array<int, string>> $globs     Path-name patterns by operand name and position; see Command_Word.
	 */
	public function __construct( string $name, array $arguments, array $globs = array() ) {
		$this->name      = $name;
		$this->arguments = $arguments;
		$this->globs     = $globs;
	}

	/**
	 * The command name.
	 *
	 * @return string
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * Arguments by option or operand name.
	 *
	 * @return array<string, mixed>
	 */
	public function arguments(): array {
		return $this->arguments;
	}

	/**
	 * Path-name patterns of path operands typed with unquoted wildcards,
	 * by operand name and position. The runner expands them inside the
	 * sandbox before the command runs.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function globs(): array {
		return $this->globs;
	}
}
