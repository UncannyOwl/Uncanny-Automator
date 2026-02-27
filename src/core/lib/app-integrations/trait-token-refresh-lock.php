<?php
/**
 * Token Refresh Lock Trait
 *
 * Provides mutex-based locking to prevent concurrent token refresh attempts.
 * This trait is designed to work with classes extending the abstract Api_Caller class.
 *
 * IMPORTANT: Token management should ideally be offloaded to the API proxy via
 * vault credential management whenever possible. This trait is intended for
 * edge cases where in-plugin token refresh is required, such as:
 *
 * - Integrations where users configure their own OAuth apps (e.g., GoTo products)
 * - Legacy integrations being migrated that require backwards compatibility
 * - Integrations with specific redirect_uri constraints
 *
 * When implementing new integrations, always prefer API proxy token management
 * over in-plugin refresh logic.
 *
 * @since   7.1
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator\App_Integrations;

use Exception;

/**
 * Trait Token_Refresh_Lock
 *
 * Usage:
 * 1. Use this trait in your Api_Caller class
 * 2. The lock key is auto-generated from helpers->get_settings_id()
 * 3. Call handle_token_refresh_with_lock() in your token refresh logic
 *
 * Example:
 * ```php
 * class My_Api_Caller extends Api_Caller {
 *     use Token_Refresh_Lock;
 *
 *     public function prepare_request_credentials( $credentials, $args ) {
 *         $expires_at = $credentials['expires_at'] ?? 0;
 *         if ( $this->is_token_expiring( $expires_at ) ) {
 *             $credentials = $this->handle_token_refresh_with_lock(
 *                 $credentials,
 *                 array( $this, 'refresh_and_store_token' )
 *             );
 *         }
 *         return $credentials;
 *     }
 * }
 * ```
 */
trait Token_Refresh_Lock {

	/**
	 * The transient key used for the refresh lock.
	 *
	 * @var string
	 */
	private $token_refresh_lock_key = '';

	/**
	 * Buffer time in seconds before token expiry to trigger refresh.
	 *
	 * @var int
	 */
	private $token_refresh_buffer_seconds = 300; // 5 minutes

	/**
	 * Maximum time in seconds to wait for another request to complete token refresh.
	 *
	 * @var int
	 */
	private $token_refresh_wait_timeout = 5;

	/**
	 * Lock duration in seconds. Should be longer than expected refresh time.
	 *
	 * @var int
	 */
	private $token_refresh_lock_duration = 15;

	/**
	 * Set a custom token refresh lock key.
	 *
	 * Optional - by default the lock key is auto-generated from helpers->get_settings_id().
	 * Use this only if you need a custom key.
	 *
	 * @param string $lock_key The transient key for the lock.
	 *
	 * @return void
	 */
	protected function set_token_refresh_lock_key( $lock_key ) {
		$this->token_refresh_lock_key = $lock_key;
	}

	/**
	 * Get the token refresh lock key.
	 *
	 * Auto-generates from helpers->get_settings_id() if not explicitly set.
	 *
	 * @return string The lock key.
	 * @throws Exception If lock key cannot be determined.
	 */
	protected function get_token_refresh_lock_key() {

		// Return custom key if set.
		if ( ! empty( $this->token_refresh_lock_key ) ) {
			return $this->token_refresh_lock_key;
		}

		// Auto-generate from helpers settings_id.
		if ( isset( $this->helpers ) && method_exists( $this->helpers, 'get_settings_id' ) ) {
			$settings_id = $this->helpers->get_settings_id();
			if ( ! empty( $settings_id ) ) {
				return 'automator_' . str_replace( '-', '_', $settings_id ) . '_token_refresh_lock';
			}
		}

		throw new Exception( 'Token refresh lock key could not be determined. Ensure helpers->get_settings_id() is available or call set_token_refresh_lock_key().' );
	}

	/**
	 * Set the buffer time before token expiry to trigger refresh.
	 *
	 * @param int $seconds Buffer time in seconds.
	 *
	 * @return void
	 */
	protected function set_token_refresh_buffer_seconds( $seconds ) {
		$this->token_refresh_buffer_seconds = absint( $seconds );
	}

