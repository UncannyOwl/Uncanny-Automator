<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

use Uncanny_Automator\App\Infrastructure\Http\Request_Headers;
use Uncanny_Automator\App\Infrastructure\License\License_Provider_Interface;

use function Uncanny_Automator\App\Infrastructure\automator_license_manager;

/**
 * Auto-injects the three license-identifying headers
 * (`license-key` / `site-name` / `item-name`) onto every outbound
 * HTTP request whose URL begins with `AUTOMATOR_API_URL`.
 *
 * Centralizes what was previously copy-pasted into every direct
 * platform call (heartbeat, capability discovery, async-job poll, etc.)
 * and what was MISSING from new direct callers like
 * `Async_Job_Recovery::poll_job_status()` — which surfaced as
 * `401 UNAUTHENTICATED Missing license headers`.
 *
 * Behavior:
 *  - URL prefix match against `AUTOMATOR_API_URL` (trailing slash
 *    normalised) via {@see Platform_Url_Matcher::is_platform()}.
 *    Non-platform URLs untouched.
 *  - Existing headers preserved — caller-provided values win (any
 *    header-name case), defense in depth for one-offs that need different
 *    credentials. A raw header string is read the way WP_Http will parse
 *    it ({@see Request_Headers::from_args()}), so the caller's headers
 *    survive.
 *  - No license key (no active license) adds nothing — we don't
 *    synthesize empty headers. A request sent through the platform
 *    client or `Api_Server::api_call()` never gets here without one:
 *    both refuse it first
 *    ({@see Dispatch\Platform_Dispatch_Guard::refuse_without_a_license_key()}).
 *    Any other request (a site-signed `/app/v1` call, a raw
 *    `wp_remote_*` call) goes out without the headers, and production's
 *    edge refuses a POST that lacks them (403) before the platform's
 *    own 401 "Missing license headers".
 *  - Hook priority is late (priority 9999) so it runs AFTER any
 *    third-party header customization.
 *
 * @since 8.0.0
 */
final class Platform_Headers_Injector {

	/**
	 * Optional license provider. When null, `maybe_inject()` resolves the
	 * default via `automator_license_manager()` at filter-fire time —
	 * deferred resolution avoids early-bootstrap chicken-and-egg.
	 *
	 * @var License_Provider_Interface|null
	 */
	private ?License_Provider_Interface $license;

	/**
	 * Which URLs are the platform.
	 *
	 * @var Platform_Url_Matcher
	 */
	private Platform_Url_Matcher $urls;

	/**
	 * @param License_Provider_Interface|null $license Optional injection
	 *        for unit tests; production passes null and the filter
	 *        resolves the default service.
	 * @param Platform_Url_Matcher|null       $urls    Optional injection
	 *        for unit tests; production passes null and gets the
	 *        configured platform.
	 */
	public function __construct( ?License_Provider_Interface $license = null, ?Platform_Url_Matcher $urls = null ) {

		$this->license = $license;
		$this->urls    = $urls ?? Platform_Url_Matcher::from_constants();
	}

	/**
	 * Register the `http_request_args` filter at priority 9999.
	 *
	 * Late priority means any third-party filter that customises headers
	 * earlier (CDN, security plugins, etc.) runs first; the injector
	 * fills only headers still missing afterward.
	 *
	 * Idempotent at the WordPress hook layer — registering twice yields
	 * one effective callback because `add_filter` dedups by callback
	 * identity for the same priority. Bootstrap calls this once.
	 *
	 * @return void
	 */
	public function register_hooks(): void {

		add_filter( 'http_request_args', array( $this, 'maybe_inject' ), 9999, 2 );
	}

	/**
	 * Filter callback. Inspects the outbound URL; when it points at the
	 * Automator platform (`AUTOMATOR_API_URL` prefix), enriches the
	 * `headers` array with the three license-identifying keys
	 * (`license-key`, `site-name`, `item-name`) sourced from the active
	 * license provider.
	 *
	 * Caller-provided header values are preserved (case-insensitive
	 * existence check) — the injector only fills blanks. Non-platform
	 * URLs and unlicensed sites pass through untouched.
	 *
	 * Defensive about input shape: any non-array `$args` or non-string
	 * `$url` is returned as-is so foreign callers don't trigger
	 * type-juggling errors.
	 *
	 * @param array<string, mixed>|mixed $args WP HTTP API request arguments.
	 * @param string|mixed               $url  Outbound request URL.
	 *
	 * @return array<string, mixed>|mixed     Modified args or input verbatim.
	 */
	public function maybe_inject( $args, $url ) {

		if ( ! is_array( $args ) || ! is_string( $url ) || '' === $url ) {
			return $args;
		}

		if ( ! $this->urls->is_platform( $url ) ) {
			return $args;
		}

		$license = $this->license ?? automator_license_manager();
		if ( null === $license ) {
			return $args;
		}

		$key       = (string) $license->get_key();
		$site_name = (string) $license->get_site_name();
		$item_name = (string) $license->get_item_name();

		if ( '' === $key ) {
			return $args; // unlicensed — an app call was refused before it got here; anything else goes out as it is
		}

		$existing = Request_Headers::from_args( $args );

		// Caller-supplied values win — only fill blanks.
		if ( ! Request_Headers::has( $existing, 'license-key' ) ) {
			$existing['license-key'] = $key;
		}
		if ( '' !== $site_name && ! Request_Headers::has( $existing, 'site-name' ) ) {
			$existing['site-name'] = $site_name;
		}
		if ( '' !== $item_name && ! Request_Headers::has( $existing, 'item-name' ) ) {
			$existing['item-name'] = $item_name;
		}
		if ( '' !== $item_name && ! Request_Headers::has( $existing, 'item-version' ) ) {
			$existing['item-version'] = AUTOMATOR_PLUGIN_VERSION;
		}

		$args['headers'] = $existing;
		return $args;
	}
}
