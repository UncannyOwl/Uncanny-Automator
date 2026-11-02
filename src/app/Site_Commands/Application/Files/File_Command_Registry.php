<?php
/**
 * The file commands this installation offers.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command output is plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Application\Files;

use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\File_Command;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Specification;

/**
 * The commands, by name, in a stable order. Commands are named here by
 * class and created only when one runs, so listing them loads nothing else.
 */
final class File_Command_Registry {

	/**
	 * Command classes by name.
	 *
	 * @var array<string, class-string<File_Command>>
	 */
	private array $classes = array();

	/**
	 * Commands already created, by name.
	 *
	 * @var array<string, File_Command>
	 */
	private array $commands = array();

	/**
	 * Factories for commands that need dependencies, by class.
	 *
	 * @var array<string, callable(): File_Command>
	 */
	private array $factories;

	/**
	 * Constructor.
	 *
	 * @param array<string, class-string<File_Command>> $classes   Command classes by name.
	 * @param array<string, callable(): File_Command>   $factories Factories for commands that need dependencies, by class.
	 *
	 * @throws \LogicException When a name repeats.
	 */
	public function __construct( array $classes, array $factories = array() ) {
		foreach ( $classes as $name => $class ) {
			if ( isset( $this->classes[ $name ] ) ) {
				throw new \LogicException( sprintf( 'Command "%s" is already registered.', $name ) );
			}
			$this->classes[ $name ] = $class;
		}
		ksort( $this->classes, SORT_STRING );
		$this->factories = $factories;
	}

	/**
	 * The command with this name, or null.
	 *
	 * @param string $name Command name.
	 * @return File_Command|null
	 */
	public function get( string $name ): ?File_Command {
		if ( ! isset( $this->classes[ $name ] ) ) {
			return null;
		}
		if ( ! isset( $this->commands[ $name ] ) ) {
			$class                   = $this->classes[ $name ];
			$this->commands[ $name ] = isset( $this->factories[ $class ] ) ? call_user_func( $this->factories[ $class ] ) : new $class();
		}

		return $this->commands[ $name ];
	}

	/**
	 * Whether a command has this name.
	 *
	 * @param string $name Command name.
	 * @return bool
	 */
	public function has( string $name ): bool {
		return isset( $this->classes[ $name ] );
	}

	/**
	 * The command names, sorted.
	 *
	 * @return string[]
	 */
	public function names(): array {
		return array_keys( $this->classes );
	}

	/**
	 * The specification of one command, read from its class without creating it.
	 *
	 * @param string $name Command name.
	 * @return Specification|null
	 */
	public function specification( string $name ): ?Specification {
		if ( ! isset( $this->classes[ $name ] ) ) {
			return null;
		}
		$class = $this->classes[ $name ];

		return $class::specification();
	}
}
