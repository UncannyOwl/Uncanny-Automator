<?php
/**
 * How much time a command that changes the site may still use.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command errors are plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Domain;

/**
 * A command must answer before the request is cut off, by PHP, the web
 * server, or the client waiting for the response. Work that cannot be
 * interrupted, such as replacing a plugin's files, starts only when enough
 * time is left to finish it and answer; otherwise it is refused before
 * anything changes, with a message that says how much time it needed, what
 * limits the request, and what to do next.
 */
final class Time_Budget {

	/** Where WordPress updates are made by hand. */
	public const UPDATES_SCREEN = 'Dashboard › Updates';

	/**
	 * Unix time, in seconds, by which the command must have finished.
	 *
	 * @var float
	 */
	private float $deadline;

	/**
	 * The whole time a request has, from its start to the deadline.
	 *
	 * @var float
	 */
	private float $capacity;

	/**
	 * Returns the current Unix time in seconds.
	 *
	 * @var callable(): float
	 */
	private $clock;

	/**
	 * What limits the request, as a noun phrase, such as "this host's
	 * 30-second PHP time limit".
	 *
	 * @var string
	 */
	private string $limit;

	/**
	 * Seconds the request had spent when the budget started, such as on
	 * loading WordPress. A new request spends about as much.
	 *
	 * @var float
	 */
	private float $startup;

	/**
	 * Set the deadline.
	 *
	 * @param float             $deadline Unix time by which the command must have finished.
	 * @param float             $capacity The whole time the request has, from its start.
	 * @param callable(): float $clock    Current Unix time in seconds.
	 * @param string            $limit    What limits the request, for messages.
	 * @param float             $startup  Seconds the request had spent when the budget started.
	 */
	public function __construct( float $deadline, float $capacity, callable $clock, string $limit, float $startup = 0.0 ) {
		$this->deadline = $deadline;
		$this->capacity = $capacity;
		$this->clock    = $clock;
		$this->limit    = $limit;
		$this->startup  = max( 0.0, $startup );
	}

	/**
	 * Seconds left, never below zero.
	 *
	 * @return float
	 */
	public function remaining(): float {
		return max( 0.0, $this->deadline - (float) call_user_func( $this->clock ) );
	}

	/**
	 * Whether at least this many seconds are left.
	 *
	 * @param float $seconds Seconds needed.
	 * @return bool
	 */
	public function allows( float $seconds ): bool {
		return $this->remaining() >= $seconds;
	}

	/**
	 * What limits the request.
	 *
	 * @return string
	 */
	public function limit(): string {
		return $this->limit;
	}

	/**
	 * Refuse to start work that needs more time than is left.
	 *
	 * @param float  $seconds Seconds the work needs, with an allowance for answering.
	 * @param string $work    What would run, such as "Updating akismet".
	 * @param string $where   Where in WordPress to do it when it can never fit here.
	 * @return void
	 *
	 * @throws Command_Error When not enough time is left.
	 */
	public function require( float $seconds, string $work, string $where = self::UPDATES_SCREEN ): void {
		if ( ! $this->allows( $seconds ) ) {
			throw new Command_Error( $this->shortfall( $seconds, $work ) . ' Nothing was changed. ' . $this->next_step( $seconds, $where ) );
		}
	}

	/**
	 * Why work was not started, without saying what changed.
	 *
	 * @param float  $seconds Seconds the work needs.
	 * @param string $work    What would run.
	 * @return string
	 */
	public function shortfall( float $seconds, string $work ): string {
		return sprintf(
			'%s was not started: it needs about %d seconds, and %d are left of %s.',
			$work,
			(int) ceil( $seconds ),
			(int) floor( $this->remaining() ),
			$this->limit
		);
	}

	/**
	 * What to do about work that did not fit: run it again in a new request,
	 * or, when it can never fit on this host, use WordPress itself. A new
	 * request has the whole time less what starting it takes.
	 *
	 * @param float  $seconds Seconds the work needs.
	 * @param string $where   Where in WordPress to do it when it can never fit here.
	 * @return string
	 */
	public function next_step( float $seconds, string $where = self::UPDATES_SCREEN ): string {
		return $seconds > $this->capacity - $this->startup
			? sprintf( 'It can never fit in one request on this host; use %s in WordPress instead.', $where )
			: 'Run the command again; a new request has the full time.';
	}
}
