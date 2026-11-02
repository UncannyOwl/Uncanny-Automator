<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Http;

use WpOrg\Requests\Exception as Requests_Exception;
use WpOrg\Requests\HookManager;

/**
 * The one check every redirect hop gets before it is followed: a redirect whose
 * target is, or resolves to, an address in a range that is not public
 * ({@see Non_Public_Ranges}) is refused, so a public URL cannot hand a request
 * on to a private address. A redirect to a public address is followed as before.
 *
 * refuses() is the check itself, for code that follows its redirects one
 * request at a time (automator_remote_get_ssrf_safe()). around() applies it to
 * the redirects WordPress's HTTP API follows for a request: wp_safe_remote_*(),
 * download_url() and wp_oembed_get() included.
 * Each hop is checked on `requests.before_redirect`, the hook where WordPress
 * itself checks a hop with wp_http_validate_url() for a request that rejects
 * unsafe URLs, after Requests has made the target absolute and just before the
 * hop is sent. WordPress's own check skips a hop to the site's own host, and a
 * site can let it through with the `http_request_host_is_external` filter; this
 * check does neither.
 *
 * DNS: like WordPress's check, a hop's host is resolved once, with
 * gethostbyname(), immediately before the hop is sent, and the transport
 * resolves it again to connect. The address is not pinned between the two
 * lookups; WordPress's HTTP API has no way to pin it on every transport.
 *
 * @since 8.0.0
 */
final class Redirect_Hop_Check {

	/**
	 * Whether a redirect to this URL must not be followed.
	 *
	 * @param string $target The redirect's target, absolute.
	 *
	 * @return bool True when the target is, or resolves to, an address that is
	 *              not public, or does not resolve.
	 */
	public static function refuses( string $target ): bool {
		return Non_Public_Ranges::url_resolves_into( $target );
	}

	/**
	 * Send requests through WordPress's HTTP API with every redirect they follow checked.
	 *
	 * The check is in place only while $send runs, for every request it sends.
	 *
	 * @param callable $send Sends the requests and returns what the caller needs.
	 *
	 * @return mixed What $send returns. A refused hop fails its request the way
	 *               WordPress fails a hop its own check refuses: wp_remote_*()
	 *               returns a WP_Error with the code `http_request_failed` and the
	 *               refusal as its message, and nothing is sent to the refused target.
	 */
	public static function around( callable $send ) {

		$check = static function ( $location, $headers, $data, $options ): void {
			self::check_hop( (string) $location, $options['hooks'] );
		};

		// Last, so a target another callback rewrote is the one checked.
		add_action( 'requests-requests.before_redirect', $check, PHP_INT_MAX, 4 );

		try {
			return $send();
		} finally {
			remove_action( 'requests-requests.before_redirect', $check, PHP_INT_MAX );
		}
	}

	/**
	 * Refuse a hop whose target is not public, before it is sent.
	 *
	 * An exception must not leave a WordPress action: WordPress would keep the
	 * action marked as running. So the hop is refused by a Requests hook, the
	 * one Requests runs next, before it sends the hop; WordPress's own check
	 * refuses a hop by throwing from a Requests hook too.
	 *
	 * @param string      $location The hop's target, made absolute by Requests.
	 * @param HookManager $hooks    The hooks of the request being redirected.
	 *
	 * @return void
	 */
	private static function check_hop( string $location, HookManager $hooks ): void {

		if ( ! self::refuses( $location ) ) {
			return;
		}

		$hooks->register(
			'requests.before_request',
			static function (): void {
				throw new Requests_Exception(
					esc_html__( 'The URL redirects to a private or reserved IP address, so the redirect was not followed.', 'uncanny-automator' ),
					'automator_redirect_not_public'
				);
			}
		);
	}
}
