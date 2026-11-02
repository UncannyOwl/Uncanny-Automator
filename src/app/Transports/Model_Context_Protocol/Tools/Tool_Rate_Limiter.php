<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Atomic_Option_Lock;

/**
 * Small, site-local fixed-window limiter for MCP tool invocations.
 *
 * One transient per site, principal and tool holds the current minute and
 * its count and is rewritten in place, so a busy site without an object
 * cache does not gain two new option rows every minute per tool.
 */
final class Tool_Rate_Limiter {

	private const LOCK_TTL = 5;

	private const LOCK_WAIT = 1;

	private Atomic_Option_Lock $lock;

	private int $retry_after = 0;

	/**
	 * Constructor.
	 *
	 * @param Atomic_Option_Lock|null $lock Atomic site-option lock boundary.
	 */
	public function __construct( ?Atomic_Option_Lock $lock = null ) {
		$this->lock = $lock ?? new Atomic_Option_Lock();
	}

	/**
	 * Allow an invocation for the current principal and tool.
	 */
	public function allows( int $user_id, string $tool_name ): bool {
		$this->retry_after = 0;
		/**
		 * Maximum calls per principal, site, and tool per minute.
		 */
		$limit = (int) apply_filters( 'automator_mcp_tool_rate_limit_per_minute', 60, $tool_name, $user_id );
		if ( $limit < 1 ) {
			return false;
		}

		$key       = self::get_counter_key( $user_id, $tool_name );
		$lock_name = self::get_lock_name( $user_id, $tool_name );
		$owner     = $this->lock->acquire( $lock_name, self::LOCK_TTL, self::LOCK_WAIT );

		// Lock contention means another call for the same principal and tool
		// is being counted right now, not that the limit was reached. Refusing
		// here turned two parallel calls into a false "rate limit exceeded";
		// the call proceeds uncounted instead.
		if ( false === $owner ) {
			return true;
		}

		try {
			$now    = time();
			$window = (int) floor( $now / MINUTE_IN_SECONDS );
			$state  = get_transient( $key );
			$count  = is_array( $state ) && (int) ( $state['window'] ?? 0 ) === $window
				? (int) ( $state['count'] ?? 0 )
				: 0;

			if ( $count >= $limit ) {
				$this->retry_after = MINUTE_IN_SECONDS - ( $now % MINUTE_IN_SECONDS );
				return false;
			}

			set_transient(
				$key,
				array(
					'window' => $window,
					'count'  => $count + 1,
				),
				2 * MINUTE_IN_SECONDS
			);

			return true;
		} finally {
			$this->lock->release( $lock_name, $owner );
		}
	}

	/**
	 * Seconds until the refused invocation's window resets, or zero when disabled.
	 *
	 * @return int
	 */
	public function retry_after(): int {
		return $this->retry_after;
	}

	/**
	 * Transient name holding the window counter for a principal and tool.
	 *
	 * @param int    $user_id   Principal.
	 * @param string $tool_name Tool.
	 * @return string
	 */
	public static function get_counter_key( int $user_id, string $tool_name ): string {
		return 'automator_mcp_rate_' . md5( get_current_blog_id() . ':' . $user_id . ':' . $tool_name );
	}

	/**
	 * Option name of the lock guarding a principal and tool counter.
	 *
	 * @param int    $user_id   Principal.
	 * @param string $tool_name Tool.
	 * @return string
	 */
	public static function get_lock_name( int $user_id, string $tool_name ): string {
		return 'automator_mcp_rate_lock_' . md5( get_current_blog_id() . ':' . $user_id . ':' . $tool_name );
	}
}
