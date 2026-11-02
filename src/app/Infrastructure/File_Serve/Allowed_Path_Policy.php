<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\File_Serve;

/**
 * Defense-in-depth allow-list for the `automator_serve` endpoint.
 *
 * Three accepted shapes:
 *
 * 1. Media-library file path under `wp_get_upload_dir()['basedir']` —
 *    realpath-prefix-checked to defeat `../` traversal.
 * 2. Media-library URL under `wp_get_upload_dir()['baseurl']`.
 * 3. External `https://` URL whose host clears an SSRF-adjacent denylist
 *    (no internal hostname suffixes, no private/loopback/link-local IPs).
 *
 * The platform's R2 owns the canonical SSRF policy server-side; this is
 * defense-in-depth on the plugin side. If our check rejects, the
 * platform never gets the URL — that's the design.
 *
 * @since 8.0.0
 */
final class Allowed_Path_Policy {

	/**
	 * Hostname suffixes that resolve to internal infrastructure on the
	 * platform's hosting fabric. Pattern matched against the parsed
	 * URL host. Cheap pre-filter ahead of DNS resolution.
	 *
	 * @var string[]
	 */
	private const DENY_HOST_SUFFIXES = array(
		'.local',
		'.internal',
		'.railway.internal',
		'.consul',
		'.svc.cluster.local',
	);

	/**
	 * Exact-match hostnames that always resolve loopback.
	 *
	 * @var string[]
	 */
	private const DENY_HOSTS = array(
		'localhost',
		'localhost.localdomain',
		'ip6-localhost',
	);

	/**
	 * Decide whether the candidate is allowed for serving.
	 *
	 * @param string $candidate Raw path or URL the recipe author / async job supplied.
	 *
	 * @return bool
	 */
	public function is_allowed( string $candidate ): bool {

		if ( '' === $candidate ) {
			return false;
		}

		// Shape 3 — external URL.
		if ( false !== strpos( $candidate, '://' ) ) {
			return $this->is_allowed_external_url( $candidate );
		}

		$uploads = wp_get_upload_dir();
		$basedir = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : '';

		// Shape 1 — media-library file path (no scheme).
		return '' !== $basedir && $this->is_within_basedir( $candidate, $basedir );
	}

	/**
	 * Allow paths that resolve under the uploads basedir AND don't
	 * climb out via `..` traversal.
	 *
	 * Public so the serve controller can re-assert containment on the EXACT
	 * disk path it is about to open — the gate validated `is_allowed()` on
	 * the request `p` in a different derivation, and a consumer must never
	 * trust a second, independent re-derivation of a path (see
	 * File_Serve_Controller::resolve_local_path()).
	 *
	 * @param string $candidate Path supplied by the caller.
	 * @param string $basedir   Absolute uploads basedir.
	 *
	 * @return bool
	 */
	public function is_within_basedir( string $candidate, string $basedir ): bool {

		$basedir_real = realpath( $basedir );
		if ( false === $basedir_real ) {
			return false;
		}

		// realpath() returns false for non-existent files. Reject those —
		// the controller separately needs the file to exist anyway.
		$candidate_real = realpath( $candidate );
		if ( false === $candidate_real ) {
			return false;
		}

		return 0 === strpos( $candidate_real, rtrim( $basedir_real, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR )
			|| $candidate_real === $basedir_real;
	}

	/**
	 * Vet a URL candidate.
	 *
	 * Order: cheap shape checks first (scheme, host parse, denylist),
	 * THEN the media-library URL check (still cheap — string prefix),
	 * THEN DNS resolution (only on otherwise-acceptable hosts).
	 *
	 * @param string $candidate URL.
	 *
	 * @return bool
	 */
	private function is_allowed_external_url( string $candidate ): bool {

		// Shape 2 — media-library URL. Two guards, both mirroring the
		// filesystem shape (Shape 1):
		//   1. Anchor the prefix match on `baseurl + "/"`. With WP's default
		//      path-bearing baseurl the path already pins the host, but
		//      `upload_url_path` (CDN/offload plugins) can make baseurl
		//      host-only — a bare prefix match would then treat
		//      `https://cdn.example.com.evil.com/...` as a media URL. The
		//      trailing-slash boundary sends it to the external-URL vetting
		//      instead, exactly as is_within_basedir() anchors the disk case.
		//   2. Map the URL to its on-disk path and realpath-check under
		//      basedir, so a `..` segment (`{baseurl}/../../etc/passwd`)
		//      can't escape the uploads dir.
		// (http baseurls are fine — some WP configs are unwrapped behind a
		// load balancer; the bytes still come off disk.)
		$uploads     = wp_get_upload_dir();
		$baseurl     = isset( $uploads['baseurl'] ) ? (string) $uploads['baseurl'] : '';
		$basedir     = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : '';
		$base_prefix = rtrim( $baseurl, '/' );
		if ( '' !== $baseurl && 0 === strpos( $candidate, $base_prefix . '/' ) ) {
			$relative = substr( $candidate, strlen( $base_prefix ) );
			return '' !== $basedir
				&& $this->is_within_basedir( rtrim( $basedir, DIRECTORY_SEPARATOR ) . $relative, $basedir );
		}

		$parts = wp_parse_url( $candidate );
		if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) ) {
			return false;
		}

		if ( 'https' !== strtolower( (string) $parts['scheme'] ) ) {
			return false;
		}

		$host = strtolower( (string) $parts['host'] );

		if ( in_array( $host, self::DENY_HOSTS, true ) ) {
			return false;
		}

		foreach ( self::DENY_HOST_SUFFIXES as $suffix ) {
			$len = strlen( $suffix );
			if ( strlen( $host ) >= $len && substr( $host, -$len ) === $suffix ) {
				return false;
			}
		}

		// Reject IP-literal hosts that fall in private/loopback/link-local
		// ranges. Hostname → IP via gethostbyname (1s budget on most
		// systems; resolution failure → reject as defensive default).
		$ip = $this->resolve_host( $host );
		if ( '' === $ip ) {
			return false;
		}

		return ! $this->is_private_ip( $ip );
	}

	/**
	 * Resolve a host to a single IPv4/IPv6 address.
	 *
	 * IP-literal inputs are returned as-is so we can run them through the
	 * private-range check without paying for a lookup.
	 *
	 * @param string $host Hostname or IP literal.
	 *
	 * @return string Resolved IP, or empty string on failure.
	 */
	private function resolve_host( string $host ): string {

		$bracketless = trim( $host, '[]' );
		if ( false !== filter_var( $bracketless, FILTER_VALIDATE_IP ) ) {
			return $bracketless;
		}

		$resolved = gethostbyname( $host );
		if ( $resolved === $host ) {
			// gethostbyname returns the input on failure.
			return '';
		}

		return $resolved;
	}

	/**
	 * Return true if the IP falls in a private, loopback, or link-local range.
	 *
	 * Ranges checked:
	 *  IPv4: 127.0.0.0/8, 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16,
	 *        169.254.0.0/16, 0.0.0.0/8.
	 *  IPv6: ::1, fc00::/7, fe80::/10, ff00::/8, ::ffff:0:0/96.
	 *
	 * @param string $ip Validated IP literal.
	 *
	 * @return bool
	 */
	private function is_private_ip( string $ip ): bool {

		// PHP's FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		// covers 10/8, 172.16/12, 192.168/16, 169.254/16, 127/8, 0/8,
		// ::1, fc00::/7, fe80::/10, and the multicast/reserved ranges.
		// `filter_var` returns the IP on success; false on the denied
		// ranges. Inverting that gives us the predicate we want.
		$ok = filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		);

		return false === $ok;
	}
}
