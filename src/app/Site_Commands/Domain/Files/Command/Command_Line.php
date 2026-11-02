<?php
/**
 * Reads a file-command line the way a shell would split it, without a shell.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command output is plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Command;

use Uncanny_Automator\App\Site_Commands\Domain\Command_Error;

/**
 * Splits one line into pipeline stages and words, and turns a stage's words
 * into the arguments its specification names.
 *
 * Whitespace separates words; single and double quotes group them, and a
 * backslash escapes a quote or backslash inside double quotes. An unquoted
 * backslash is an ordinary character. An unquoted `|` separates stages.
 * Every other shell character (`;`, `&`, backticks, `$`, `<`, `>`) is
 * refused unless quoted, so nothing here can mean more than one command per
 * stage.
 *
 * As in a shell, unquoted braces expand first: `a{b,c}d` is the two words
 * `abd acd`, braces nest, and a brace without a comma is literal. Then an
 * unquoted `*`, `?` or `[...]` marks the word for path-name expansion; the
 * word carries a pattern in which every quoted wildcard and every backslash
 * is escaped (see Command_Word). Only path operands are expanded, by the
 * runner, inside the sandbox; option values and patterns stay as typed.
 */
final class Command_Line {

	/** The longest line, in characters. */
	public const MAX_LENGTH = 2000;

	/** The most stages one line may join with pipes. */
	public const MAX_STAGES = 8;

	/** The most words brace expansion may make of one line. */
	public const MAX_WORDS = 1000;

	/**
	 * The most characters brace expansion may make of one line, judged
	 * before anything is expanded, so a long word with a few groups cannot
	 * use up memory: each character is held as a small array.
	 */
	public const MAX_EXPANDED_CHARACTERS = 16384;

	/**
	 * Options of find-style commands written with two dashes, because GNU
	 * find has no such predicate.
	 *
	 * @var string[]
	 */
	private const DOUBLE_DASH_IN_FIND = array( 'exclude_dir', Specification::CWD );

	/**
	 * Find words accepted as written because they change nothing: printing
	 * each path is what find already does.
	 *
	 * @var string[]
	 */
	private const FIND_NO_OPERATIONS = array( '-print' );

	/**
	 * Commands where `-NUM` means `-n NUM`, as in GNU head and tail.
	 *
	 * @var string[]
	 */
	private const LINE_COUNT_SHORTHAND = array( 'head', 'tail' );

	/**
	 * Value names for help and messages, by option name.
	 *
	 * @var array<string, string>
	 */
	private const VALUE_NAMES = array(
		'name'            => 'GLOB',
		'iname'           => 'GLOB',
		'names'           => 'GLOB',
		'not_path'        => 'GLOB',
		'include'         => 'GLOB',
		'exclude'         => 'GLOB',
		'exclude_dir'     => 'NAME',
		'newer'           => 'FILE',
		'size'            => 'SIZE',
		'type'            => 'f|d|l',
		'mmin'            => 'N',
		'mtime'           => 'N',
		'regexp'          => 'PATTERN',
		'key'             => 'KEYDEF',
		'field_separator' => 'SEP',
		Specification::CWD => 'DIR',
	);

	/**
	 * The stages of a line, each a list of words; the first word names the command.
	 *
	 * @param string $line The command line.
	 * @return array<int, string[]>
	 *
	 * @throws Command_Error When the line is malformed.
	 */
	public static function stages( string $line ): array {
		$stages = array();
		foreach ( self::parse( $line ) as $words ) {
			$stages[] = array_map(
				static fn ( Command_Word $word ): string => $word->text(),
				$words
			);
		}

		return $stages;
	}

