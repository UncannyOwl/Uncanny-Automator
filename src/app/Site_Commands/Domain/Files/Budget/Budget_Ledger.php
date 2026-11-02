<?php
/**
 * Counts what a running file command consumes.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command output is plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Budget;

use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Budget_Exceeded;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox\Printed_Source;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Transcript\Stream;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Transcript\Transcript;

/**
 * Counts what a running command consumes and is the only path by which a
 * command appends to a transcript, so neither truncation nor the content
 * policy can be bypassed; the runners write their own stage notices around
 * it.
 *
 * When a budget is exhausted the ledger writes one notice where the cut
 * happened, marks the transcript truncated, and throws Budget_Exceeded to
 * unwind the command.
 *
 * While a command prints from a file or standard input it holds the source
 * (hold()), and the ledger keeps what it writes until the source is
 * released, or a budget stops the command. The content policy then reads
 * the printed text: it is written, or the source's refusal is written in
 * place of its standard output (Printed_Source).
 */
final class Budget_Ledger {

	/**
	 * Bytes written.
	 *
	 * @var int
	 */
	private int $bytes = 0;

	/**
	 * Lines written.
	 *
	 * @var int
	 */
	private int $lines = 0;

	/**
	 * Files visited.
	 *
	 * @var int
	 */
	private int $files_scanned = 0;

	/**
	 * Grep matches counted.
	 *
	 * @var int
	 */
	private int $matches = 0;

	/**
	 * Calls to tick().
	 *
	 * @var int
	 */
	private int $ticks = 0;

	/**
	 * Paths the sandbox refused.
	 *
	 * @var string[]
	 */
	private array $denials = array();

	/**
	 * Monotonic time the budget started.
	 *
	 * @var float
	 */
	private float $started_at;

	/**
	 * True once a budget has stopped the command.
	 *
	 * @var bool
	 */
	private bool $exhausted = false;

	/**
	 * True once the output budget itself is spent, so nothing more is written.
	 *
	 * @var bool
	 */
	private bool $output_exhausted = false;

	/**
	 * The command name that prefixes notices.
	 *
	 * @var string
	 */
	private string $command_name;

	/**
	 * The bounds.
	 *
	 * @var Limits
	 */
	private Limits $limits;

	/**
	 * The clock.
	 *
	 * @var Clock
	 */
	private Clock $clock;

	/**
	 * Where output goes.
	 *
	 * @var Transcript
	 */
	private Transcript $transcript;

	/**
	 * The memory gauge, or null to skip memory checks.
	 *
	 * @var Memory_Gauge|null
	 */
	private ?Memory_Gauge $memory;

	/**
	 * The last path scanned, so a stop can say where it was.
	 *
	 * @var string|null
	 */
	private ?string $last_path = null;

	/**
	 * The source the command prints from, held for the content policy, or null.
	 *
	 * @var Printed_Source|null
	 */
	private ?Printed_Source $source = null;

	/**
	 * The policy's verdict on the held source once a budget stop made it
	 * judge early: true when written, false when withheld; null until then.
	 *
	 * @var bool|null
	 */
	private ?bool $verdict = null;

	/**
	 * What was written while the source was held, in order.
	 *
	 * @var Transcript
	 */
	private Transcript $held;

	/**
	 * The held standard output as the content policy reads it, without what
	 * the command added, such as a line number.
	 *
	 * @var string
	 */
	private string $held_printed = '';

	/**
	 * Whether the command added a prefix to any held text it printed from the source.
	 *
	 * @var bool
	 */
	private bool $held_prefixed = false;

	/**
	 * Sources whose printed text the content policy withheld.
	 *
	 * @var Printed_Source[]
	 */
	private array $withheld = array();

	/**
	 * Start counting.
	 *
	 * @param string            $command_name The command name that prefixes notices.
	 * @param Limits            $limits       The bounds.
	 * @param Clock             $clock        The clock.
	 * @param Transcript        $transcript   Where output goes.
	 * @param float|null        $started_at   Monotonic start time; now when null.
	 * @param Memory_Gauge|null $memory       The memory gauge, or null to skip memory checks.
	 */
	public function __construct( string $command_name, Limits $limits, Clock $clock, Transcript $transcript, ?float $started_at = null, ?Memory_Gauge $memory = null ) {
		$this->command_name = $command_name;
		$this->limits       = $limits;
		$this->clock        = $clock;
		$this->transcript   = $transcript;
		$this->memory       = $memory;
		$this->started_at   = $started_at ?? $clock->now();
		$this->held         = new Transcript();
	}

