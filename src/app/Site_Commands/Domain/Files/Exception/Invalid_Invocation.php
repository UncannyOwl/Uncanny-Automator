<?php
/**
 * Arguments that do not satisfy a file command's specification.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Exception;

/**
 * The arguments did not satisfy the command's specification.
 *
 * Carries the lines the shell would print and the exit status it would use.
 */
final class Invalid_Invocation extends Coder_Exception {

	/**
	 * The lines the shell would print.
	 *
	 * @var string[]
	 */
	private array $lines;

	/**
	 * The exit status the shell would use.
	 *
	 * @var int
	 */
	private int $exit_code;

	/**
	 * Record the lines and status.
	 *
	 * @param string[] $lines     The lines the shell would print.
	 * @param int      $exit_code The exit status the shell would use.
	 */
	public function __construct( array $lines, int $exit_code = 2 ) {
		$this->lines     = $lines;
		$this->exit_code = $exit_code;
		parent::__construct( implode( "\n", $lines ) );
	}

	/**
	 * An argument the command does not accept, with the closest known name.
	 *
	 * @param string   $command The command name.
	 * @param string   $option  The argument given.
	 * @param string[] $known   The argument names this command accepts.
	 * @return self
	 */
	public static function unrecognized_option( string $command, string $option, array $known = array() ): self {
		// The command line passes an unknown short option as "-q".
		if ( 2 === strlen( $option ) && '-' === $option[0] ) {
			return new self(
				array(
					sprintf( "%s: invalid option -- '%s'", $command, $option[1] ),
					sprintf( "Try '%s --help' for more information.", $command ),
				)
			);
		}
		$lines   = array( sprintf( "%s: unrecognized option '--%s'", $command, str_replace( '_', '-', $option ) ) );
		$closest = self::closest( $option, $known );
		if ( null !== $closest ) {
			$lines[] = sprintf( "%s: did you mean '--%s'?", $command, str_replace( '_', '-', $closest ) );
		}
		$lines[] = sprintf( "Try '%s --help' for more information.", $command );

		return new self( $lines );
	}

	/**
	 * The known name closest to the given one, when it is close enough.
	 *
	 * @param string   $given The argument given.
	 * @param string[] $known The argument names this command accepts.
	 * @return string|null
	 */
	private static function closest( string $given, array $known ): ?string {
		$best          = null;
		$best_distance = PHP_INT_MAX;
		foreach ( $known as $candidate ) {
			$distance = levenshtein( strtolower( $given ), strtolower( $candidate ) );
			if ( $distance < $best_distance ) {
				$best_distance = $distance;
				$best          = $candidate;
			}
		}

		return null !== $best && $best_distance <= max( 2, (int) ( strlen( $given ) / 2 ) ) ? $best : null;
	}

	/**
	 * A value the argument does not accept.
	 *
	 * @param string $command The command name.
	 * @param string $option  The argument name.
	 * @param string $value   The value given, shortened.
	 * @param string $why     Why it was refused.
	 * @return self
	 */
	public static function invalid_argument( string $command, string $option, string $value, string $why = '' ): self {
		$line = sprintf( "%s: invalid argument '%s' for '--%s'", $command, $value, $option );
		if ( '' !== $why ) {
			$line .= ': ' . $why;
		}

		return new self( array( $line, sprintf( "Try '%s --help' for more information.", $command ) ) );
	}

	/**
	 * A required operand was not given.
	 *
	 * @param string $command The command name.
	 * @param string $operand The operand name.
	 * @return self
	 */
	public static function missing_operand( string $command, string $operand ): self {
		return new self(
			array(
				sprintf( '%s: missing %s operand', $command, $operand ),
				sprintf( "Try '%s --help' for more information.", $command ),
			)
		);
	}

	/**
	 * Lines a command writes itself.
	 *
	 * @param string   $command   The command name.
	 * @param string[] $lines     The lines the shell would print.
	 * @param int      $exit_code The exit status the shell would use.
	 * @return self
	 */
	public static function custom( string $command, array $lines, int $exit_code = 2 ): self {
		return new self( $lines, $exit_code );
	}

	/**
	 * The lines the shell would print.
	 *
	 * @return string[]
	 */
	public function lines(): array {
		return $this->lines;
	}

	/**
	 * The exit status the shell would use.
	 *
	 * @return int
	 */
	public function exit_code(): int {
		return $this->exit_code;
	}
}
