<?php
/**
 * One validated call of a file command.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Command;

/**
 * One validated call of a command.
 */
final class Invocation {

	/**
	 * The command name.
	 *
	 * @var string
	 */
	private string $command;

	/**
	 * Option values by name, defaults filled in.
	 *
	 * @var array<string, mixed>
	 */
	private array $options;

	/**
	 * Operand values by name, defaults filled in.
	 *
	 * @var array<string, mixed>
	 */
	private array $operands;

	/**
	 * Hold the validated values.
	 *
	 * @param string               $command  The command name.
	 * @param array<string, mixed> $options  Option values by name.
	 * @param array<string, mixed> $operands Operand values by name.
	 */
	public function __construct( string $command, array $options, array $operands ) {
		$this->command  = $command;
		$this->options  = $options;
		$this->operands = $operands;
	}

	/**
	 * The command name.
	 *
	 * @return string
	 */
	public function command(): string {
		return $this->command;
	}

	/**
	 * Whether a flag is set.
	 *
	 * @param string $name Option name.
	 * @return bool
	 */
	public function flag( string $name ): bool {
		return true === ( $this->options[ $name ] ?? false );
	}

	/**
	 * An option value, or null.
	 *
	 * @param string $name Option name.
	 * @return mixed
	 */
	public function option( string $name ) {
		return $this->options[ $name ] ?? null;
	}

	/**
	 * An integer option, or null when unset.
	 *
	 * @param string $name Option name.
	 * @return int|null
	 */
	public function integer( string $name ): ?int {
		$value = $this->options[ $name ] ?? null;

		return null === $value ? null : (int) $value;
	}

	/**
	 * A list option, empty when unset.
	 *
	 * @param string $name Option name.
	 * @return string[]
	 */
	public function list( string $name ): array {
		$value = $this->options[ $name ] ?? array();

		return is_array( $value ) ? $value : array();
	}

	/**
	 * An operand value, or null.
	 *
	 * @param string $name Operand name.
	 * @return mixed
	 */
	public function operand( string $name ) {
		return $this->operands[ $name ] ?? null;
	}

	/**
	 * The same call with one operand replaced, such as path operands after
	 * path-name expansion.
	 *
	 * @param string $name  Operand name.
	 * @param mixed  $value The new value.
	 * @return self
	 */
	public function with_operand( string $name, $value ): self {
		$copy                    = clone $this;
		$copy->operands[ $name ] = $value;

		return $copy;
	}

	/**
	 * A list operand, empty when unset.
	 *
	 * @param string $name Operand name.
	 * @return string[]
	 */
	public function paths( string $name = 'paths' ): array {
		$value = $this->operands[ $name ] ?? array();

		return is_array( $value ) ? $value : array();
	}
}