	/**
	 * The bounds.
	 *
	 * @return Limits
	 */
	public function limits(): Limits {
		return $this->limits;
	}

	/**
	 * Where output goes.
	 *
	 * @return Transcript
	 */
	public function transcript(): Transcript {
		return $this->transcript;
	}

	/**
	 * The clock.
	 *
	 * @return Clock
	 */
	public function clock(): Clock {
		return $this->clock;
	}

	/**
	 * Write to standard output: text the command printed from its source,
	 * after a prefix the command adds to it, such as a line number or a file
	 * name. The content policy reads the text without the prefix.
	 *
	 * @param string $text   Output text.
	 * @param string $prefix What the command adds before the text.
	 * @return void
	 *
	 * @throws Budget_Exceeded When the output budget is exhausted.
	 */
	public function out( string $text, string $prefix = '' ): void {
		$this->write( Stream::STDOUT, $prefix . $text, strlen( $prefix ) );
	}

	/**
	 * Write to standard output text the command adds about a source rather
	 * than prints from it, such as a header, a separator, a file name or a
	 * count. The content policy does not read it; names stay visible.
	 *
	 * @param string $text Output text.
	 * @return void
	 *
	 * @throws Budget_Exceeded When the output budget is exhausted.
	 */
	public function out_label( string $text ): void {
		$this->write( Stream::STDOUT, $text, strlen( $text ) );
	}

	/**
	 * Write to standard error.
	 *
	 * @param string $text Output text.
	 * @return void
	 *
	 * @throws Budget_Exceeded When the output budget is exhausted.
	 */
	public function err( string $text ): void {
		$this->write( Stream::STDERR, $text );
	}

	/**
	 * Write a command's findings: as out() or err(), and also after a time,
	 * file or memory budget stopped the command, so what it found before the
	 * stop still reaches the caller after the stop's notice. The output
	 * budget still applies; once it is spent, nothing more is written.
	 *
	 * @param string $text   Output text.
	 * @param string $stream One of the Stream constants.
	 * @return void
	 *
	 * @throws Budget_Exceeded When the output budget runs out before any budget stopped the command.
	 */
	public function report( string $text, string $stream = Stream::STDOUT ): void {
		if ( ! $this->exhausted ) {
			$this->write( $stream, $text );
			return;
		}
		if ( $this->output_exhausted ) {
			return;
		}
		$this->exhausted = false;
		try {
			$this->write( $stream, $text );
		} catch ( Budget_Exceeded $exceeded ) {
			// The output budget ran out now; its notice is written.
			unset( $exceeded );
		} finally {
			$this->exhausted = true;
		}
	}

	/**
	 * Shell-style error line: "command: path: strerror".
	 *
	 * @param string $subject What failed, usually a path.
	 * @param string $message The strerror text.
	 * @return void
	 */
	public function error_line( string $subject, string $message ): void {
		$this->err( $this->error_text( $subject, $message ) );
	}

	/**
	 * The shell-style error line, for writing later.
	 *
	 * @param string $subject What failed, usually a path.
	 * @param string $message The strerror text.
	 * @return string
	 */
	public function error_text( string $subject, string $message ): string {
		return sprintf( "%s: %s: %s\n", $this->command_name, $subject, $message );
	}

	/**
	 * Hold what the command writes while it prints from this source, until
	 * release(), so the content policy reads the printed text before the
	 * caller does. A source still held is released first.
	 *
	 * @param Printed_Source $source The file or standard input.
	 * @return void
	 */
	public function hold( Printed_Source $source ): void {
		$this->release();
		$this->source  = $source;
		$this->verdict = null;
	}

	/**
	 * Let the content policy read what was printed from the held source,
	 * then write it, or write the source's refusal in place of its standard
	 * output; standard error written meanwhile is kept, in order. Nothing
	 * happens when no source is held.
	 *
	 * @return bool False when the source was withheld, now or when a budget stopped the command.
	 */
	public function release(): bool {
		if ( null === $this->source ) {
			return true;
		}
		$written       = $this->verdict ?? $this->judge();
		$this->source  = null;
		$this->verdict = null;

		return $written;
	}