	/**
	 * Get the buffer time before token expiry to trigger refresh.
	 *
	 * @return int Buffer time in seconds.
	 */
	protected function get_token_refresh_buffer_seconds() {
		return $this->token_refresh_buffer_seconds;
	}

	/**
	 * Set the maximum wait timeout for concurrent refresh.
	 *
	 * @param int $seconds Wait timeout in seconds.
	 *
	 * @return void
	 */
	protected function set_token_refresh_wait_timeout( $seconds ) {
		$this->token_refresh_wait_timeout = absint( $seconds );
	}

	/**
	 * Get the maximum wait timeout for concurrent refresh.
	 *
	 * @return int Wait timeout in seconds.
	 */
	protected function get_token_refresh_wait_timeout() {
		return $this->token_refresh_wait_timeout;
	}

	/**
	 * Set the lock duration.
	 *
	 * @param int $seconds Lock duration in seconds.
	 *
	 * @return void
	 */
	protected function set_token_refresh_lock_duration( $seconds ) {
		$this->token_refresh_lock_duration = absint( $seconds );
	}

	/**
	 * Get the lock duration.
	 *
	 * @return int Lock duration in seconds.
	 */
	protected function get_token_refresh_lock_duration() {
		return $this->token_refresh_lock_duration;
	}

	/**
	 * Handle token refresh with locking to prevent concurrent refreshes.
	 *
	 * @param array    $credentials      The current credentials.
	 * @param callable $refresh_callback Callback that performs the actual refresh.
	 *                                   Should accept $credentials and return refreshed credentials.
	 *
	 * @return array The refreshed credentials.
	 * @throws Exception If token refresh fails or times out.
	 */
	protected function handle_token_refresh_with_lock( $credentials, $refresh_callback ) {

		// Try to acquire the lock atomically.
		$lock_acquired = $this->acquire_refresh_lock();

		if ( $lock_acquired ) {
			try {
				// We acquired the lock - perform the refresh.
				$credentials = call_user_func( $refresh_callback, $credentials );
			} finally {
				// Always release the lock.
				$this->release_refresh_lock();
			}
		} else {
			// Another request is refreshing - wait for completion.
			$this->wait_for_refresh_completion();

			// Fetch the latest credentials (refreshed by the other request).
			$credentials = $this->helpers->get_credentials();
		}

		return $credentials;
	}

	/**
	 * Attempt to acquire the refresh lock atomically.
	 *
	 * @return bool True if lock was acquired, false if already locked.
	 */
	protected function acquire_refresh_lock() {

		$lock_key = $this->get_token_refresh_lock_key();

		// Check if lock exists - if so, another process is refreshing.
		if ( get_transient( $lock_key ) ) {
			return false;
		}

		// Set lock with configured duration.
		set_transient( $lock_key, true, $this->get_token_refresh_lock_duration() );

		return true;
	}

	/**
	 * Release the refresh lock.
	 *
	 * @return void
	 */
	protected function release_refresh_lock() {
		delete_transient( $this->get_token_refresh_lock_key() );
	}

	/**
	 * Wait for another request to complete token refresh.
	 *
	 * @return void
	 * @throws Exception If wait times out.
	 */
	protected function wait_for_refresh_completion() {

		$lock_key   = $this->get_token_refresh_lock_key();
		$timeout    = $this->get_token_refresh_wait_timeout();
		$start_time = time();

		while ( get_transient( $lock_key ) ) {
			if ( time() - $start_time >= $timeout ) {
				throw new Exception(
					esc_html__( 'Token refresh timeout. Please try again.', 'uncanny-automator' ),
					500
				);
			}
			usleep( 100000 ); // 100ms polling interval.
		}
	}

	/**
	 * Check if token is expired, about to expire, or missing expiry data.
	 *
	 * @param int $expires_at Unix timestamp when token expires.
	 *
	 * @return bool True if token needs refresh.
	 */
	protected function is_token_expiring( $expires_at ) {
		// If expires_at is not set, trigger refresh to get proper expiry data.
		if ( $expires_at <= 0 ) {
			return true;
		}

		$buffer = $this->get_token_refresh_buffer_seconds();

		return time() > ( $expires_at - $buffer );
	}
}
