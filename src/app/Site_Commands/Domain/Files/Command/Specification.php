<?php
/**
 * A file command's contract.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command output is plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Command;

use LogicException;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Invalid_Invocation;

/**
 * A command's contract: name, options, operands, and the text the model
 * reads. One specification per command drives validation, the tool
 * manifest, and the help text.
 */
final class Specification {

	/** No option value needs more; a longer one is a mistake or an attack on the regex engine. */
	public const MAX_TEXT_BYTES = 4096;

	/** The option every file command shares: a per-call working directory. */
	public const CWD = 'cwd';

	/**
	 * The argument key under which the command line passes a usage error it
	 * found, such as a missing option value, as the lines to print. No word
	 * a caller types can make this key: control characters are refused.
	 */
	public const LINE_ERROR = "\0line_error";

	/**
	 * The command name.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * One line for listings.
	 *
	 * @var string
	 */
	private string $summary;

	/**
	 * The text the model reads.
	 *
	 * @var string
	 */
	private string $description;

	/**
	 * Options by name.
	 *
	 * @var array<string, Option>
	 */
	private array $options;

	/**
	 * Operands by name.
	 *
	 * @var array<string, Operand>
	 */
	private array $operands;

	/**
	 * True when the command reads the previous pipeline stage's output in
	 * place of a file, as GNU does when no file is given.
	 *
	 * @var bool
	 */
	private bool $reads_standard_input;