	/**
	 * Sources whose printed text the content policy withheld, oldest first.
	 *
	 * @return Printed_Source[]
	 */
	public function withheld(): array {
		return $this->withheld;
	}

	/**
	 * How many of these texts, written in turn, the output budgets would let
	 * through, whole or in part. Writes nothing, so a command that judges its
	 * output before printing any of it can judge only what would print.
	 *
	 * @param string[] $texts The texts, in the order they would be written.
	 * @return int
	 */
	public function texts_within_budget( array $texts ): int {
		$bytes = $this->bytes;
		$lines = $this->lines;
		$count = 0;
		foreach ( $texts as $text ) {
			$newlines = substr_count( $text, "\n" );
			if ( $bytes >= $this->limits->max_output_bytes() || ( $newlines > 0 && $lines >= $this->limits->max_output_lines() ) ) {
				break;
			}
			++$count;
			$bytes += strlen( $text );
			$lines += $newlines;
		}

		return $count;
	}

	/**
	 * Count one file visited by a walk or a read. Throws when over budget.
	 *
	 * @param string|null $path The path visited, for the notice.
	 * @return void
	 *
	 * @throws Budget_Exceeded When the files, time or memory budget is exhausted.
	 */
	public function scan_file( ?string $path = null ): void {
		if ( null !== $path ) {
			$this->last_path = $path;
		}
		if ( 0 === ( $this->files_scanned % 1024 ) ) {
			$this->check_memory();
		}
		++$this->files_scanned;
		$this->check_time();
		if ( $this->files_scanned > $this->limits->max_files_scanned() ) {
			$this->truncate( sprintf( 'stopped after scanning %d files%s; results are partial. %s', $this->limits->max_files_scanned(), $this->where_it_was(), $this->narrowing_hint( true ) ) );
		}
		$this->tick();
	}

	/**
	 * Count one grep match. Throws when over budget.
	 *
	 * @return void
	 *
	 * @throws Budget_Exceeded When the match budget is exhausted.
	 */
	public function record_match(): void {
		++$this->matches;
		if ( $this->matches > $this->limits->max_grep_matches() ) {
			$this->truncate( sprintf( 'output truncated at %d matches; narrow the pattern or use --include', $this->limits->max_grep_matches() ) );
		}
	}

	/**
	 * Cheap periodic wall-time check for tight loops.
	 *
	 * @return void
	 *
	 * @throws Budget_Exceeded When the time or memory budget is exhausted.
	 */
	public function tick(): void {
		if ( 0 !== ( ++$this->ticks % 256 ) ) {
			return;
		}
		$this->check_time();
		$this->check_memory();
	}

	/**
	 * Stop while there is still room for WordPress to finish the request.
	 * A PHP memory fatal cannot be caught; this is the guard before it.
	 *
	 * @return void
	 *
	 * @throws Budget_Exceeded When memory is nearly exhausted.
	 */
	public function check_memory(): void {
		if ( null === $this->memory ) {
			return;
		}
		$limit = $this->memory->limit();
		if ( $limit <= 0 ) {
			return;
		}
		$used = $this->memory->used();
		if ( $used > $limit - $this->limits->memory_headroom_bytes() ) {
			$this->truncate( sprintf( 'stopped: memory nearly exhausted (%d of %d MB in use); narrow the input or raise memory_limit', (int) round( $used / 1048576 ), (int) round( $limit / 1048576 ) ) );
		}
	}

	/**
	 * Remaining request time; never hand a zero timeout to a transport.
	 *
	 * @return float
	 *
	 * @throws Budget_Exceeded When no time is left.
	 */
	public function remaining_seconds(): float {
		$remaining = $this->limits->wall_time_seconds() - ( $this->clock->now() - $this->started_at );
		if ( $remaining <= 0.0 ) {
			$this->truncate( sprintf( 'stopped after %.1f seconds%s; results are partial. %s', $this->limits->wall_time_seconds(), $this->where_it_was(), $this->narrowing_hint( false ) ) );
		}

		return $remaining;
	}

