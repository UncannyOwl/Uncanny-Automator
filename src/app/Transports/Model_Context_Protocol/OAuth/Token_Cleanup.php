<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server\OAuth_Artifact_Store;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server\OAuth_Pending_Registration_Cleanup;

/**
 * Token Cleanup.
 *
 * Handles automated cleanup of expired MCP tokens.
 *
 * @since 7.0.0
 */
class Token_Cleanup {

	private const CLEANUP_LOCK           = 'automator_mcp_cleanup_run_lock';
	private const LAST_RUN               = 'automator_mcp_token_cleanup_last_run';
	private const CHECK_AFTER            = 'automator_mcp_token_cleanup_check_after';
	private const IN_PROGRESS            = 'automator_mcp_token_cleanup_in_progress';
	private const OPPORTUNISTIC_INTERVAL = 60;

	/**
	 * Token manager instance.
	 *
	 * @since 7.0.0
	 * @var Token_Manager
	 */
	private Token_Manager $token_manager;

	private OAuth_Artifact_Store $oauth_artifacts;

	private OAuth_Pending_Registration_Cleanup $pending_registrations;

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 */
	public function __construct() {
		$this->token_manager         = new Token_Manager();
		$this->oauth_artifacts       = new OAuth_Artifact_Store();
		$this->pending_registrations = new OAuth_Pending_Registration_Cleanup();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function init(): void {
		// Hook into the scheduled event.
		add_action( 'automator_mcp_token_cleanup', array( $this, 'run_cleanup' ) );
		add_action( OAuth_Pending_Registration_Cleanup::HOOK, array( $this->pending_registrations, 'run' ), 10, 0 );

		// Add cleanup to daily maintenance if not already scheduled.
		add_action( 'init', array( $this, 'ensure_cleanup_scheduled' ) );
		add_action( 'shutdown', array( $this, 'maybe_run_opportunistic' ) );
	}

	/**
	 * Deactivation callback registered from the actual plugin bootstrap file.
	 */
	public static function deactivate(): void {
		( new self() )->unschedule_cleanup();
	}

	/**
	 * Schedule the cleanup cron job.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function schedule_cleanup(): void {
		if ( ! wp_next_scheduled( 'automator_mcp_token_cleanup' ) ) {
			// 'tomorrow 3:00 AM' resolves in PHP's timezone, which WordPress
			// pins to UTC, so the low-traffic-hour intent is approximate.
			wp_schedule_event(
				strtotime( 'tomorrow 3:00 AM' ),
				'daily',
				'automator_mcp_token_cleanup'
			);
		}
	}

	/**
	 * Unschedule the cleanup cron job.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function unschedule_cleanup(): void {
		$timestamp = wp_next_scheduled( 'automator_mcp_token_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'automator_mcp_token_cleanup' );
		}
		wp_clear_scheduled_hook( OAuth_Pending_Registration_Cleanup::HOOK );
	}

	/**
	 * Ensure cleanup is scheduled.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function ensure_cleanup_scheduled(): void {
		if ( ! wp_next_scheduled( 'automator_mcp_token_cleanup' ) ) {
			$this->schedule_cleanup();
		}
	}

	/**
	 * Run the token cleanup process.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function run_cleanup(): void {
		$this->run_cleanup_batch( false );
	}

	/**
	 * Continue unfinished or missed cleanup without relying on a loopback.
	 *
	 * @since 8.0.0
	 */
	public function maybe_run_opportunistic(): void {
		if ( wp_installing() || ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) ) {
			return;
		}
		// Ordinary page loads read one timestamp, not every cleanup cursor.
		// Check again in a minute so partial runs still recover when WP-Cron is disabled.
		$now = time();
		if ( (int) get_option( self::CHECK_AFTER, 0 ) > $now ) {
			return;
		}
		update_option( self::CHECK_AFTER, $now + self::OPPORTUNISTIC_INTERVAL, false );
		$next       = wp_next_scheduled( 'automator_mcp_token_cleanup' );
		$unfinished = (bool) get_option( self::IN_PROGRESS, false )
			|| (int) get_option( 'automator_mcp_token_credential_cleanup_cursor', 0 ) > 0
			|| (bool) get_option( 'automator_mcp_token_credential_cleanup_retry', false )
			|| (int) get_option( 'automator_mcp_oauth_artifact_cleanup_cursor', 0 ) > 0
			|| (bool) get_option( 'automator_mcp_oauth_artifact_cleanup_retry', false );
		if ( ! $unfinished ) {
			if ( $next && $next > time() - MINUTE_IN_SECONDS ) {
				return;
			}
			if ( (int) get_option( self::LAST_RUN, 0 ) > time() - DAY_IN_SECONDS ) {
				return;
			}
		}

		$this->run_cleanup_batch( true );
	}

