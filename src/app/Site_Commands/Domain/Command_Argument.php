<?php
/**
 * One positional argument a site command accepts.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command errors are plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Domain;

/**
 * Named so help, the synopsis and the "missing argument" error all say the
 * same thing, for example <plugin>.
 */
final class Command_Argument {

	private string $name;
	private string $description;
	private bool $required;

	/**
	 * Validate the argument shape once, where it is declared.
	 *
	 * @param string $name        Argument name.
	 * @param string $description One-sentence description.
	 * @param bool   $required    Whether the command needs it.
	 *
	 * @throws \InvalidArgumentException When the name is malformed.
	 */
	private function __construct( string $name, string $description, bool $required ) {
		if ( 1 !== preg_match( '/^[a-z][a-z0-9_-]*$/', $name ) ) {
			throw new \InvalidArgumentException( 'Invalid site command argument name: ' . $name );
		}

		$this->name        = $name;
		$this->description = $description;
		$this->required    = $required;
	}

	/**
	 * An argument the command cannot run without.
	 *
	 * @param string $name        Argument name.
	 * @param string $description Description.
	 * @return self
	 */
	public static function required( string $name, string $description ): self {
		return new self( $name, $description, true );
	}

	/**
	 * An argument the command can run without.
	 *
	 * @param string $name        Argument name.
	 * @param string $description Description.
	 * @return self
	 */
	public static function optional( string $name, string $description ): self {
		return new self( $name, $description, false );
	}

	/**
	 * Argument name.
	 *
	 * @return string
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description(): string {
		return $this->description;
	}

	/**
	 * Whether the command needs this argument.
	 *
	 * @return bool
	 */
	public function is_required(): bool {
		return $this->required;
	}

	/**
	 * Synopsis fragment: <name> or [<name>].
	 *
	 * @return string
	 */
	public function synopsis(): string {
		return $this->required ? '<' . $this->name . '>' : '[<' . $this->name . '>]';
	}
}
