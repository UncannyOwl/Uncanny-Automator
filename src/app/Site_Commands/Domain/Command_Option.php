<?php
/**
 * One --option a site command accepts.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command errors are plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Domain;

/**
 * A flag (--extra), a free value (--fields=a,b) or a choice from a fixed
 * list (--status=active). Invalid values are refused, never coerced.
 */
final class Command_Option {

	private const FLAG   = 'flag';
	private const VALUE  = 'value';
	private const CHOICE = 'choice';
	private const NUMBER = 'number';

	private string $name;
	private string $description;
	private string $kind;

	/**
	 * Allowed values for a choice.
	 *
	 * @var string[]
	 */
	private array $choices;

	private int $min = 0;
	private int $max = 0;

	/**
	 * Validate the option shape once, where it is declared.
	 *
	 * @param string   $name        Option name without dashes.
	 * @param string   $description One-sentence description.
	 * @param string   $kind        flag, value or choice.
	 * @param string[] $choices     Allowed values for a choice.
	 *
	 * @throws \InvalidArgumentException When the declaration is malformed.
	 */
	private function __construct( string $name, string $description, string $kind, array $choices ) {
		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9_-]*$/', $name ) ) {
			throw new \InvalidArgumentException( 'Invalid site command option name: ' . $name );
		}
		if ( self::CHOICE === $kind && ( array() === $choices || array_filter( $choices, 'is_string' ) !== $choices ) ) {
			throw new \InvalidArgumentException( 'A choice option needs string values: ' . $name );
		}

		$this->name        = $name;
		$this->description = $description;
		$this->kind        = $kind;
		$this->choices     = array_values( $choices );
	}

	/**
	 * An option without a value, such as --extra.
	 *
	 * @param string $name        Option name.
	 * @param string $description Description.
	 * @return self
	 */
	public static function flag( string $name, string $description ): self {
		return new self( $name, $description, self::FLAG, array() );
	}

	/**
	 * An option with a free value, such as --fields=name,status.
	 *
	 * @param string $name        Option name.
	 * @param string $description Description.
	 * @return self
	 */
	public static function value( string $name, string $description ): self {
		return new self( $name, $description, self::VALUE, array() );
	}

	/**
	 * An option with one value from a fixed list, such as --status=active.
	 *
	 * @param string   $name        Option name.
	 * @param string   $description Description.
	 * @param string[] $choices     Allowed values.
	 * @return self
	 */
	public static function choice( string $name, string $description, array $choices ): self {
		return new self( $name, $description, self::CHOICE, $choices );
	}

	/**
	 * A whole number within a range, such as --posts_per_page=20.
	 *
	 * @param string $name        Option name.
	 * @param string $description Description.
	 * @param int    $min         Smallest accepted value.
	 * @param int    $max         Largest accepted value.
	 * @return self
	 */
	public static function number( string $name, string $description, int $min, int $max ): self {
		$option      = new self( $name, $description, self::NUMBER, array() );
		$option->min = $min;
		$option->max = $max;

		return $option;
	}

	/**
	 * Option name without dashes.
	 *
	 * @return string
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * Description, followed by the allowed values for a choice.
	 *
	 * @return string
	 */
	public function description(): string {
		if ( self::CHOICE !== $this->kind ) {
			return $this->description;
		}

		return $this->description . ' One of: ' . implode( ', ', $this->choices ) . '.';
	}

	/**
	 * Synopsis fragment, such as [--status=<active|inactive>].
	 *
	 * @return string
	 */
	public function synopsis(): string {
		if ( self::FLAG === $this->kind ) {
			return '[--' . $this->name . ']';
		}
		$value = self::CHOICE === $this->kind ? implode( '|', $this->choices ) : $this->name;

		return '[--' . $this->name . '=<' . $value . '>]';
	}

	/**
	 * Refuse a value this option does not accept.
	 *
	 * @param string|bool $value Parsed value: true for --name, false for --no-name.
	 *
	 * @throws Command_Error When the value is not accepted.
	 */
	public function validate( $value ): void {
		if ( self::FLAG === $this->kind ) {
			if ( ! is_bool( $value ) ) {
				throw new Command_Error( sprintf( 'The option --%s takes no value.', $this->name ) );
			}
			return;
		}
		if ( ! is_string( $value ) || '' === $value ) {
			throw new Command_Error( sprintf( 'The option --%1$s needs a value, for example --%1$s=<value>.', $this->name ) );
		}
		if ( self::NUMBER === $this->kind && ( 1 !== preg_match( '/^[0-9]{1,18}$/', $value ) || (int) $value < $this->min || (int) $value > $this->max ) ) {
			throw new Command_Error( sprintf( 'The option --%s must be a whole number from %d to %d.', $this->name, $this->min, $this->max ) );
		}
		if ( self::CHOICE === $this->kind && ! in_array( $value, $this->choices, true ) ) {
			throw new Command_Error( sprintf( 'Invalid value for --%s: %s. Use one of: %s.', $this->name, $value, implode( ', ', $this->choices ) ) );
		}
	}
}
