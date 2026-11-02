<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

use Uncanny_Automator\App\Events\Dispatcher;

/**
 * Layers a short TCP/TLS-handshake timeout onto platform-bound HTTP
 * requests. WordPress's `timeout` argument on `wp_remote_*` covers the
 * full request/response cycle but not the connection phase — without
 * this injector, a DNS or handshake stall pins a PHP worker for the
 * full 90s response timeout (§9.4). Healthy connects complete in well
 * under two seconds; fail fast on connect, stay patient on response.
 *
 * Hooks the WordPress `http_api_curl` action — the canonical seam for
 * cURL-specific tuning on handles WP created. No cURL initialization
 * on our side. Scoped by URL host so third-party integration traffic
 * (Slack, HubSpot, etc.) inherits no extra behavior.
 *
 * Filters:
 *   - `automator_platform_is_platform_host`  (bool, string $host, string $url)
 *     — opt in/out additional hosts (dev environments, internal VIPs).
 *   - `automator_platform_connect_timeout`   (int seconds, string $url)
 *     — tune the timeout; values ≤ 0 are treated as "invalid" and fall
 *     back to the default (a zero would disable connect timeouts
 *     entirely, reintroducing the bug this class fixes).
 *
 * @since 8.0.0
 */
final class Connect_Timeout_Injector {

	/**
	 * Known platform hostnames — covers production + staging for the api
	 * tier. The plugin does not call the webhooks tier directly; that
	 * surface is for inbound third-party traffic. Extend via the
	 * `automator_platform_is_platform_host` filter.
	 *
	 * @var array<int, string>
	 */
	private const KNOWN_HOSTS = array(
		'api.automatorplugin.com',
		'platform.automatorplugin.com',
		'api.uncannycloud.com',
	);

	/**
	 * Default connect timeout in seconds. Chosen over 5s to avoid
	 * spurious failures when Cloudflare DNS or Railway edge has a
	 * momentary slow path; 7s is still a factor-of-12 improvement over
	 * waiting the full 90s response timeout on a dead connection.
	 */
	private const DEFAULT_CONNECT_TIMEOUT = 7;

	/**
	 * Register the `http_api_curl` listener. Called once at bootstrap.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action( 'http_api_curl', array( $this, 'maybe_apply' ), 10, 3 );
	}

	/**
	 * WP callback — set CURLOPT_CONNECTTIMEOUT on platform-bound
	 * requests only. Signature matches WP's `http_api_curl` action.
	 *
	 * The `$handle` is created and owned by WP's cURL transport; we
	 * only tune it. We never call curl_init/curl_exec ourselves.
	 *
	 * @param resource|\CurlHandle $handle      The cURL handle WP built.
	 * @param array<string, mixed> $parsed_args Request args (unused).
	 * @param string               $url         Outbound URL.
	 *
	 * @return void
	 */
	public function maybe_apply( $handle, $parsed_args, $url ): void {

		if ( ! is_string( $url ) ) {
			return;
		}

		if ( ! $this->should_apply_to_url( $url ) ) {
			return;
		}

		curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT, $this->connect_timeout_seconds( $url ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- tunes the handle WordPress passes to http_api_curl; the HTTP API has no connect-timeout argument (its timeout also caps the whole response)
	}

	/**
	 * Decide whether `$url` is platform-bound and therefore subject to
	 * the short connect timeout.
	 *
	 * @return bool
	 */
	public function should_apply_to_url( string $url ): bool {

		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}

		$host          = strtolower( $host );
		$is_known_host = in_array( $host, self::KNOWN_HOSTS, true ) || $this->matches_configured_api_url( $host );

		return (bool) Dispatcher::filter(
			'automator_platform_is_platform_host',
			$is_known_host,
			$host,
			$url
		);
	}

	/**
	 * Resolve the connect-timeout seconds for `$url`, respecting the
	 * `automator_platform_connect_timeout` filter. Non-positive filter
	 * returns fall back to the default — a zero would disable cURL's
	 * connect timeout entirely (unbounded wait), the exact behavior
	 * this class exists to prevent.
	 *
	 * @return int
	 */
	public function connect_timeout_seconds( string $url ): int {

		$seconds = (int) Dispatcher::filter(
			'automator_platform_connect_timeout',
			self::DEFAULT_CONNECT_TIMEOUT,
			$url
		);

		return $seconds > 0 ? $seconds : self::DEFAULT_CONNECT_TIMEOUT;
	}

	/**
	 * Check whether `$host` matches the host portion of the configured
	 * `AUTOMATOR_API_URL` constant. Lets operators override the base
	 * URL (self-hosted platform, on-prem staging) and still get the
	 * connect-timeout behavior without patching this class.
	 *
	 * @return bool
	 */
	private function matches_configured_api_url( string $host ): bool {

		if ( ! defined( 'AUTOMATOR_API_URL' ) ) {
			return false;
		}

		$configured = wp_parse_url( (string) AUTOMATOR_API_URL, PHP_URL_HOST );

		if ( ! is_string( $configured ) || '' === $configured ) {
			return false;
		}

		return strtolower( $configured ) === $host;
	}
}
