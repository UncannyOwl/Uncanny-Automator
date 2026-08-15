<?php
/**
 * MCP allocation-facts cache refresh.
 *
 * @package Uncanny_Automator
 * @since 7.5.1.1
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Feature_State\Infrastructure;

use Uncanny_Automator\App\Infrastructure\License\License_Manager;

/**
 * Refreshes the local allocation observation before admin menus use it.
 */
final class Mcp_Allocation_Facts_Refresh {

	public const FAILURE_TRANSIENT = 'automator_llm_allocation_facts_failed';
	public const LOCK_OPTION       = 'automator_llm_allocation_facts_refresh_lock';
	public const CACHE_DURATION    = 5 * MINUTE_IN_SECONDS;
	public const FAILURE_DURATION  = MINUTE_IN_SECONDS;
	public const LOCK_DURATION     = 30;

	private License_Manager $licenses;
	private string $endpoint;
	private bool $attempted = false;

	/**
	 * @param License_Manager $licenses Automator license manager.
	 * @param string          $base_url MCP service base URL.
	 */
	public function __construct( License_Manager $licenses, string $base_url ) {
		$parts = wp_parse_url( $base_url );

		if (
			! is_array( $parts )
			|| empty( $parts['host'] )
			|| ! isset( $parts['scheme'] )
			|| ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true )
		) {
			throw new \InvalidArgumentException( 'The MCP allocation service URL is invalid.' );
		}