	/**
	 * The stages of a line as words that remember which wildcards were
	 * typed unquoted.
	 *
	 * @param string $line The command line.
	 * @return array<int, Command_Word[]>
	 *
	 * @throws Command_Error When the line is malformed.
	 */
	public static function parse( string $line ): array {
		if ( self::length( $line ) > self::MAX_LENGTH ) {
			throw new Command_Error( sprintf( 'The command is longer than %d characters.', self::MAX_LENGTH ) );
		}
		// A line break would separate commands in a shell, so it is refused too.
		if ( 1 === preg_match( '/[\x00-\x08\x0A-\x1F\x7F]/', $line ) ) {
			throw new Command_Error( 'The command contains a line break or control character. Send one command on one line.' );
		}

		$stages = array();
		$words  = array();
		$pieces = array();
		$quoted = false;
		$total  = 0;
		$made   = 0;
		$quote  = null;
		$length = strlen( $line );
		for ( $i = 0; $i < $length; $i++ ) {
			$char = $line[ $i ];
			if ( null !== $quote ) {
				if ( $char === $quote ) {
					$quote = null;
				} elseif ( '"' === $quote && '\\' === $char && $i + 1 < $length && in_array( $line[ $i + 1 ], array( '"', '\\' ), true ) ) {
					$pieces[] = array( $line[ ++$i ], true );
				} else {
					$pieces[] = array( $char, true );
				}
				continue;
			}
			if ( '"' === $char || "'" === $char ) {
				$quote  = $char;
				$quoted = true;
				continue;
			}
			if ( ' ' === $char || "\t" === $char || '|' === $char ) {
				$words  = self::add_word( $words, $pieces, $quoted, $total, $made );
				$pieces = array();
				$quoted = false;
				if ( '|' === $char ) {
					if ( array() === $words ) {
						throw new Command_Error( 'A pipe (|) needs a command on each side.' );
					}
					$stages[] = $words;
					$words    = array();
				}
				continue;
			}
			if ( false !== strpos( ';&`$<>', $char ) ) {
				throw new Command_Error( sprintf( 'The character %s is shell syntax. File commands run without a shell; quote it if it is part of a value.', $char ) );
			}
			$pieces[] = array( $char, false );
		}
		if ( null !== $quote ) {
			throw new Command_Error( 'The command has an unclosed quote.' );
		}
		$words = self::add_word( $words, $pieces, $quoted, $total, $made );
		if ( array() === $words ) {
			throw new Command_Error( array() === $stages ? 'The command is empty.' : 'A pipe (|) needs a command on each side.' );
		}
		$stages[] = $words;
		if ( count( $stages ) > self::MAX_STAGES ) {
			throw new Command_Error( sprintf( 'The command joins %d commands with pipes; at most %d may be joined. Split it into separate calls.', count( $stages ), self::MAX_STAGES ) );
		}

		return $stages;
	}

	/**
	 * The arguments a stage's words give, keyed as the specification names them.
	 * Unknown options are kept under their own keys so the specification
	 * reports them, never ignores them.
	 *
	 * @param Specification               $specification The command's specification.
	 * @param array<int, string|Command_Word> $args      The words after the command name.
	 * @return array<string, mixed>
	 */
	public static function arguments( Specification $specification, array $args ): array {
		return self::parse_arguments( $specification, $args )[0];
	}

