<?php
/**
 * grep: search file contents for a pattern.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command output is plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Commands;

use Throwable;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Budget\Budget_Ledger;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Exit_Status;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Invocation;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Operand;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Option;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Specification;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Access_Denied;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Coder_Exception;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Filesystem_Exception;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Invalid_Invocation;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Line_Read_Limit_Exceeded;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Pattern_Exception;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Unexpected_Failure;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Content_Kind;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Read_Only_Filesystem;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Standard_Input;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Pattern\Glob_Pattern;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Pattern\Pattern_Mode;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Pattern\Search_Pattern;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox\Printed_Source;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox\Sandbox;

/**
 * GNU grep over the sandbox: one file, several files, or a recursive walk,
 * with context, counts, file lists and the match budget.
 */
final class Grep extends Abstract_Command {

	/**
	 * Whether the start was implied; GNU prints walked names without the ./ prefix then.
	 *
	 * @var bool
	 */
	private bool $implicit_dot = false;

	/**
	 * Files the denylist refused during a recursive search.
	 *
	 * @var string[]
	 */
	private array $denied_files = array();

	/**
	 * Files a recursive search found whose printed lines the content policy withheld.
	 *
	 * @var string[]
	 */
	private array $withheld_files = array();

	/**
	 * Whether the content policy withheld the lines printed from a file
	 * operand or standard input, which fails the search as an unreadable
	 * file does.
	 *
	 * @var bool
	 */
	private bool $withheld = false;

	/**
	 * Whether any context group was printed for an earlier file; GNU separates files with -- too.
	 *
	 * @var bool
	 */
	private bool $printed_context_before = false;

	/**
	 * Set when PCRE gave up on a line; the run stops with exit 2.
	 *
	 * @var bool
	 */
	private bool $pattern_failed = false;

	/**
	 * True for -s: messages about missing or unreadable files are not printed.
	 *
	 * @var bool
	 */
	private bool $silent = false;

	/**
	 * Files skipped at a line longer than 1 MB.
	 *
	 * @var int
	 */
	private int $long_line_files = 0;