		$this->licenses = $licenses;
		$this->endpoint = untrailingslashit( $base_url ) . '/api/credits/allocation-facts';
	}

	/**
	 * Register refresh work outside the policy query.
	 *
	 * @return void
	 */
	public function register(): void {
		// WordPress fires admin_menu before admin_init. Warm at priority 1 so Setup
		// Wizard and Page Builder policy checks at the default priority see the new
		// observation instead of memoizing one all-hidden request first.
		add_action( 'admin_menu', array( $this, 'refresh_if_needed' ), 1 );

		// Front-end admin-bar surfaces and the launcher REST callback do not pass
		// through admin_menu. Give each a request-lifecycle seam before it evaluates
		// the cache-only feature policy; anonymous traffic and unrelated REST routes
		// deliberately do not initiate allocation reads.
		add_action( 'wp', array( $this, 'refresh_frontend_if_needed' ), 1, 0 );
		add_filter( 'rest_dispatch_request', array( $this, 'refresh_launcher_rest_if_needed' ), 1, 2 );
		add_action( 'automator_daily_healthcheck', array( $this, 'refresh' ) );
	}

	/**
	 * Warm allocation facts for logged-in front-end presentation surfaces.
	 *
	 * @return void
	 */
	public function refresh_frontend_if_needed(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$this->refresh_if_needed();
	}

	/**
	 * Warm allocation facts immediately before the launcher REST callback.
	 *
	 * rest_dispatch_request runs after WordPress has authenticated the request and
	 * accepted its permission callback. Returning the incoming dispatch value
	 * unchanged preserves third-party short-circuits and normal REST dispatch.
	 *
	 * @param mixed            $dispatch_result Existing dispatch result, normally null.
	 * @param \WP_REST_Request $request         Matched REST request.
	 *
	 * @return mixed
	 */
	public function refresh_launcher_rest_if_needed( $dispatch_result, $request ) {
		if (
			null !== $dispatch_result
			|| ! $request instanceof \WP_REST_Request
			|| 1 !== preg_match( '#^/uap/v2/mcp/chat/launcher/\d+/?$#', $request->get_route() )
		) {
			return $dispatch_result;
		}

		$this->refresh_if_needed();

		return $dispatch_result;
	}

	/**
	 * Warm a missing observation before WordPress builds admin menus.
	 *
	 * @return void
	 */
	public function refresh_if_needed(): void {
		if (
			wp_doing_ajax()
			|| false !== get_transient( self::FAILURE_TRANSIENT )
			|| $this->has_usable_cache()
		) {
			return;
		}

		$this->refresh();
	}

	/**
	 * Get and cache one current allocation observation.
	 *
	 * @return bool Whether a valid observation was cached.
	 */
	public function refresh(): bool {
		if ( $this->attempted ) {
			return false !== get_transient( Mcp_Allocation_Facts_Reader::TRANSIENT );
		}

		$this->attempted = true;
		$license_key     = trim( $this->licenses->get_key() );

		if ( '' === $license_key ) {
			return false;
		}

		// add_option() gives all PHP workers one atomic lease. If another request
		// is already warming the cache, this request keeps the fail-closed snapshot
		// and lets that worker finish instead of adding another remote call.
		if ( ! $this->acquire_lock() ) {
			return false;
		}

		try {
			return $this->request_and_cache( $license_key );
		} catch ( \Throwable $error ) {
			unset( $error );
			$this->remember_failure();

			return false;
		} finally {
			delete_option( self::LOCK_OPTION );
		}
	}

	/**
	 * Request one observation while this worker owns the refresh lease.
	 *
	 * @param string $license_key Selected license credential.
	 *
	 * @return bool Whether a valid observation was cached.
	 */
	private function request_and_cache( string $license_key ): bool {

		$response = wp_remote_post(
			$this->endpoint,
			array(
				'timeout'     => 5,
				'redirection' => 0,
				'headers'     => array( 'Content-Type' => 'application/json' ),
				'body'        => wp_json_encode( array( 'license_key' => $license_key ) ),
				'data_format' => 'body',
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$this->remember_failure();
			return false;
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		try {
			Mcp_Allocation_Facts_Response::from_array( $decoded );
		} catch ( \UnexpectedValueException $error ) {
			unset( $error );
			$this->remember_failure();
			return false;
		}

		// The request can overlap a license activation, deactivation, or key
		// change. Do not publish a response for a credential that is no longer the
		// selected local identity. The next admin request can warm the new key.
		if ( ! hash_equals( $license_key, trim( $this->licenses->get_key() ) ) ) {
			return false;
		}

		$cached = array(
			'schema_version'   => Mcp_Allocation_Facts_Reader::SCHEMA_VERSION,
			'license_key_hash' => hash( 'sha256', $license_key ),
			'facts'            => $decoded,
		);
		$stored = set_transient(
			Mcp_Allocation_Facts_Reader::TRANSIENT,
			$cached,
			self::CACHE_DURATION
		);

		// WordPress can return false for either a failed write or an unchanged value.
		// Verify the stored snapshot so this method answers its cache contract exactly.
		if ( ! $stored && get_transient( Mcp_Allocation_Facts_Reader::TRANSIENT ) !== $cached ) {
			$this->remember_failure();
			return false;
		}

		delete_transient( self::FAILURE_TRANSIENT );

		return true;
	}

	/**
	 * Claim the short cross-request refresh lease.
	 *
	 * @return bool
	 */
	private function acquire_lock(): bool {
		$now     = time();
		$expires = get_option( self::LOCK_OPTION, null );

		if ( is_numeric( $expires ) && (int) $expires > $now ) {
			return false;
		}

		if ( null !== $expires ) {
			delete_option( self::LOCK_OPTION );
		}

		return add_option( self::LOCK_OPTION, $now + self::LOCK_DURATION, '', 'no' );
	}

	/**
	 * Keep a short failure marker so admin requests do not retry in a loop.
	 *
	 * @return void
	 */
	private function remember_failure(): void {
		try {
			set_transient( self::FAILURE_TRANSIENT, true, self::FAILURE_DURATION );
		} catch ( \Throwable $error ) {
			// This marker is only retry suppression. A cache/filter failure must not
			// turn optional feature-state warming into a WordPress request failure.
			unset( $error );
		}
	}

	/**
	 * Check that the local snapshot is readable for the selected key.
	 *
	 * A transient can survive a third-party write or a license-transition race.
	 * Validating it here lets the next admin request replace an unusable snapshot
	 * instead of mistaking mere transient existence for usable policy evidence.
	 *
	 * @return bool
	 */
	private function has_usable_cache(): bool {
		try {
			( new Mcp_Allocation_Facts_Reader( $this->licenses ) )->read();

			return true;
		} catch ( \Throwable $error ) {
			unset( $error );

			return false;
		}
	}
}