	/**
	 * The arguments a stage's words give, and the path-name patterns of the
	 * path operands that were typed with unquoted wildcards.
	 *
	 * A usage error found here, such as a missing option value or an extra
	 * operand, is passed under Specification::LINE_ERROR so the
	 * specification reports it as the command would, in the order GNU
	 * getopt meets it.
	 *
	 * @param Specification                   $specification The command's specification.
	 * @param array<int, string|Command_Word> $args          The words after the command name.
	 * @return array{0: array<string, mixed>, 1: array<string, array<int, string>>} The arguments, and patterns by operand name and position.
	 */
	public static function parse_arguments( Specification $specification, array $args ): array {
		$command = $specification->name();
		$options = $specification->options();
		$shorts  = array();
		foreach ( $options as $option ) {
			if ( null !== $option->short() ) {
				$shorts[ $option->short() ] = $option;
			}
		}
		$find_style = self::uses_single_dash_options( $specification );

		$words = array();
		foreach ( $args as $arg ) {
			$words[] = $arg instanceof Command_Word ? $arg : new Command_Word( (string) $arg );
		}

		$result          = array();
		$positional      = array();
		$only_positional = false;

		while ( array() !== $words ) {
			$word = array_shift( $words );
			$arg  = $word->text();
			if ( $find_style && ! $only_positional && '!' === $arg ) {
				$arg = '-not';
			}

			if ( $only_positional || '-' === $arg || 0 !== strpos( $arg, '-' ) ) {
				$positional[] = $word;
				continue;
			}
			if ( '--' === $arg ) {
				$only_positional = true;
				continue;
			}

			if ( 0 === strpos( $arg, '--' ) ) {
				$body  = substr( $arg, 2 );
				$value = null;
				if ( false !== strpos( $body, '=' ) ) {
					list( $body, $value ) = explode( '=', $body, 2 );
				}
				$key     = str_replace( '-', '_', $body );
				$refusal = self::refusal( $command, $key );
				if ( null !== $refusal ) {
					return self::failed( $result, $refusal );
				}
				if ( ! isset( $options[ $key ] ) ) {
					$result[ $key ] = true; // Let the specification report it.
					continue;
				}
				$option = $options[ $key ];
				if ( Option_Type::FLAG === $option->type() ) {
					$result[ $key ] = null === $value ? true : in_array( strtolower( $value ), array( '1', 'true', 'yes', 'always', 'auto' ), true );
					continue;
				}
				if ( null === $value ) {
					if ( array() === $words ) {
						return self::failed( $result, self::missing_value( $command, sprintf( "option '--%s' requires an argument", $body ) ) );
					}
					$value = array_shift( $words )->text();
				}
				$result = self::set( $result, $option, $value );
				continue;
			}

			// GNU head and tail read -5 as -n 5.
			if ( in_array( $command, self::LINE_COUNT_SHORTHAND, true ) && isset( $options['lines'] ) && 1 === preg_match( '/^-(\d+)$/', $arg, $found ) ) {
				$result['lines'] = $found[1];
				continue;
			}

			if ( $find_style ) {
				$single = str_replace( '-', '_', substr( $arg, 1 ) );
				if ( isset( $options[ $single ] ) ) {
					$option = $options[ $single ];
					if ( Option_Type::FLAG === $option->type() ) {
						$result[ $single ] = true;
						continue;
					}
					if ( array() === $words ) {
						return self::failed( $result, array( sprintf( "%s: missing argument to '%s'", $command, $arg ) ) );
					}
					$result = self::set( $result, $option, array_shift( $words )->text() );
					continue;
				}
				if ( '-not' === $arg && isset( $options['not_path'] ) ) {
					if ( array() === $words || '-path' !== $words[0]->text() ) {
						return self::failed( $result, array( sprintf( "%s: -not (or !) is supported only as -not -path GLOB here, such as -not -path '*/cache/*'", $command ), self::supported_predicates( $specification ) ) );
					}
					array_shift( $words );
					if ( array() === $words ) {
						return self::failed( $result, array( sprintf( "%s: missing argument to '-path'", $command ) ) );
					}
					$result['not_path'][] = array_shift( $words )->text();
					continue;
				}
				if ( in_array( $arg, self::FIND_NO_OPERATIONS, true ) ) {
					continue;
				}
				$lines = array( sprintf( "%s: unknown predicate '%s'", $command, $arg ), self::supported_predicates( $specification ) );
				if ( in_array( $arg, array( '-exec', '-execdir', '-ok', '-okdir', '-delete' ), true ) ) {
					$lines[] = sprintf( '%s: this find runs no commands; pass the paths on instead, such as: find wp-content -name \'*.log\' | xargs <command>', $command );
				}

				return self::failed( $result, $lines );
			}

			// Single-dash long options such as -maxdepth, for commands that declare them.
			$single = str_replace( '-', '_', substr( $arg, 1 ) );
			if ( strlen( $single ) > 1 && isset( $options[ $single ] ) ) {
				$option = $options[ $single ];
				if ( Option_Type::FLAG === $option->type() ) {
					$result[ $single ] = true;
					continue;
				}
				if ( array() === $words ) {
					return self::failed( $result, self::missing_value( $command, sprintf( "option '%s' requires an argument", $arg ) ) );
				}
				$result = self::set( $result, $option, array_shift( $words )->text() );
				continue;
			}

			// Short flags, possibly bundled: -rn, -n20, -A 3.
			$body = substr( $arg, 1 );
			while ( '' !== $body ) {
				$letter  = $body[0];
				$body    = substr( $body, 1 );
				$refusal = self::refusal( $command, $letter );
				if ( null !== $refusal ) {
					return self::failed( $result, $refusal );
				}
				if ( ! isset( $shorts[ $letter ] ) ) {
					$result[ '-' . $letter ] = true; // Unrecognized; the specification reports it.
					continue;
				}
				$option = $shorts[ $letter ];
				if ( Option_Type::FLAG === $option->type() ) {
					$result[ $option->name() ] = true;
					continue;
				}
				if ( '' !== $body ) {
					$value = $body;
				} elseif ( array() !== $words ) {
					$value = array_shift( $words )->text();
				} else {
					return self::failed( $result, self::missing_value( $command, sprintf( "option requires an argument -- '%s'", $letter ) ) );
				}
				$body   = '';
				$result = self::set( $result, $option, $value );
			}
		}

		return self::assign_operands( $specification, $result, $positional );
	}