	/**
	 * Stop when the wall time is spent.
	 *
	 * @return void
	 *
	 * @throws Budget_Exceeded When the time budget is exhausted.
	 */
	public function check_time(): void {
		if ( ( $this->clock->now() - $this->started_at ) > $this->limits->wall_time_seconds() ) {
			$this->truncate( sprintf( 'stopped after %.1f seconds%s; results are partial. %s', $this->limits->wall_time_seconds(), $this->where_it_was(), $this->narrowing_hint( false ) ) );
		}
	}

	/**
	 * Remember a sandbox denial so the caller can audit it.
	 *
	 * @param string $requested The path as the caller wrote it.
	 * @return void
	 */
	public function record_denial( string $requested ): void {
		$this->denials[] = $requested;
	}

	/**
	 * Paths the sandbox refused.
	 *
	 * @return string[]
	 */
	public function denials(): array {
		return $this->denials;
	}

	/**
	 * Append within the byte and line budgets, cutting at the limit.
	 *
	 * @param string $stream One of the Stream constants.
	 * @param string $text   Output text.
	 * @param int    $added  How many of its first bytes the command added rather than printed from its source.
	 * @return void
	 *
	 * @throws Budget_Exceeded When a budget is or was exhausted.
	 */
	private function write( string $stream, string $text, int $added = 0 ): void {
		if ( '' === $text ) {
			return;
		}
		if ( $this->exhausted ) {
			throw new Budget_Exceeded( 'Budget already exhausted.' );
		}

		$length   = strlen( $text );
		$newlines = substr_count( $text, "\n" );

		if ( $this->bytes + $length > $this->limits->max_output_bytes() ) {
			$room = max( 0, $this->limits->max_output_bytes() - $this->bytes );
			if ( $room > 0 ) {
				$this->emit( $stream, substr( $text, 0, $room ), $added );
				$this->bytes += $room;
			}
			$this->output_exhausted = true;
			$this->truncate( sprintf( 'output truncated at %d bytes; narrow the request or use head, tail, or sed -n', $this->limits->max_output_bytes() ) );
		}

		if ( $this->lines + $newlines > $this->limits->max_output_lines() ) {
			$room    = max( 0, $this->limits->max_output_lines() - $this->lines );
			$partial = $this->first_lines( $text, $room );
			if ( '' !== $partial ) {
				$this->emit( $stream, $partial, $added );
				$this->bytes += strlen( $partial );
				$this->lines += $room;
			}
			$this->output_exhausted = true;
			$this->truncate( sprintf( 'output truncated at %d lines; narrow the request: head or tail with -n %d or less, or sed -n with a range', $this->limits->max_output_lines(), $this->limits->max_output_lines() ) );
		}

		$this->emit( $stream, $text, $added );
		$this->bytes += $length;
		$this->lines += $newlines;
	}

	/**
	 * Write to the transcript, or keep the text while a source is held.
	 *
	 * @param string $stream One of the Stream constants.
	 * @param string $text   Output text, already within the budgets.
	 * @param int    $added  How many of its first bytes the command added.
	 * @return void
	 */
	private function emit( string $stream, string $text, int $added ): void {
		if ( null === $this->source || null !== $this->verdict ) {
			$this->transcript->write( $stream, $text );

			return;
		}
		$this->held->write( $stream, $text );
		if ( Stream::STDOUT === $stream ) {
			$from_source         = (string) substr( $text, $added );
			$this->held_printed .= $from_source;
			if ( $added > 0 && '' !== $from_source ) {
				$this->held_prefixed = true;
			}
		}
	}