	/**
	 * Serialize cleanup and throttle opportunistic runs across PHP requests.
	 *
	 * @since 8.0.0
	 */
	private function run_cleanup_batch( bool $opportunistic ): void {
		$lock  = new Atomic_Option_Lock();
		$owner = $lock->acquire( self::CLEANUP_LOCK, 300, 0 );
		if ( false === $owner ) {
			return;
		}

		try {
			wp_cache_delete( self::LAST_RUN, 'options' );
			wp_cache_delete( 'notoptions', 'options' );
			$last_run = (int) get_option( self::LAST_RUN, 0 );
			if ( $opportunistic && $last_run > time() - self::OPPORTUNISTIC_INTERVAL ) {
				return;
			}
			if ( ! update_option( self::IN_PROGRESS, 1, false ) ) {
				wp_cache_delete( self::IN_PROGRESS, 'options' );
				wp_cache_delete( 'notoptions', 'options' );
				if ( 1 !== (int) get_option( self::IN_PROGRESS, 0 ) ) {
					return;
				}
			}
			update_option( self::LAST_RUN, time(), false );
			$cleaned_count  = $this->token_manager->cleanup_expired_tokens();
			$artifact_count = $this->oauth_artifacts->cleanup_expired( 50 );
			if ( ! $opportunistic ) {
				$this->pending_registrations->run();
			}
			if ( $this->token_manager->cleanup_was_confirmed() && $this->oauth_artifacts->cleanup_was_confirmed() ) {
				update_option( self::IN_PROGRESS, 0, false );
			}

			// Log cleanup results.
			Dispatcher::action(
				'automator_mcp_token_event',
				array(
					'event_type'     => 'automated_cleanup',
					'cleaned_count'  => $cleaned_count,
					'artifact_count' => $artifact_count,
					'timestamp'      => time(),
				)
			);

		} catch ( \Exception $e ) {

			// Log cleanup failure.
			Dispatcher::action(
				'automator_mcp_token_event',
				array(
					'event_type' => 'cleanup_failed',
					'error'      => $e->getMessage(),
					'timestamp'  => time(),
				)
			);
		} finally {
			$lock->release( self::CLEANUP_LOCK, $owner );
		}
	}

	/**
	 * Manual cleanup trigger for administrators.
	 *
	 * @since 7.0.0
	 *
	 * @return int Number of tokens cleaned up.
	 */
	public function manual_cleanup(): int {
		if ( ! current_user_can( 'manage_options' ) ) {
			return 0;
		}

		$cleaned_count = $this->token_manager->cleanup_expired_tokens();

		// Log manual cleanup.
		Dispatcher::action(
			'automator_mcp_token_event',
			array(
				'event_type'    => 'manual_cleanup',
				'cleaned_count' => $cleaned_count,
				'user_id'       => get_current_user_id(),
				'timestamp'     => time(),
			)
		);

		return $cleaned_count;
	}

	/**
	 * Get cleanup statistics.
	 *
	 * @since 7.0.0
	 *
	 * @return array Cleanup statistics.
	 */
	public function get_cleanup_stats(): array {
		global $wpdb;

		// Count total encrypted tokens across all users.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Counts usermeta rows for an admin statistic; live count, must not be cached.
		$total_tokens = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s",
				// Site-suffixed on multisite; the bare key would count zero there.
				( new Token_Site_Policy() )->get_token_meta_key()
			)
		);

		// Get next scheduled cleanup.
		$next_cleanup = wp_next_scheduled( 'automator_mcp_token_cleanup' );

		return array(
			'total_users_with_tokens' => (int) $total_tokens,
			'next_cleanup_scheduled'  => $next_cleanup ? date_i18n( 'Y-m-d H:i:s', $next_cleanup ) : 'Not scheduled',
			'cleanup_hook_registered' => has_action( 'automator_mcp_token_cleanup' ),
		);
	}
}