	/**
	 * Whether the command takes find-style options: one dash, whole words,
	 * such as -name and -maxdepth.
	 *
	 * @param Specification $specification The command's specification.
	 * @return bool
	 */
	public static function uses_single_dash_options( Specification $specification ): bool {
		return 'find' === $specification->name();
	}

	/**
	 * How a caller writes one option: `-name GLOB` and `-type f|d|l` for
	 * find-style commands, `-n, --lines=<n>` for the others. Options with a
	 * fixed set of values list them.
	 *
	 * @param Specification $specification The command's specification.
	 * @param Option        $option        The option.
	 * @return string
	 */
	public static function usage( Specification $specification, Option $option ): string {
		$flag   = Option_Type::FLAG === $option->type();
		$values = $option->enum_values();
		if ( self::uses_single_dash_options( $specification ) && ! in_array( $option->name(), self::DOUBLE_DASH_IN_FIND, true ) ) {
			$word = 'not_path' === $option->name() ? '-not -path' : '-' . str_replace( '_', '-', $option->name() );
			if ( $flag ) {
				return $word;
			}
			if ( null !== $values ) {
				return $word . ' ' . implode( '|', $values );
			}

			return $word . ' ' . ( Option_Type::INTEGER === $option->type() ? 'N' : ( self::VALUE_NAMES[ $option->name() ] ?? 'VALUE' ) );
		}

		$label = ( null === $option->short() ? '' : '-' . $option->short() . ', ' ) . '--' . str_replace( '_', '-', $option->name() );
		if ( $flag ) {
			return $label;
		}
		if ( null !== $values ) {
			return $label . '=<' . implode( '|', $values ) . '>';
		}

		return $label . '=<' . ( Option_Type::INTEGER === $option->type() ? 'n' : 'value' ) . '>';
	}

	/**
	 * The words one raw word makes after brace expansion, added to the stage.
	 *
	 * @param Command_Word[]                        $words  The stage's words so far.
	 * @param array<int, array{0: string, 1: bool}> $pieces The word's characters and whether each was quoted.
	 * @param bool                                  $quoted Whether the word held quotes, so an empty one still counts.
	 * @param int                                   $total  Words made so far on the line.
	 * @param int                                   $made   Characters made so far on the line.
	 * @return Command_Word[]
	 *
	 * @throws Command_Error When brace expansion makes too many words or characters.
	 */
	private static function add_word( array $words, array $pieces, bool $quoted, int &$total, int &$made ): array {
		if ( array() === $pieces && ! $quoted ) {
			return $words;
		}
		$most  = self::most_alternatives( $pieces );
		$made += $most * count( $pieces );
		if ( $most > self::MAX_WORDS || $made > self::MAX_EXPANDED_CHARACTERS ) {
			throw new Command_Error( sprintf( 'Brace expansion makes this command too long (at most %d words and %d characters). Write fewer alternatives.', self::MAX_WORDS, self::MAX_EXPANDED_CHARACTERS ) );
		}
		foreach ( self::expand_braces( $pieces ) as $expanded ) {
			if ( array() === $expanded && ! $quoted ) {
				continue; // An empty alternative of an unquoted word is no word, as in a shell.
			}
			if ( ++$total > self::MAX_WORDS ) {
				throw new Command_Error( sprintf( 'Brace expansion makes more than %d words of this command. Write fewer alternatives.', self::MAX_WORDS ) );
			}
			$words[] = Command_Word::from_pieces( $expanded );
		}

		return $words;
	}