	/**
	 * The command's contract.
	 *
	 * @return Specification
	 */
	public static function specification(): Specification {
		return new Specification(
			'grep',
			'Search file contents for a pattern.',
			"Find text inside files. Use -F (--fixed-strings) for a literal name or error. Use -E (--extended-regexp) for alternatives such as -E 'foo|bar'; without it, | is literal. Use -n (--line-number) for citations, -r (--recursive) for a directory, and --include='*.php' to narrow files. Use -l (--files-with-matches) to get filenames only. Exit 1 means no matches; exit 2 means an error. Recursive searches skip common dependency/cache directories; see --exclude-dir.",
			array(
				Option::flag( 'recursive', 'r', 'Search directories recursively. Does not follow symbolic links.' ),
				Option::flag( 'dereference_recursive', 'R', 'The same as -r here: symbolic links are not followed.' ),
				Option::list( 'regexp', 'e', "Use this pattern; repeat it for alternatives, for example -e add_action -e add_filter. With -e every operand is a file." ),
				Option::flag( 'line_number', 'n', 'Prefix each line with its line number.' ),
				Option::flag( 'ignore_case', 'i', 'Ignore case distinctions.' ),
				Option::flag( 'files_with_matches', 'l', 'Print only the names of files with matches.' ),
				Option::flag( 'count', 'c', 'Print only a count of matching lines per file.' ),
				Option::flag( 'extended_regexp', 'E', 'Use for foo|bar, (groups), +, or ? regex operators. Without this flag those characters are literal.' ),
				Option::flag( 'fixed_strings', 'F', 'Search exact text, such as a class name or error message, with no regex interpretation.' ),
				Option::flag( 'word_regexp', 'w', 'Match whole words only.' ),
				Option::flag( 'invert_match', 'v', 'Select lines that do not match.' ),
				Option::flag( 'only_matching', 'o', 'Print only the matched part of each line, one per line, so a wide pattern shows which alternative hit.' ),
				Option::flag( 'quiet', 'q', 'Print nothing; exit 0 at the first match, even if an error occurred, 1 when nothing matched.' ),
				Option::flag( 'no_messages', 's', 'Do not print errors about missing or unreadable files; the exit status still reports them.' ),
				Option::flag( 'no_filename', 'h', 'Never prefix lines with the file name.' ),
				Option::flag( 'with_filename', 'H', 'Always prefix lines with the file name, even for one file.' ),
				Option::flag( 'skip_binary', 'I', 'Accepted for compatibility: binary files are always skipped, as if they held no match (GNU -I).' ),
				Option::flag( 'color', null, 'Accepted for compatibility, with any value; output is never colored.' ),
				Option::list( 'exclude', null, "Skip files whose base name matches this glob; repeat it for more, for example --exclude='*.min.js' --exclude='*.map'. GNU --exclude." ),
				Option::integer( 'after_context', 'A', 'Print this many lines after each match.', null, 0, 50 ),
				Option::integer( 'before_context', 'B', 'Print this many lines before each match.', null, 0, 50 ),
				Option::integer( 'context', 'C', 'Print this many lines before and after each match.', null, 0, 50 ),
				Option::integer( 'max_count', 'm', 'Stop reading a file after this many matching lines.', null, 1, 100000 ),
				Option::list( 'include', null, "Only search files whose base name matches this glob; repeat it for more, for example --include='*.php'." ),
				Option::list( 'exclude_dir', null, 'Directory names not to descend into; repeat it for more. Defaults to node_modules, vendor, .git, cache, .cache, upgrade, backups; uploads is not excluded. Any --exclude-dir replaces the defaults: pass --exclude-dir= (empty) to descend into everything, or list each name to skip, adding names such as uploads to skip more.', 20, Tree_Walker::DEFAULT_EXCLUDE_DIRS ),
			),
			array(
				new Operand( 'pattern', 'The pattern to search for, unless -e gives it. A basic regular expression unless --extended-regexp or --fixed-strings: in basic mode ( ) { } | + ? are literal characters.', false, false, null, 20, 1024 ),
				Operand::paths( 'Files or directories to search. With --recursive defaults to the site root.' ),
			),
			true
		);
	}

