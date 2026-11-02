<?php
/**
 * Parse one site command written in WP-CLI style.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command errors are plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Domain\Command_Line;

use Uncanny_Automator\App\Site_Commands\Domain\Command_Error;

/**
 * There is no shell. The parser only splits words, honours quotes and reads
 * --options. Shell syntax is refused instead of ignored, so a command never
 * silently means something other than what was written.
 */
final class Command_Parser {

	public const MAX_LENGTH = 2000;

	/**
	 * @param string $input Command line, with or without a leading "wp".
	 * @return Parsed_Command
	 *
	 * @throws Command_Error When the command cannot be read exactly.
	 */
	public function parse( string $input ): Parsed_Command {
		if ( $this->length( $input ) > self::MAX_LENGTH ) {
			throw new Command_Error( sprintf( 'The command is longer than %d characters.', self::MAX_LENGTH ) );
		}

		// Check before trimming: trim() would silently drop a NUL at either end.
		// A line break would separate commands in a shell, so it is refused too.
		if ( 1 === preg_match( '/[\x00-\x08\x0A-\x1F\x7F]/', $input ) ) {
			throw new Command_Error( 'The command contains a line break or control character. Send one command on one line.' );
		}

		$tokens = $this->tokenize( trim( $input ) );
		if ( isset( $tokens[0] ) && 'wp' === $tokens[0] ) {
			array_shift( $tokens );
		}

		$words   = array();
		$options = array();
		foreach ( $tokens as $token ) {
			if ( '--' === $token ) {
				throw new Command_Error( 'A bare -- is not supported. Write options as --name or --name=value.' );
			}
			if ( 0 === strpos( $token, '--' ) ) {
				list( $name, $value ) = $this->read_option( substr( $token, 2 ) );
				if ( array_key_exists( $name, $options ) ) {
					throw new Command_Error( sprintf( 'The option --%s was given more than once.', $name ) );
				}
				$options[ $name ] = $value;
				continue;
			}
			if ( '-' === substr( $token, 0, 1 ) && strlen( $token ) > 1 && ! is_numeric( $token ) ) {
				throw new Command_Error( sprintf( 'Short options such as %s are not supported. Use the long --name form.', $token ) );
			}
			$words[] = $token;
		}

		return new Parsed_Command( $words, $options );
	}

	/**
	 * Length in characters, without depending on the mbstring extension.
	 *
	 * @param string $input Command line.
	 * @return int
	 */
	private function length( string $input ): int {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $input, 'UTF-8' );
		}
		$characters = preg_match_all( '/./su', $input );

		return false === $characters ? strlen( $input ) : $characters;
	}

	/**
	 * @param string $body Option text after the leading dashes.
	 * @return array{0: string, 1: string|bool}
	 */
	private function read_option( string $body ): array {
		$equals = strpos( $body, '=' );
		$name   = false === $equals ? $body : substr( $body, 0, $equals );
		$value  = false === $equals ? true : substr( $body, $equals + 1 );

		if ( true === $value && 0 === strpos( $name, 'no-' ) ) {
			$name  = substr( $name, 3 );
			$value = false;
		}
		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9_-]*$/', $name ) ) {
			throw new Command_Error( sprintf( 'The option --%s is not a valid option name.', $body ) );
		}

		return array( $name, $value );
	}

	/**
	 * Split on whitespace, keeping quoted text together.
	 *
	 * @param string $input Command line.
	 * @return string[]
	 */
	private function tokenize( string $input ): array {
		$tokens  = array();
		$current = '';
		$started = false;
		$quote   = null;
		$length  = strlen( $input );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $input[ $i ];

			if ( null !== $quote ) {
				if ( $char === $quote ) {
					$quote = null;
				} elseif ( '"' === $quote && '\\' === $char && $i + 1 < $length && in_array( $input[ $i + 1 ], array( '"', '\\' ), true ) ) {
					$current .= $input[ ++$i ];
				} else {
					$current .= $char;
				}
				continue;
			}

			if ( '"' === $char || "'" === $char ) {
				$quote   = $char;
				$started = true;
				continue;
			}
			if ( ' ' === $char || "\t" === $char ) {
				if ( $started ) {
					$tokens[] = $current;
				}
				$current = '';
				$started = false;
				continue;
			}
			if ( '|' === $char ) {
				throw new Command_Error( 'Pipes (|) are not supported yet. Run one command at a time.' );
			}
			if ( false !== strpos( ';&`$<>', $char ) ) {
				throw new Command_Error( sprintf( 'The character %s is shell syntax. Site commands run one command without a shell; quote it if it is part of a value.', $char ) );
			}

			$current .= $char;
			$started  = true;
		}

		if ( null !== $quote ) {
			throw new Command_Error( 'The command has an unclosed quote.' );
		}
		if ( $started ) {
			$tokens[] = $current;
		}

		return $tokens;
	}
}