	/**
	 * Brace expansion as a shell does it: the first unquoted `{` with a
	 * matching `}` and a comma between them at its own level splits the
	 * word; the alternatives and the rest are expanded in turn.
	 *
	 * @param array<int, array{0: string, 1: bool}> $pieces A word's characters and whether each was quoted.
	 * @return array<int, array<int, array{0: string, 1: bool}>>
	 *
	 * @throws Command_Error When the expansion makes too many words.
	 */
	private static function expand_braces( array $pieces ): array {
		$count = count( $pieces );
		for ( $open = 0; $open < $count; $open++ ) {
			if ( ! self::is_syntax( $pieces[ $open ], '{' ) ) {
				continue;
			}
			$depth  = 0;
			$close  = null;
			$commas = array();
			for ( $j = $open; $j < $count; $j++ ) {
				if ( self::is_syntax( $pieces[ $j ], '{' ) ) {
					++$depth;
				} elseif ( self::is_syntax( $pieces[ $j ], '}' ) ) {
					--$depth;
					if ( 0 === $depth ) {
						$close = $j;
						break;
					}
				} elseif ( 1 === $depth && self::is_syntax( $pieces[ $j ], ',' ) ) {
					$commas[] = $j;
				}
			}
			if ( null === $close || array() === $commas ) {
				continue;
			}

			$preamble     = array_slice( $pieces, 0, $open );
			$postscript   = array_slice( $pieces, $close + 1 );
			$alternatives = array();
			$start        = $open + 1;
			foreach ( array_merge( $commas, array( $close ) ) as $end ) {
				$alternatives[] = array_slice( $pieces, $start, $end - $start );
				$start          = $end + 1;
			}
			$results = array();
			foreach ( $alternatives as $alternative ) {
				foreach ( self::expand_braces( array_merge( $alternative, $postscript ) ) as $rest ) {
					$results[] = array_merge( $preamble, $rest );
					if ( count( $results ) > self::MAX_WORDS ) {
						throw new Command_Error( sprintf( 'Brace expansion makes more than %d words of this command. Write fewer alternatives.', self::MAX_WORDS ) );
					}
				}
			}

			return $results;
		}

		return array( $pieces );
	}

	/**
	 * The most words brace expansion can make of a word, without expanding
	 * it: the product of each brace group's alternatives, at least as many
	 * as it really makes. Stops counting past MAX_WORDS.
	 *
	 * @param array<int, array{0: string, 1: bool}> $pieces A word's characters and whether each was quoted.
	 * @return int
	 */
	private static function most_alternatives( array $pieces ): int {
		$most   = 1;
		$commas = array();
		foreach ( $pieces as $piece ) {
			if ( self::is_syntax( $piece, '{' ) ) {
				$commas[] = 0;
			} elseif ( array() !== $commas && self::is_syntax( $piece, ',' ) ) {
				++$commas[ count( $commas ) - 1 ];
			} elseif ( array() !== $commas && self::is_syntax( $piece, '}' ) ) {
				$most *= 1 + array_pop( $commas );
				if ( $most > self::MAX_WORDS ) {
					return $most;
				}
			}
		}

		return $most;
	}

	/**
	 * Whether a character is this unquoted brace-expansion character.
	 *
	 * @param array{0: string, 1: bool} $piece A character and whether it was quoted.
	 * @param string                    $char  The syntax character.
	 * @return bool
	 */
	private static function is_syntax( array $piece, string $char ): bool {
		return ! $piece[1] && $char === $piece[0];
	}