	/**
	 * Search every operand.
	 *
	 * @param Invocation           $invocation The validated arguments.
	 * @param Sandbox              $sandbox    Resolves every path.
	 * @param Budget_Ledger        $ledger     The only way to write output.
	 * @param Read_Only_Filesystem $filesystem The only way to read.
	 * @return Exit_Status
	 *
	 * @throws Invalid_Invocation When there is nothing to search.
	 */
	public function run( Invocation $invocation, Sandbox $sandbox, Budget_Ledger $ledger, Read_Only_Filesystem $filesystem ): Exit_Status {
		$recursive                    = $invocation->flag( 'recursive' ) || $invocation->flag( 'dereference_recursive' );
		$paths                        = $invocation->paths();
		$this->implicit_dot           = false;
		$this->denied_files           = array();
		$this->withheld_files         = array();
		$this->withheld               = false;
		$this->printed_context_before = false;
		$this->silent                 = $invocation->flag( 'no_messages' );
		$this->long_line_files        = 0;
		$quiet                        = $invocation->flag( 'quiet' );
		$patterns                     = $invocation->list( 'regexp' );
		if ( null !== $invocation->operand( 'pattern' ) ) {
			$patterns[] = (string) $invocation->operand( 'pattern' );
		}
		if ( array() === $patterns ) {
			throw Invalid_Invocation::custom( 'grep', array( 'Usage: grep [OPTION]... PATTERNS [FILE]...', "Try 'grep --help' for more information." ) );
		}
		if ( array() === $paths ) {
			if ( ! $recursive ) {
				throw Invalid_Invocation::custom( 'grep', array( 'grep: no file operand; name the files to search or use --recursive', "Try 'grep --help' for more information." ) );
			}
			$paths              = array( '.' );
			$this->implicit_dot = true; // GNU prints walked names without the ./ prefix when the start was implied.
		}

		try {
			$pattern = new Search_Pattern(
				implode( "\n", $patterns ),
				$invocation->flag( 'fixed_strings' ) ? Pattern_Mode::FIXED : ( $invocation->flag( 'extended_regexp' ) ? Pattern_Mode::EXTENDED : Pattern_Mode::BASIC ),
				$invocation->flag( 'ignore_case' ),
				$invocation->flag( 'word_regexp' )
			);
		} catch ( Pattern_Exception $failure ) {
			$ledger->err( $failure->getMessage() . "\n" );

			return Exit_Status::trouble();
		} catch ( Throwable $failure ) {
			// Preserve expected control-flow exceptions; contextualize unexpected failures.
			throw Unexpected_Failure::during( 'searching file content', $failure );
		}

		$context      = $invocation->integer( 'context' );
		$after        = min( $invocation->integer( 'after_context' ) ?? $context ?? 0, $ledger->limits()->max_context_lines() );
		$before       = min( $invocation->integer( 'before_context' ) ?? $context ?? 0, $ledger->limits()->max_context_lines() );
		$include      = $invocation->list( 'include' );
		$exclude      = $invocation->list( 'exclude' );
		$exclude_dirs = $invocation->list( 'exclude_dir' );

		// GNU shows file names when there is more than one operand, or when
		// recursing into a directory operand. Decide before printing anything.
		$any_directory = false;
		if ( $recursive ) {
			foreach ( $paths as $given ) {
				try {
					$probe = $sandbox->resolve( $given, $filesystem );
					if ( $filesystem->exists( $probe ) && $filesystem->info( $probe, true )->is_directory() ) {
						$any_directory = true;
						break;
					}
				} catch ( Coder_Exception $reported_below ) {
					// Reported below, in order.
					unset( $reported_below );
				} catch ( Throwable $failure ) {
					// Preserve expected control-flow exceptions; contextualize unexpected failures.
					throw Unexpected_Failure::during( 'searching file content', $failure );
				}
			}
		}
		$show_names = count( $paths ) > 1 || $any_directory;
		if ( $invocation->flag( 'no_filename' ) ) {
			$show_names = false;
		} elseif ( $invocation->flag( 'with_filename' ) ) {
			$show_names = true;
		}

		$had_error    = false;
		$any_match    = false;
		$walker       = new Tree_Walker( $filesystem, $ledger );
		$skipped_dirs = 0;
		$on_error     = function ( Resolved_Path $path, Filesystem_Exception $failure ) use ( $ledger, &$had_error ): void {
			$this->report_filesystem( $failure, $path->given(), $ledger );
			$had_error = true;
		};

		foreach ( $paths as $given ) {
			$resolved = $this->resolve( $given, $sandbox, $ledger, $filesystem );
			if ( null === $resolved ) {
				$had_error = true;
				continue;
			}
			try {
				$info = $filesystem->info( $resolved->real(), true );
			} catch ( Filesystem_Exception $failure ) {
				$this->report_filesystem( $failure, $given, $ledger );
				$had_error = true;
				continue;
			} catch ( Throwable $failure ) {
				// Preserve expected control-flow exceptions; contextualize unexpected failures.
				throw Unexpected_Failure::during( 'searching file content', $failure );
			}

			if ( ! $info->is_directory() ) {
				// Check before policy sampling: opening a FIFO blocks inside
				// fopen(), where neither a time nor memory budget can run.
				if ( ! $info->is_file() ) {
					$this->complain( $ledger, $given, 'not a regular file' );
					$had_error = true;
					continue;
				}
				try {
					$sandbox->assert_content_readable( $given, $resolved->real(), $filesystem );
				} catch ( Access_Denied $denied ) {
					$this->report_denied( $denied, $ledger, '', $resolved->is_standard_input() );
					$had_error = true;
					continue;
				} catch ( Throwable $failure ) {
					// Preserve expected control-flow exceptions; contextualize unexpected failures.
					throw Unexpected_Failure::during( 'searching file content', $failure );
				}
				try {
					$any_match = $this->search_file( $resolved, $pattern, $invocation, $show_names, $before, $after, $sandbox, $ledger, $filesystem, false ) || $any_match;
					if ( $this->pattern_failed ) {
						return Exit_Status::trouble();
					}
					if ( $quiet && $any_match ) {
						return Exit_Status::success();
					}
				} catch ( Line_Read_Limit_Exceeded $long ) {
					unset( $long );
					$this->skip_long_line( $given, $ledger );
				} catch ( Filesystem_Exception $failure ) {
					$this->report_filesystem( $failure, $given, $ledger );
					$had_error = true;
				} catch ( Throwable $failure ) {
					// Preserve expected control-flow exceptions; contextualize unexpected failures.
					throw Unexpected_Failure::during( 'searching file content', $failure );
				}
				continue;
			}

			if ( ! $recursive ) {
				$this->complain( $ledger, $given, 'Is a directory' );
				$had_error = true;
				continue;
			}

			foreach ( $walker->walk( $resolved, $info, 64, $exclude_dirs, $on_error ) as $entry ) {
				if ( 0 === $entry->depth() || ! $entry->info()->is_file() ) {
					continue;
				}
				$entry_path = $entry->path();
				if ( array() !== $include && ! Glob_Pattern::any_matches( $include, basename( $entry_path->given() ) ) ) {
					continue;
				}
				if ( array() !== $exclude && Glob_Pattern::any_matches( $exclude, basename( $entry_path->given() ) ) ) {
					continue;
				}
				if ( ! $sandbox->allows_content( $entry_path->real(), $filesystem, $entry_path->physical(), $entry_path->given() ) ) {
					$ledger->record_denial( $entry_path->given() );
					$this->denied_files[] = $entry_path->given();
					continue;
				}
				$ledger->tick();
				try {
					$any_match = $this->search_file( $entry_path, $pattern, $invocation, $show_names, $before, $after, $sandbox, $ledger, $filesystem, true ) || $any_match;
					if ( $this->pattern_failed ) {
						return Exit_Status::trouble();
					}
					if ( $quiet && $any_match ) {
						return Exit_Status::success();
					}
				} catch ( Line_Read_Limit_Exceeded $long ) {
					unset( $long );
					$this->skip_long_line( $entry_path->given(), $ledger );
				} catch ( Filesystem_Exception $failure ) {
					$this->report_filesystem( $failure, $entry_path->given(), $ledger );
					$had_error = true;
				} catch ( Throwable $failure ) {
					// Preserve expected control-flow exceptions; contextualize unexpected failures.
					throw Unexpected_Failure::during( 'searching file content', $failure );
				}
			}
		}

		if ( $had_error || $this->withheld ) {
			return Exit_Status::trouble();
		}
		if ( $this->long_line_files > 0 && ! $any_match ) {
			// A skipped file may have held the match: not an answer of "none".
			return Exit_Status::trouble();
		}
		$skipped_dirs += $walker->skipped();
		if ( array() !== $this->denied_files ) {
			$shown = array_slice( $this->denied_files, 0, 5 );
			$ledger->err( sprintf( "grep: %d file%s not searched by policy (may hold credentials): %s%s\n", count( $this->denied_files ), 1 === count( $this->denied_files ) ? '' : 's', implode( ', ', $shown ), count( $this->denied_files ) > 5 ? ', ...' : '' ) );
		}
		if ( array() !== $this->withheld_files ) {
			$shown = array_slice( $this->withheld_files, 0, 5 );
			$ledger->err( sprintf( "grep: %d file%s withheld by policy (the lines found hold credentials): %s%s\n", count( $this->withheld_files ), 1 === count( $this->withheld_files ) ? '' : 's', implode( ', ', $shown ), count( $this->withheld_files ) > 5 ? ', ...' : '' ) );
		}
		$literal = Pattern_Mode::BASIC === $pattern->mode() ? self::literal_in_basic( $pattern->pattern() ) : array();
		if ( ! $any_match && array() !== $literal ) {
			$quoted = array_map(
				static fn( string $c ): string => "'" . $c . "'",
				$literal
			);
			$ledger->err( sprintf( "grep: no matches. In a basic regular expression %s; pass -E (--extended-regexp) for alternation, groups, and + ? quantifiers, or -F (--fixed-strings) to match the text as typed\n", 1 === count( $quoted ) ? $quoted[0] . ' is a literal character' : implode( ' ', $quoted ) . ' are literal characters' ) );
		}
		if ( ! $any_match && $recursive && Tree_Walker::DEFAULT_EXCLUDE_DIRS === $exclude_dirs && $skipped_dirs > 0 ) {
			$ledger->err( 'grep: no matches; subdirectories named ' . implode( ', ', Tree_Walker::DEFAULT_EXCLUDE_DIRS ) . " were skipped by default (uploads was searched); pass --exclude-dir= (empty) to search them too\n" );
		}

		return $any_match ? Exit_Status::success() : Exit_Status::failure();
	}

