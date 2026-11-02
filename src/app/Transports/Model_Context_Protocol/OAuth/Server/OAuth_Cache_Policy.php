<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server;

/**
 * Page-cache opt-outs for OAuth routes.
 *
 * Cache-Control headers are honoured by browsers and most CDNs, but WordPress
 * page caches read their own signals: the DONOTCACHEPAGE constant and, for
 * LiteSpeed Cache (which caches REST responses for its own TTL by default),
 * the litespeed_control_* actions.
 */
final class OAuth_Cache_Policy {

	private const LITESPEED_REASON = 'automator-mcp-oauth';

	/**
	 * Mark the current response as never cacheable by a WordPress page cache.
	 */
	public static function no_cache(): void {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Cache plugins' shared constant.
			define( 'DONOTCACHEPAGE', true );
		}
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache's own hook.
		do_action( 'litespeed_control_set_nocache', self::LITESPEED_REASON );
	}

	/**
	 * Allow a public metadata document to be cached, but only for this long.
	 */
	public static function public_for( int $seconds ): void {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache's own hook.
		do_action( 'litespeed_control_set_ttl', max( 0, $seconds ) );
	}
}