	/**
	 * The content policy's verdict on the held source: write what was held,
	 * or withhold its standard output, give its bytes and lines back to the
	 * budgets, record the refusal for the audit, and write the source's
	 * refusal where its output began. Notices bypass the budgets, so this
	 * writes to the transcript directly.
	 *
	 * @return bool False when the source was withheld.
	 */
	private function judge(): bool {
		$source              = $this->source;
		$held                = $this->held;
		$printed             = $this->held_printed;
		$prefixed            = $this->held_prefixed;
		$this->held          = new Transcript();
		$this->held_printed  = '';
		$this->held_prefixed = false;
		if ( null === $source ) {
			return true;
		}

		// Nothing printed holds nothing: a search with no match costs no regex.
		if ( '' === $printed || $source->allows( $printed, $prefixed ) ) {
			foreach ( $held->chunks() as list( $stream, $text ) ) {
				$this->transcript->write( $stream, $text );
			}

			return true;
		}

		$this->withheld[] = $source;
		$this->record_denial( $source->requested() );
		$refused = false;
		foreach ( $held->chunks() as list( $stream, $text ) ) {
			if ( Stream::STDOUT !== $stream ) {
				$this->transcript->write( $stream, $text );
				continue;
			}
			$this->bytes -= strlen( $text );
			$this->lines -= substr_count( $text, "\n" );
			if ( ! $refused ) {
				$this->transcript->write( Stream::STDERR, $source->refusal() );
				$refused = true;
			}
		}

		return false;
	}

	/**
	 * The first $count lines of the text, terminators intact.
	 *
	 * @param string $text  Output text.
	 * @param int    $count Lines to keep.
	 * @return string
	 */
	private function first_lines( string $text, int $count ): string {
		if ( $count <= 0 ) {
			return '';
		}
		$offset = 0;
		for ( $i = 0; $i < $count; ++$i ) {
			$position = strpos( $text, "\n", $offset );
			if ( false === $position ) {
				return $text;
			}
			$offset = $position + 1;
		}

		return substr( $text, 0, $offset );
	}

	/**
	 * The path most recently given to scan_file(), or null.
	 *
	 * @return string|null
	 */
	public function current_path(): ?string {
		return $this->last_path;
	}

	/**
	 * How to narrow the stopped command, naming only options it has:
	 * find has -maxdepth and -not -path, grep has --include and
	 * --exclude-dir, du has --exclude-dir, and core verify-checksums takes no
	 * paths, so it is run again. Every other command can only be given fewer
	 * paths.
	 *
	 * @param bool $by_files True when the file count stopped it, false for time.
	 * @return string
	 */
	private function narrowing_hint( bool $by_files ): string {
		switch ( $this->command_name ) {
			case 'find':
				return $by_files
					? 'Narrow the path, add -maxdepth, or skip that directory with --exclude-dir or -not -path'
					: 'Narrow the path or skip that directory with --exclude-dir or -not -path';
			case 'grep':
				return 'Narrow the path, add --include, or skip that directory with --exclude-dir';
			case 'du':
				return 'Narrow the path or skip that directory with --exclude-dir';
			case 'core verify-checksums':
				return 'Run core verify-checksums again, as each call has its own time; find wp-admin wp-includes -mmin -1440 lists core files changed in the last day';
			default:
				return 'Narrow the path or pass fewer files';
		}
	}

	/**
	 * " while in <directory>" for a notice, so a stop names what was too big.
	 *
	 * @return string
	 */
	private function where_it_was(): string {
		if ( null === $this->last_path ) {
			return '';
		}
		$directory = false !== strpos( $this->last_path, '/' ) ? substr( $this->last_path, 0, (int) strrpos( $this->last_path, '/' ) ) : $this->last_path;

		return '' === $directory ? '' : ' while in ' . $directory;
	}

	/**
	 * Write the notice, mark the transcript truncated and unwind.
	 *
	 * @param string $reason Why the command stopped, with narrowing advice.
	 * @return never
	 *
	 * @throws Budget_Exceeded Always.
	 */
	public function truncate( string $reason ) {
		// What was printed before the stop is judged, and written or withheld, before the notice.
		if ( null !== $this->source && null === $this->verdict ) {
			$this->verdict = $this->judge();
		}
		$this->exhausted = true;
		$this->transcript->mark_truncated();
		// Every caller, including custom command limits, must make incomplete
		// output explicit. Keep the reason and its narrowing advice intact.
		if ( false === strpos( $reason, 'partial' ) ) {
			$reason .= '; results are partial';
		}
		$notice = sprintf( "%s: %s\n", $this->command_name, $reason );
		if ( ! $this->transcript->ends_with_newline() ) {
			$notice = "\n" . $notice;
		}
		$this->transcript->write( Stream::STDERR, $notice );

		throw new Budget_Exceeded( $reason );
	}
}