	/**
	 * Characters a model tends to mean as regex operators that GNU basic
	 * syntax treats as literals, when they appear unescaped in the pattern.
	 *
	 * @param string $pattern The basic pattern.
	 * @return string[]
	 */
	private static function literal_in_basic( string $pattern ): array {
		$found  = array();
		$length = strlen( $pattern );
		for ( $i = 0; $i < $length; ++$i ) {
			$char = $pattern[ $i ];
			if ( '\\' === $char ) {
				++$i;
				continue;
			}
			if ( in_array( $char, array( '|', '(', ')', '+', '?', '{', '}' ), true ) && ! in_array( $char, $found, true ) ) {
				$found[] = $char;
			}
		}

		return $found;
	}

	/**
	 * Search one file and print what the options ask for.
	 *
	 * @param Resolved_Path        $target     The file.
	 * @param Search_Pattern       $pattern    The pattern.
	 * @param Invocation           $invocation The validated arguments.
	 * @param bool                 $show_names Whether lines carry the file name.
	 * @param int                  $before     Lines of context before each match.
	 * @param int                  $after      Lines of context after each match.
	 * @param Sandbox              $sandbox    The sandbox, whose content policy reads the lines printed.
	 * @param Budget_Ledger        $ledger     The ledger.
	 * @param Read_Only_Filesystem $filesystem The filesystem.
	 * @param bool                 $walked     Whether a recursive search found the file, rather than the caller naming it.
	 * @return bool Whether any line was selected.
	 */
	private function search_file( Resolved_Path $target, Search_Pattern $pattern, Invocation $invocation, bool $show_names, int $before, int $after, Sandbox $sandbox, Budget_Ledger $ledger, Read_Only_Filesystem $filesystem, bool $walked ): bool {
		$ledger->scan_file( $target->given() );
		// As GNU -I, always: a binary file, such as an archive, can hold text,
		// such as a configuration file, past the bytes the content checks
		// read, and counts, names or the exit status would reveal it a guess
		// at a time. Standard input is the previous stage's checked text.
		if ( ! $target->is_standard_input() && Content_Kind::BINARY === Content_Kind::detect( $filesystem->sample( $target->real(), Content_Kind::SAMPLE_BYTES ) ) ) {
			return false;
		}
		$quiet                = $invocation->flag( 'quiet' );
		$list_only            = $invocation->flag( 'files_with_matches' );
		$count_only           = $invocation->flag( 'count' );
		$invert               = $invocation->flag( 'invert_match' );
		$only_matching        = $invocation->flag( 'only_matching' ) && ! $invert;
		$this->pattern_failed = false;
		$numbers              = $invocation->flag( 'line_number' );
		$max_count            = $invocation->integer( 'max_count' );
		$prefix               = $show_names ? $target->label() : '';
		if ( $this->implicit_dot && 0 === strpos( $prefix, './' ) ) {
			$prefix = substr( $prefix, 2 );
		}
		$use_context = ( $before > 0 || $after > 0 ) && ! $list_only && ! $count_only;

		$count        = 0;
		$number       = 0;
		$pending      = 0;
		$last_printed = 0;
		/**
		 * Retained before-context lines, as array( number, line ).
		 *
		 * @var array<int, array{0: int, 1: string}> $trailing
		 */
		$trailing       = array();
		$trailing_bytes = 0;

		$separated = $this->printed_context_before;
		if ( $walked ) {
			// Like a file the denylist refused, a walked file whose lines are withheld is named once, at the end.
			$ledger->hold( new Printed_Source( $sandbox, $target->given(), '', Exit_Status::success() ) );
		} else {
			$this->hold_printed( $target, $sandbox, $ledger );
		}
		try {
			foreach ( $filesystem->lines( $target->real() ) as $raw ) {
				++$number;
				$ledger->check_time();
				$ledger->check_memory();
				$line = rtrim( $raw, "\r\n" );
				try {
					$is_match = $pattern->matches( $line ) !== $invert;
				} catch ( Pattern_Exception $expensive ) {
					$ledger->error_line( $target->given() . ':' . $number, $expensive->getMessage() );
					$this->pattern_failed = true;

					return false;
				} catch ( Throwable $failure ) {
					// Preserve expected control-flow exceptions; contextualize unexpected failures.
					throw Unexpected_Failure::during( 'searching file content', $failure );
				}

				if ( $is_match ) {
					++$count;
					if ( $quiet ) {
						return true;
					}
					if ( ! $count_only && ! $list_only ) {
						$ledger->record_match();
					}
					if ( $list_only ) {
						$ledger->out_label( $target->label() . "\n" );

						return true;
					}
					if ( ! $count_only ) {
						if ( $use_context ) {
							if ( ( $last_printed > 0 && $number - $last_printed > 1 && ( array() === $trailing || $trailing[0][0] - $last_printed > 1 ) ) || ( 0 === $last_printed && $this->printed_context_before ) ) {
								$ledger->out_label( "--\n" );
							}
							$this->printed_context_before = true;
							foreach ( $trailing as $retained ) {
								list( $trailing_number, $trailing_line ) = $retained;
								if ( $trailing_number > $last_printed ) {
									$ledger->out( $trailing_line . "\n", $this->prefix( $prefix, $trailing_number, $numbers, '-' ) );
									$last_printed                 = $trailing_number;
									$this->printed_context_before = true;
								}
							}
							$trailing       = array();
							$trailing_bytes = 0;
						}
						if ( $only_matching ) {
							try {
								$checkpoint = static function () use ( $ledger ): void {
									$ledger->check_time();
									$ledger->check_memory();
								};
								foreach ( $pattern->pieces( $line, $checkpoint ) as $piece ) {
									$ledger->out( $piece . "\n", $this->prefix( $prefix, $number, $numbers, ':' ) );
								}
							} catch ( Pattern_Exception $expensive ) {
								$ledger->error_line( $target->given() . ':' . $number, $expensive->getMessage() );
								$this->pattern_failed = true;

								return false;
							} catch ( Throwable $failure ) {
								// Preserve expected control-flow exceptions; contextualize unexpected failures.
								throw Unexpected_Failure::during( 'searching file content', $failure );
							}
						} else {
							$ledger->out( $line . "\n", $this->prefix( $prefix, $number, $numbers, ':' ) );
						}
						$last_printed = $number;
						$pending      = $after;
					}
					if ( null !== $max_count && $count >= $max_count ) {
						break;
					}
					continue;
				}

				if ( ! $use_context ) {
					continue;
				}
				if ( $pending > 0 ) {
					$ledger->out( $line . "\n", $this->prefix( $prefix, $number, $numbers, '-' ) );
					$last_printed = $number;
					--$pending;
					continue;
				}
				if ( $before > 0 ) {
					if ( count( $trailing ) >= $before ) {
						$removed         = array_shift( $trailing );
						$trailing_bytes -= strlen( $removed[1] );
					}
					// Fifty legal 1 MB lines are still too much retained context
					// on a small WordPress worker. Bound bytes as well as lines.
					if ( $trailing_bytes + strlen( $line ) > 4 * 1048576 ) {
						$ledger->truncate( 'before-context exceeds 4 MB; request fewer context lines; results are partial' );
					}
					$trailing[]      = array( $number, $line );
					$trailing_bytes += strlen( $line );
				}
			}

			if ( $count_only ) {
				$ledger->out_label( ( '' !== $prefix ? $prefix . ':' : '' ) . $count . "\n" );
			}

			return $count > 0;
		} finally {
			if ( ! $ledger->release() ) {
				// Nothing of this file was printed, so no group follows it.
				$this->printed_context_before = $separated;
				if ( $walked ) {
					$this->withheld_files[] = $target->given();
				} else {
					$this->withheld = true;
				}
			}
		}
	}

