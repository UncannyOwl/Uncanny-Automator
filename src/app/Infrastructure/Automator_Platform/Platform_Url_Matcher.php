<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

/**
 * Decides which Automator platform surface an outbound URL targets — the ONE
 * implementation of "is this a platform URL" behind every
 * `http_request_args` injector and the `http_response` credentials observer.
 *
 * Four answers, widest first:
 *  - {@see is_platform()}       — anything under `AUTOMATOR_API_URL`: `/v2`,
 *    `/v3`, `/app/v1`, and the base itself. License headers and the client
 *    timeout declaration go here.
 *  - {@see is_site_signed()}    — the platform plane and the `/v3` action
 *    surface: the requests signed with this site's secret, and the answers
 *    that can be about this site's registration (platform answers contract
 *    3.1). Never `/v2`.
 *  - {@see is_action_surface()} — `/v3/*`, the vendor-call surface.
 *  - {@see is_platform_plane()} — `AUTOMATOR_API_URL` +
 *    `AUTOMATOR_PLATFORM_API_PATH` (`/app/v1/*`), the plugin↔platform
 *    plumbing.
 *
 * Every answer is a strict string prefix of the configured base (trailing
 * slashes normalised) followed by `/`, so a lookalike host
 * (`api.automatorplugin.com.evil.test`) or a longer path segment (`/v30`,
 * `/app/v10`) never matches.
 *
 * An unconfigured base — the constant undefined, empty, or only slashes —
 * matches nothing on any surface: without a platform there is no platform
 * URL, including a relative path that happens to look like a platform route.
 *
 * Built from the constants in production ({@see from_constants()}); the
 * constructor takes the values so an install without them can be modelled
 * (a defined constant cannot be undefined at runtime).
 *
 * @since 8.0.0
 */
final class Platform_Url_Matcher {

	/**
	 * `AUTOMATOR_API_URL` without trailing slashes; '' when unconfigured.
	 *
	 * @var string
	 */
	private string $base;

	/**
	 * `AUTOMATOR_PLATFORM_API_PATH` without surrounding slashes; null when
	 * the constant is undefined (no platform plane to match).
	 *
	 * @var string|null
	 */
	private ?string $plane_path;

	/**
	 * @param string|null $api_url    The platform base URL (`AUTOMATOR_API_URL`),
	 *                                or null when it is not defined.
	 * @param string|null $plane_path The platform-plane path prefix
	 *                                (`AUTOMATOR_PLATFORM_API_PATH`), or null
	 *                                when it is not defined.
	 */
	public function __construct( ?string $api_url, ?string $plane_path ) {

		$this->base       = null === $api_url ? '' : rtrim( $api_url, '/' );
		$this->plane_path = null === $plane_path ? null : trim( $plane_path, '/' );
	}

	/**
	 * The matcher for this install's configured platform.
	 *
	 * Both constants are defined by `src/globals.php` (or earlier, in
	 * wp-config.php) before Application_Bootstrap constructs any consumer, and
	 * a constant never changes once defined — so reading them here is the same
	 * as reading them on every request.
	 *
	 * @return self
	 */
	public static function from_constants(): self {

		return new self(
			defined( 'AUTOMATOR_API_URL' ) ? (string) AUTOMATOR_API_URL : null,
			defined( 'AUTOMATOR_PLATFORM_API_PATH' ) ? (string) AUTOMATOR_PLATFORM_API_PATH : null
		);
	}

	/**
	 * Whether the URL targets the Automator platform at all: the base itself
	 * or any path under it (`/v2`, `/v3`, `/app/v1`).
	 *
	 * @param string $url Outbound request URL.
	 *
	 * @return bool
	 */
	public function is_platform( string $url ): bool {

		if ( '' === $this->base ) {
			return false;
		}

		return $url === $this->base || 0 === strpos( $url, $this->base . '/' );
	}

	/**
	 * Whether a request to the URL is signed with this site's secret: the
	 * platform plane or a `/v3` action route. Never `/v2` (the legacy contract,
	 * no site identity), never another host. The one implementation of that
	 * rule: the signer, its guard and replay resend
	 * ({@see Platform_Request_Signature_Injector}), the credentials observer,
	 * the clock-skew monitor and the client's `Retry-After` wait all read it.
	 *
	 * @since 8.0.0
	 *
	 * @param string $url Outbound request URL.
	 *
	 * @return bool
	 */
	public function is_site_signed( string $url ): bool {

		return $this->is_platform_plane( $url ) || $this->is_action_surface( $url );
	}

	/**
	 * Whether the URL is a `/v3` action route on the platform.
	 *
	 * @param string $url Outbound request URL.
	 *
	 * @return bool
	 */
	public function is_action_surface( string $url ): bool {

		return $this->is_under( $url, 'v3' );
	}

	/**
	 * Whether the URL is on the platform plane (`{base}/app/v1/…`). Never `/v2`
	 * (form-encoded legacy contract, no site identity), never `/v3`, never
	 * another host.
	 *
	 * @param string $url Outbound request URL.
	 *
	 * @return bool
	 */
	public function is_platform_plane( string $url ): bool {

		return null !== $this->plane_path && $this->is_under( $url, $this->plane_path );
	}

	/**
	 * Whether the URL starts with `{base}/{path}/`.
	 *
	 * @param string $url  Outbound request URL.
	 * @param string $path Path prefix without surrounding slashes.
	 *
	 * @return bool
	 */
	private function is_under( string $url, string $path ): bool {

		return '' !== $this->base && 0 === strpos( $url, $this->base . '/' . $path . '/' );
	}
}