	/**
	 * Store an option value: appended for a list, replaced otherwise, as
	 * GNU's last -n or -maxdepth wins. A find test given more than once must
	 * pass each time, so find declares its tests as lists.
	 *
	 * @param array<string, mixed> $result The arguments so far.
	 * @param Option               $option The option.
	 * @param string               $value  The value.
	 * @return array<string, mixed>
	 */
	private static function set( array $result, Option $option, string $value ): array {
		if ( Option_Type::TEXT_LIST === $option->type() ) {
			$result[ $option->name() ][] = $value;
		} else {
			$result[ $option->name() ] = $value;
		}

		return $result;
	}

	/**
	 * Give the positional words to the operands, in order. With grep -e,
	 * every positional word is a file. A word left over is GNU's extra
	 * operand.
	 *
	 * @param Specification        $specification The command's specification.
	 * @param array<string, mixed> $result        The arguments so far.
	 * @param Command_Word[]       $positional    The positional words.
	 * @return array{0: array<string, mixed>, 1: array<string, array<int, string>>}
	 */
	private static function assign_operands( Specification $specification, array $result, array $positional ): array {
		$globs    = array();
		$operands = array();
		foreach ( $specification->operands() as $operand ) {
			if ( 'pattern' === $operand->name() && isset( $result['regexp'] ) ) {
				continue;
			}
			$operands[] = $operand;
		}

		foreach ( $operands as $operand ) {
			if ( array() === $positional ) {
				break;
			}
			$name     = $operand->name();
			$expands  = 'paths' === $name || 'path' === $name;
			$assigned = $operand->many() ? $positional : array( $positional[0] );
			$texts    = array();
			foreach ( $assigned as $index => $word ) {
				$texts[] = $word->text();
				if ( $expands && null !== $word->glob() ) {
					$globs[ $name ][ $index ] = $word->glob();
				}
			}
			$result[ $name ] = $operand->many() ? $texts : $texts[0];
			$positional      = array_slice( $positional, count( $assigned ) );
		}
		if ( array() !== $positional ) {
			return self::failed(
				$result,
				array(
					sprintf( "%s: extra operand '%s'", $specification->name(), $positional[0]->text() ),
					sprintf( "Try '%s --help' for more information.", $specification->name() ),
				)
			);
		}

		return array( $result, $globs );
	}

	/**
	 * Stop at a usage error, which the specification then reports.
	 *
	 * @param array<string, mixed> $result The arguments so far.
	 * @param string[]             $lines  The lines the command prints.
	 * @return array{0: array<string, mixed>, 1: array<string, array<int, string>>}
	 */
	private static function failed( array $result, array $lines ): array {
		$result[ Specification::LINE_ERROR ] = $lines;

		return array( $result, array() );
	}

	/**
	 * GNU's lines for an option written without its value.
	 *
	 * @param string $command The command name.
	 * @param string $message What is missing.
	 * @return string[]
	 */
	private static function missing_value( string $command, string $message ): array {
		return array( sprintf( '%s: %s', $command, $message ), sprintf( "Try '%s --help' for more information.", $command ) );
	}

	/**
	 * An explanation for an option that cannot work in one read-only call.
	 *
	 * @param string $command The command name.
	 * @param string $option  The short letter or long option name.
	 * @return string[]|null
	 */
	private static function refusal( string $command, string $option ): ?array {
		if ( 'tail' === $command && in_array( $option, array( 'f', 'F', 'follow' ), true ) ) {
			return array(
				'tail: -f (follow) is not available: each call prints a snapshot and returns. Run tail -n N again to see lines added since.',
				"Try 'tail --help' for more information.",
			);
		}

		return null;
	}

	/**
	 * The predicates a find-style command supports, for its messages.
	 *
	 * @param Specification $specification The command's specification.
	 * @return string
	 */
	private static function supported_predicates( Specification $specification ): string {
		$supported = array();
		foreach ( $specification->options() as $option ) {
			$supported[] = self::usage( $specification, $option );
		}

		return sprintf( '%s: supported: %s', $specification->name(), implode( ', ', $supported ) );
	}

	/**
	 * Length in characters, without depending on the mbstring extension.
	 *
	 * @param string $line The command line.
	 * @return int
	 */
	private static function length( string $line ): int {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $line, 'UTF-8' );
		}
		$characters = preg_match_all( '/./su', $line );

		return false === $characters ? strlen( $line ) : $characters;
	}
}