	/**
	 * Report a file stopped at a line longer than 1 MB and go on with the
	 * others. Lines printed before it stand; the result is partial.
	 *
	 * @param string        $given  The file as named.
	 * @param Budget_Ledger $ledger The ledger.
	 * @return void
	 */
	private function skip_long_line( string $given, Budget_Ledger $ledger ): void {
		++$this->long_line_files;
		if ( $this->implicit_dot && 0 === strpos( $given, './' ) ) {
			$given = substr( $given, 2 );
		}
		$ledger->transcript()->mark_truncated();
		$ledger->err( ( $ledger->transcript()->ends_with_newline() ? '' : "\n" ) . sprintf( "grep: %s: line longer than 1 MB, skipped\n", $given ) );
	}

	/**
	 * A file error line, unless -s silenced it.
	 *
	 * @param Budget_Ledger $ledger  The ledger.
	 * @param string        $subject The file.
	 * @param string        $message The error.
	 * @return void
	 */
	private function complain( Budget_Ledger $ledger, string $subject, string $message ): void {
		if ( ! $this->silent ) {
			$ledger->error_line( $subject, $message );
		}
	}

	/**
	 * The shell's error for a missing or unreadable file, unless -s silenced it.
	 *
	 * @param Filesystem_Exception $failure The condition.
	 * @param string               $given   The operand as the caller wrote it.
	 * @param Budget_Ledger        $ledger  The ledger.
	 * @param string               $verb    Words before the operand in messages.
	 * @return void
	 */
	protected function report_filesystem( Filesystem_Exception $failure, string $given, Budget_Ledger $ledger, string $verb = '' ): void {
		if ( ! $this->silent ) {
			parent::report_filesystem( $failure, $given, $ledger, $verb );
		}
	}