	/**
	 * Index the options and operands, adding cwd to every file command.
	 *
	 * @param string    $name                 The command name.
	 * @param string    $summary              One line for listings.
	 * @param string    $description          The text the model reads.
	 * @param Option[]  $options              The options.
	 * @param Operand[] $operands             The operands.
	 * @param bool      $reads_standard_input True when the command reads the previous pipeline stage's output in place of a file, as GNU does when no file is given.
	 */
	public function __construct( string $name, string $summary, string $description, array $options, array $operands, bool $reads_standard_input = false ) {
		$this->name                 = $name;
		$this->summary              = $summary;
		$this->description          = $description;
		$this->reads_standard_input = $reads_standard_input;

		$indexed_options = array();
		foreach ( $options as $option ) {
			$indexed_options[ $option->name() ] = $option;
		}
		$indexed_operands = array();
		foreach ( $operands as $operand ) {
			$indexed_operands[ $operand->name() ] = $operand;
		}
		if ( ( isset( $indexed_operands['paths'] ) || 'pwd' === $name ) && ! isset( $indexed_options[ self::CWD ] ) ) {
			$indexed_options[ self::CWD ] = Option::text( self::CWD, null, "An existing DIRECTORY, never a file. Relative paths resolve from it for this call only. Example: --cwd=wp-content/plugins/woocommerce woocommerce.php. Omit --cwd to use the site root. Set it again in each pipeline stage that needs it." );
		}
		$this->options  = $indexed_options;
		$this->operands = $indexed_operands;
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
	 * One line for listings.
	 *
	 * @return string
	 */
	public function summary(): string {
		return $this->summary;
	}

	/**
	 * The text the model reads.
	 *
	 * @return string
	 */
	public function description(): string {
		return $this->description;
	}

	/**
	 * Whether the command reads standard input when no file is given.
	 *
	 * @return bool
	 */
	public function reads_standard_input(): bool {
		return $this->reads_standard_input;
	}

	/**
	 * Options by name.
	 *
	 * @return array<string, Option>
	 */
	public function options(): array {
		return $this->options;
	}

	/**
	 * Operands by name.
	 *
	 * @return array<string, Operand>
	 */
	public function operands(): array {
		return $this->operands;
	}

	/**
	 * Validate raw arguments and build an Invocation.
	 *
	 * @param array<string, mixed> $arguments Raw arguments by name.
	 * @return Invocation
	 *
	 * @throws Invalid_Invocation When an argument is unknown, malformed or missing.
	 */
	public function invoke( array $arguments ): Invocation {
		$option_values  = array();
		$operand_values = array();

		foreach ( $arguments as $key => $value ) {
			$key = (string) $key;
			if ( self::LINE_ERROR === $key ) {
				throw Invalid_Invocation::custom( $this->name, array_map( 'strval', (array) $value ) );
			}
			if ( isset( $this->options[ $key ] ) ) {
				$option_values[ $key ] = $this->coerce_option( $this->options[ $key ], $value );
				continue;
			}
			if ( isset( $this->operands[ $key ] ) ) {
				$operand_values[ $key ] = $this->coerce_operand( $this->operands[ $key ], $value );
				continue;
			}
			throw Invalid_Invocation::unrecognized_option( $this->name, $key, array_keys( $this->options ) );
		}

		foreach ( $this->options as $name => $option ) {
			if ( ! array_key_exists( $name, $option_values ) ) {
				$option_values[ $name ] = $option->default();
			}
		}

		foreach ( $this->operands as $name => $operand ) {
			if ( array_key_exists( $name, $operand_values ) ) {
				continue;
			}
			if ( $operand->required() ) {
				throw Invalid_Invocation::missing_operand( $this->name, $name );
			}
			$operand_values[ $name ] = $operand->default();
		}

		return new Invocation( $this->name, $option_values, $operand_values );
	}

	/**
	 * Coerce one option value to its type, within its bounds.
	 *
	 * @param Option $option The option.
	 * @param mixed  $value  The raw value.
	 * @return mixed
	 *
	 * @throws Invalid_Invocation When the value does not fit the option.
	 * @throws LogicException     When the option has an unknown type.
	 */
	private function coerce_option( Option $option, $value ) {
		switch ( $option->type() ) {
			case Option_Type::FLAG:
				if ( is_bool( $value ) ) {
					return $value;
				}
				if ( is_int( $value ) && ( 0 === $value || 1 === $value ) ) {
					return 1 === $value;
				}
				if ( is_string( $value ) && in_array( strtolower( $value ), array( 'true', 'false', '1', '0', '' ), true ) ) {
					return in_array( strtolower( $value ), array( 'true', '1' ), true );
				}
				throw Invalid_Invocation::invalid_argument( $this->name, $option->name(), $this->stringify( $value ), 'expected true or false' );

			case Option_Type::INTEGER:
				if ( is_bool( $value ) || ( ! is_int( $value ) && ! ( is_string( $value ) && preg_match( '/^[+-]?\d+$/', $value ) ) ) ) {
					throw Invalid_Invocation::invalid_argument( $this->name, $option->name(), $this->stringify( $value ), 'expected an integer' );
				}
				$integer = (int) $value;
				if ( null !== $option->min() && $integer < $option->min() ) {
					throw Invalid_Invocation::invalid_argument( $this->name, $option->name(), (string) $integer, sprintf( 'minimum is %d', $option->min() ) );
				}
				if ( null !== $option->max() && $integer > $option->max() ) {
					throw Invalid_Invocation::invalid_argument( $this->name, $option->name(), (string) $integer, sprintf( 'maximum is %d', $option->max() ) );
				}
				if ( is_string( $value ) && ( '+' === $value[0] || '-' === $value[0] ) ) {
					// A written sign is kept, as "+5" or "-0", so tail can tell -n +N (from
					// line N on) from -n N, and head -n -0 (every line) from -n 0.
					// Invocation::integer() still reads it as the number.
					return $value[0] . (string) abs( $integer );
				}

				return $integer;

			case Option_Type::TEXT:
				if ( ! is_scalar( $value ) ) {
					throw Invalid_Invocation::invalid_argument( $this->name, $option->name(), $this->stringify( $value ), 'expected a string' );
				}
				$text = (string) $value;
				if ( strlen( $text ) > self::MAX_TEXT_BYTES ) {
					throw Invalid_Invocation::invalid_argument( $this->name, $option->name(), substr( $text, 0, 40 ) . '...', sprintf( 'at most %d bytes', self::MAX_TEXT_BYTES ) );
				}
				if ( null !== $option->enum_values() && ! in_array( $text, $option->enum_values(), true ) ) {
					throw Invalid_Invocation::invalid_argument( $this->name, $option->name(), $text, 'expected one of ' . implode( ', ', $option->enum_values() ) );
				}

				return $text;

			case Option_Type::TEXT_LIST:
				// Each item is held to the same cap as a text option, such as every -name glob find takes.
				return $this->coerce_string_list( $option->name(), $value, $option->max() ?? PHP_INT_MAX, self::MAX_TEXT_BYTES, sprintf( 'at most %d bytes', self::MAX_TEXT_BYTES ) );
		}

		// An enum made any other type impossible; a string constant cannot.
		throw new LogicException( 'Unknown option type: ' . $option->type() );
	}

	/**
	 * Coerce one operand value.
	 *
	 * @param Operand $operand The operand.
	 * @param mixed   $value   The raw value.
	 * @return mixed
	 *
	 * @throws Invalid_Invocation When the value does not fit the operand.
	 */
	private function coerce_operand( Operand $operand, $value ) {
		if ( $operand->many() ) {
			if ( 1 === $operand->max_items() && is_array( $value ) && count( $value ) > 1 ) {
				// GNU names the first operand it cannot use.
				throw Invalid_Invocation::custom( $this->name, array( sprintf( "%s: extra operand '%s'", $this->name, $this->stringify( array_values( $value )[1] ) ), sprintf( "Try '%s --help' for more information.", $this->name ) ) );
			}

			return $this->coerce_string_list( $operand->name(), $value, $operand->max_items(), $operand->max_length() );
		}

		if ( ! is_scalar( $value ) ) {
			throw Invalid_Invocation::invalid_argument( $this->name, $operand->name(), $this->stringify( $value ), 'expected a string' );
		}
		$text = (string) $value;
		if ( strlen( $text ) > $operand->max_length() ) {
			throw Invalid_Invocation::invalid_argument( $this->name, $operand->name(), substr( $text, 0, 40 ) . '…', 'too long' );
		}

		return $text;
	}

	/**
	 * A list of strings from a string or an array, bounded in count and in
	 * item length.
	 *
	 * @param string $argument   The argument name, for messages.
	 * @param mixed  $value      The raw value.
	 * @param int    $max_items  The most items accepted.
	 * @param int    $max_length The longest item accepted.
	 * @param string $too_long   Why an item over $max_length is refused.
	 * @return string[]
	 *
	 * @throws Invalid_Invocation When the value is not a bounded list of strings.
	 */
	private function coerce_string_list( string $argument, $value, int $max_items, int $max_length, string $too_long = 'too long' ): array {
		if ( is_int( $value ) ) {
			$value = (string) $value;
		}
		if ( is_string( $value ) ) {
			$value = '' === $value ? array() : array( $value );
		}
		if ( ! is_array( $value ) ) {
			throw Invalid_Invocation::invalid_argument( $this->name, $argument, $this->stringify( $value ), 'expected a list of strings' );
		}
		if ( count( $value ) > $max_items ) {
			throw Invalid_Invocation::invalid_argument( $this->name, $argument, $this->stringify( $value ), sprintf( 'at most %d items', $max_items ) );
		}
		$list = array();
		foreach ( $value as $item ) {
			if ( ! is_scalar( $item ) ) {
				throw Invalid_Invocation::invalid_argument( $this->name, $argument, $this->stringify( $item ), 'expected a string' );
			}
			$item = (string) $item;
			if ( strlen( $item ) > $max_length ) {
				throw Invalid_Invocation::invalid_argument( $this->name, $argument, substr( $item, 0, 40 ) . '…', $too_long );
			}
			$list[] = $item;
		}

		return $list;
	}

	/**
	 * A short printable form of any value, for messages.
	 *
	 * @param mixed $value The raw value.
	 * @return string
	 */
	private function stringify( $value ): string {
		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		return is_array( $value ) ? '[' . count( $value ) . ' items]' : gettype( $value );
	}
}
