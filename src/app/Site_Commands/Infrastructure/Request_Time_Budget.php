<?php
/**
 * The time budget of the current request.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Infrastructure;

use Uncanny_Automator\App\Site_Commands\Domain\Time_Budget;

/**
 * Two limits end a request, and the earlier one counts, measured on the wall
 * clock from the start of the request:
 *
 * - The response limit, RESPONSE_SECONDS. Web servers and AI apps stop
 *   waiting for an answer after a time nothing on the site can see; common
 *   ones are 40 seconds (Apache mod_fcgid) and 60 (nginx, PHP-FPM setups,
 *   MCP clients). A command keeps the whole request under the lowest.
 * - The host's PHP time limit, max_execution_time, as the host set it. The
 *   MCP router raises PHP's limit for slow actions before tools run, so the
 *   value is recorded first (remember_host_limit()). A host with a short limit
 *   often enforces it outside PHP as well, where raising it changes nothing,
 *   so that limit is kept.
 *
 * ANSWER_SECONDS is kept back for formatting and sending the answer.
 */
final class Request_Time_Budget {

	/** Longest a request that changes the site may take, from its start. */
	public const RESPONSE_SECONDS = 40;

	/** Kept back for formatting and sending the answer. */
	public const ANSWER_SECONDS = 3;

	/**
	 * The host's PHP time limit before anything in this request raised it,
	 * or null when it was not recorded.
	 *
	 * @var int|null
	 */
	private static ?int $host_limit = null;

	/**
	 * Record the host's PHP time limit before the request raises it. Only the
	 * first value counts.
	 *
	 * @param int $seconds max_execution_time as the host set it; 0 for none.
	 * @return void
	 */
	public static function remember_host_limit( int $seconds ): void {
		if ( null === self::$host_limit ) {
			self::$host_limit = max( 0, $seconds );
		}
	}

	/**
	 * Forget the recorded limit, for tests.
	 *
	 * @return void
	 */
	public static function forget_host_limit(): void {
		self::$host_limit = null;
	}

	/**
	 * The budget of the current request.
	 *
	 * @return Time_Budget
	 */
	public static function start(): Time_Budget {
		$now        = microtime( true );
		$start      = self::request_start( $now );
		$host_limit = self::$host_limit ?? max( 0, (int) ini_get( 'max_execution_time' ) );
		$seconds    = $host_limit > 0 && $host_limit < self::RESPONSE_SECONDS ? $host_limit : self::RESPONSE_SECONDS;
		$limit      = $seconds < self::RESPONSE_SECONDS
			? sprintf( 'this host\'s %d-second PHP time limit (max_execution_time)', $seconds )
			: sprintf( 'the %d seconds each site_manage request may take, so the answer arrives before web servers and AI apps stop waiting', self::RESPONSE_SECONDS );

		return new Time_Budget(
			$start + $seconds - self::ANSWER_SECONDS,
			(float) ( $seconds - self::ANSWER_SECONDS ),
			static function (): float {
				return microtime( true );
			},
			$limit,
			$now - $start
		);
	}

	/**
	 * When the request started: the server's timestamp, unless it is missing,
	 * in the future, or too old to belong to this request.
	 *
	 * @param float $now Current Unix time.
	 * @return float
	 */
	private static function request_start( float $now ): float {
		$start = $_SERVER['REQUEST_TIME_FLOAT'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Set by PHP, never by the client; only used as a number after the check below.

		return is_numeric( $start ) && (float) $start <= $now && (float) $start > $now - HOUR_IN_SECONDS ? (float) $start : $now;
	}
}
