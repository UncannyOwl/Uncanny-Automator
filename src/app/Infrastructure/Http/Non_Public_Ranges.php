<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Http;

/**
 * Every IPv4 and IPv6 range that is not publicly routable, and whether an
 * address or a URL's host falls in one.
 *
 * The one list for each place that sends a request to an address a recipe
 * supplies: the "Send data to a webhook" action and the remote-file client
 * ({@see \Uncanny_Automator\App\Infrastructure\Remote_File\Remote_File_Client}).
 * It is written out range by range because PHP's FILTER_FLAG_GLOBAL_RANGE
 * exists only from PHP 8.2, and FILTER_FLAG_NO_PRIV_RANGE with
 * FILTER_FLAG_NO_RES_RANGE lets carrier-grade NAT, multicast, benchmarking
 * and the documentation ranges through.
 *
 * The IPv4 ranges are the IANA IPv4 Special-Purpose Address Registry's
 * blocks that are not globally reachable, with multicast and the reserved
 * 240.0.0.0/4. In IPv6 only global unicast (2000::/3) can be public, so
 * every other block of the IANA IPv6 Address Space is listed, followed by
 * the special-purpose blocks inside 2000::/3 that are not globally
 * reachable.
 *
 * @since 8.0.0
 */
final class Non_Public_Ranges {

	/**
	 * IPv4 ranges that are not public, in CIDR notation.
	 *
	 * @var string[]
	 */
	private const IPV4 = array(
		'0.0.0.0/8',       // "This network", the unspecified address 0.0.0.0 included (RFC 791).
		'10.0.0.0/8',      // Private use (RFC 1918).
		'100.64.0.0/10',   // Shared address space for carrier-grade NAT; holds Alibaba Cloud's metadata address 100.100.100.200 (RFC 6598).
		'127.0.0.0/8',     // Loopback (RFC 1122).
		'169.254.0.0/16',  // Link-local; holds the cloud metadata address 169.254.169.254 (RFC 3927).
		'172.16.0.0/12',   // Private use (RFC 1918).
		'192.0.0.0/24',    // IETF protocol assignments (RFC 6890).
		'192.0.2.0/24',    // Documentation, TEST-NET-1 (RFC 5737).
		'192.88.99.0/24',  // 6to4 relay anycast, deprecated (RFC 7526).
		'192.168.0.0/16',  // Private use (RFC 1918).
		'198.18.0.0/15',   // Benchmarking (RFC 2544).
		'198.51.100.0/24', // Documentation, TEST-NET-2 (RFC 5737).
		'203.0.113.0/24',  // Documentation, TEST-NET-3 (RFC 5737).
		'224.0.0.0/4',     // Multicast (RFC 5771).
		'240.0.0.0/4',     // Reserved, the limited broadcast address 255.255.255.255 included (RFC 1112, RFC 919).
	);

	/**
	 * IPv6 ranges that are not public, in CIDR notation.
	 *
	 * @var string[]
	 */
	private const IPV6 = array(
		// Reserved by IETF. Holds the unspecified address ::, the loopback ::1, IPv4-compatible
		// (::/96) and IPv4-mapped (::ffff:0:0/96) addresses, and both NAT64 prefixes
		// (64:ff9b::/96, 64:ff9b:1::/48), which reach whatever IPv4 address they carry
		// (RFC 4291, RFC 6052, RFC 8215).
		'::/8',
		'100::/8',        // Reserved by IETF, the discard-only prefix 100::/64 included (RFC 6666).
		'200::/7',        // Reserved by IETF.
		'400::/6',        // Reserved by IETF.
		'800::/5',        // Reserved by IETF.
		'1000::/4',       // Reserved by IETF.
		'2001::/23',      // IETF protocol assignments: Teredo, benchmarking, ORCHID (RFC 2928).
		'2001:db8::/32',  // Documentation (RFC 3849).
		'2002::/16',      // 6to4, which reaches the IPv4 address it carries (RFC 3056).
		'3fff::/20',      // Documentation (RFC 9637).
		'4000::/3',       // Reserved by IETF, the segment-routing prefix 5f00::/16 included (RFC 9602).
		'6000::/3',       // Reserved by IETF.
		'8000::/3',       // Reserved by IETF.
		'a000::/3',       // Reserved by IETF.
		'c000::/3',       // Reserved by IETF.
		'e000::/4',       // Reserved by IETF.
		'f000::/5',       // Reserved by IETF.
		'f800::/6',       // Reserved by IETF.
		'fc00::/7',       // Unique local, private use (RFC 4193).
		'fe00::/9',       // Reserved by IETF.
		'fe80::/10',      // Link-local (RFC 4291).
		'fec0::/10',      // Site-local, deprecated (RFC 3879).
		'ff00::/8',       // Multicast (RFC 4291).
	);

	/**
	 * Whether a URL's host is, or resolves to, an address that is not public.
	 *
	 * A host name is resolved with gethostbyname(), as WordPress resolves it
	 * when it checks a URL. A URL with no host, and a host that does not
	 * resolve, count as not public: nothing can be requested from them.
	 *
	 * @param string $url The URL a request is about to go to.
	 *
	 * @return bool True when the request must not be sent.
	 */
	public static function url_resolves_into( string $url ): bool {

		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( ! is_string( $host ) || '' === $host ) {
			return true;
		}

		// An IPv6 literal comes bracketed: [::1].
		$host = trim( $host, '[]' );

		if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return self::includes( $host );
		}

		// gethostbyname() returns the host unchanged when the lookup fails, which is no address.
		return self::includes( gethostbyname( $host ) );
	}

	/**
	 * Whether an IP address falls in a range that is not public.
	 *
	 * @param string $ip An IPv4 or IPv6 address.
	 *
	 * @return bool True when it does, or when the string is not an IP address.
	 */
	public static function includes( string $ip ): bool {

		if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return true;
		}

		$address = inet_pton( $ip );

		if ( false === $address ) {
			return true;
		}

		$ranges = 4 === strlen( $address ) ? self::IPV4 : self::IPV6;

		foreach ( $ranges as $range ) {
			if ( self::in_range( $address, $range ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a packed address falls in a CIDR range of the same family.
	 *
	 * @param string $address The address as inet_pton() packs it.
	 * @param string $range   The range in CIDR notation.
	 *
	 * @return bool
	 */
	private static function in_range( string $address, string $range ): bool {

		list( $network, $prefix_length ) = explode( '/', $range );

		$network       = (string) inet_pton( $network );
		$prefix_length = (int) $prefix_length;
		$whole_bytes   = intdiv( $prefix_length, 8 );

		if ( substr( $address, 0, $whole_bytes ) !== substr( $network, 0, $whole_bytes ) ) {
			return false;
		}

		$remaining_bits = $prefix_length % 8;

		if ( 0 === $remaining_bits ) {
			return true;
		}

		$mask = ( 0xFF << ( 8 - $remaining_bits ) ) & 0xFF;

		return ( ord( $address[ $whole_bytes ] ) & $mask ) === ( ord( $network[ $whole_bytes ] ) & $mask );
	}
}
