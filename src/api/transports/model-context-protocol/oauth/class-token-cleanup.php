<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\OAuth;

/**
 * Token Cleanup.
 *
 * Handles automated cleanup of expired MCP tokens.
 *
 * @since 7.0.0
 */
class Token_Cleanup {

	/**
	 * Token manager instance.
	 *
	 * @since 7.0.0
	 * @var Token_Manager
	 */
	private Token_Manager $token_manager;

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 */
	public function __construct() {
		$this->token_manager = new Token_Manager();
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Schedule cleanup on plugin activation.
		register_activation_hook( __FILE__, array( $this, 'schedule_cleanup' ) );

		// Unschedule on deactivation.
		register_deactivation_hook( __FILE__, array( $this, 'unschedule_cleanup' ) );

		// Hook into the scheduled event.
		add_action( 'automator_mcp_token_cleanup', array( $this, 'run_cleanup' ) );

		// Add cleanup to daily maintenance if not already scheduled.
		add_action( 'init', array( $this, 'ensure_cleanup_scheduled' ) );
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
			// Schedule daily cleanup at 3 AM.
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
		try {
			$cleaned_count = $this->token_manager->cleanup_expired_tokens();

			// Log cleanup results.
			do_action(
				'automator_mcp_token_event',
				array(
					'event_type'    => 'automated_cleanup',
					'cleaned_count' => $cleaned_count,
					'timestamp'     => time(),
				)
			);

		} catch ( \Exception $e ) {

			// Log cleanup failure.
			do_action(
				'automator_mcp_token_event',
				array(
					'event_type' => 'cleanup_failed',
					'error'      => $e->getMessage(),
					'timestamp'  => time(),
				)
			);
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
		if ( ! current_user_can( automator_get_admin_capability() ) ) {
			return 0;
		}

		$cleaned_count = $this->token_manager->cleanup_expired_tokens();

		// Log manual cleanup.
		do_action(
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
		$total_tokens = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s",
				'automator_mcp_tokens_encrypted'
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
