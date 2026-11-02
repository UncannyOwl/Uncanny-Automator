<?php
/**
 * Every declared site command, looked up by path.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command errors are plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Domain;

/**
 * Built once from the declarations and shared by running and help, so both
 * see the same commands. A broken declaration fails loudly here instead of
 * silently replacing another command.
 */
final class Command_Index {

	/**
	 * Commands by path, sorted.
	 *
	 * @var array<string, Command_Definition>
	 */
	private array $commands = array();

	/**
	 * Index the declarations.
	 *
	 * @param Command_Definition[] $definitions Declared commands.
	 *
	 * @throws \LogicException When a path is declared twice or has no handler.
	 */
	public function __construct( array $definitions ) {
		foreach ( $definitions as $definition ) {
			$path = $definition->path();
			if ( isset( $this->commands[ $path ] ) ) {
				throw new \LogicException( 'Site command declared twice: ' . $path );
			}
			if ( null === $definition->handler() ) {
				throw new \LogicException( 'Site command has no handler: ' . $path );
			}
			$this->commands[ $path ] = $definition;
		}
		ksort( $this->commands );
	}

	/**
	 * Every command, sorted by path.
	 *
	 * @return array<string, Command_Definition>
	 */
	public function all(): array {
		return $this->commands;
	}

	/**
	 * Commands whose path starts with this group.
	 *
	 * @param string $group First word of a path.
	 * @return array<string, Command_Definition>
	 */
	public function in_group( string $group ): array {
		return array_filter(
			$this->commands,
			static function ( Command_Definition $definition ) use ( $group ): bool {
				return $definition->group() === $group;
			}
		);
	}

	/**
	 * Match the longest declared path at the start of the words.
	 *
	 * @param string[] $words Command words.
	 * @return array{0: Command_Definition, 1: string[]} The command and the words after its path.
	 *
	 * @throws Command_Error When no declared command matches.
	 */
	public function resolve( array $words ): array {
		for ( $length = min( Command_Definition::MAX_PATH_WORDS, count( $words ) ); $length > 0; $length-- ) {
			$path = implode( ' ', array_slice( $words, 0, $length ) );
			if ( isset( $this->commands[ $path ] ) ) {
				return array( $this->commands[ $path ], array_slice( $words, $length ) );
			}
		}

		$known = array_keys( $this->in_group( (string) $words[0] ) );
		if ( array() !== $known ) {
			throw new Command_Error( sprintf( '"%s" is not a supported command. Supported "%s" commands: %s.', implode( ' ', $words ), $words[0], implode( ', ', $known ) ) );
		}

		throw new Command_Error( sprintf( '"%s" is not a supported command. Run "help" to list the commands.', $words[0] ) );
	}
}