	/**
	 * The permission error for a refused file, unless -s silenced it. The
	 * refusal is still recorded.
	 *
	 * @param Access_Denied $denied         The refusal.
	 * @param Budget_Ledger $ledger         The ledger.
	 * @param string        $verb           Words before the operand in messages.
	 * @param bool          $standard_input Whether the refused input is standard input.
	 * @return string
	 */
	protected function denial_text( Access_Denied $denied, Budget_Ledger $ledger, string $verb = '', bool $standard_input = false ): string {
		return $this->silent ? '' : parent::denial_text( $denied, $ledger, $verb, $standard_input );
	}

	/**
	 * GNU grep exits 2 when a file cannot be read.
	 *
	 * @return Exit_Status
	 */
	protected static function refused_status(): Exit_Status {
		return Exit_Status::trouble();
	}

	/**
	 * GNU grep calls standard input "(standard input)", in prefixes and messages.
	 *
	 * @param string $given "-" or empty.
	 * @return string
	 */
	protected function standard_input_name( string $given ): string {
		unset( $given );

		return Standard_Input::DISPLAY;
	}

	/**
	 * What grep writes before a line: the file name, then the number, each
	 * followed by the separator.
	 *
	 * @param string $prefix    The file name, or empty.
	 * @param int    $number    The line number.
	 * @param bool   $numbers   Whether to print the number.
	 * @param string $separator ':' for a selected line, '-' for context.
	 * @return string
	 */
	private function prefix( string $prefix, int $number, bool $numbers, string $separator ): string {
		$out = '';
		if ( '' !== $prefix ) {
			$out .= $prefix . $separator;
		}
		if ( $numbers ) {
			$out .= $number . $separator;
		}

		return $out;
	}
}
