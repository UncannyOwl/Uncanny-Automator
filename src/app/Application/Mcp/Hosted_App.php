<?php
/**
 * The hosted Automator MCP app this site trusts.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Application\Mcp;

/**
 * A site trusts exactly one hosted app: production, or the address set with
 * UNCANNY_AGENT_APP_URL in wp-config.php for local development. The OAuth
 * callback allowlist and the MCP Origin policy both use this address.
 */
final class Hosted_App {

	/** Production base URL. */
	public const PRODUCTION_URL = 'https://mcp.automatorplugin.com';

	/**
	 * The hosted app's base URL, without a trailing slash.
	 *
	 * @return string
	 */
	public static function base_url(): string {
		if ( defined( 'UNCANNY_AGENT_APP_URL' ) && ! empty( UNCANNY_AGENT_APP_URL ) ) {
			return rtrim( (string) UNCANNY_AGENT_APP_URL, '/' );
		}

		return self::PRODUCTION_URL;
	}

	/**
	 * The hosted app, opened from this site. Every link to it carries:
	 *
	 * - `site`: the home URL, so sign-in returns the owner to this site. The
	 *   hosted app derives a connected site's address from its REST URL, which
	 *   WordPress builds on the home URL, so the two compare equal.
	 * - `admin`: the wp-admin URL, which is not always the home URL plus
	 *   `/wp-admin/`, for example when WordPress lives in a subfolder.
	 * - `is_ms`: 1 on a multisite network.
	 * - `is_sb`: 1 on a subsite, any site of a network but its main one.
	 * - `is_nadmin_site`: 1 on the network's main site, which hosts Network Admin.
	 * - `automator_ver` and `wp_ver`: the Automator and WordPress versions.
	 * - `automator_ref`: optional, the place in the plugin that opened the link.
	 * - `metadata`: optional, extra values as URL-encoded JSON. Links reach
	 *   server logs, so keep personal data out of it.
	 *
	 * Anyone can write these parameters, so the hosted app acts on them only
	 * after matching `site` to a site of the signed-in account.
	 *
	 * @since 8.0.0
	 *
	 * @param string              $ref      The place in the plugin that opens the link, if it says.
	 * @param array<string,mixed> $metadata Extra values for the hosted app, if any.
	 *
	 * @return string
	 */
	public static function connect_url( string $ref = '', array $metadata = array() ): string {
		// add_query_arg() leaves values as given, so each one is encoded here.
		$args = array(
			'site'           => rawurlencode( home_url() ),
			'admin'          => rawurlencode( admin_url() ),
			'is_ms'          => is_multisite() ? '1' : '0',
			'is_sb'          => is_multisite() && ! is_main_site() ? '1' : '0',
			'is_nadmin_site' => is_multisite() && is_main_site() ? '1' : '0',
			'automator_ver'  => rawurlencode( AUTOMATOR_PLUGIN_VERSION ),
			'wp_ver'         => rawurlencode( (string) get_bloginfo( 'version' ) ),
		);

		if ( '' !== $ref ) {
			$args['automator_ref'] = rawurlencode( $ref );
		}

		$json = array() === $metadata ? false : wp_json_encode( $metadata );

		if ( false !== $json ) {
			$args['metadata'] = rawurlencode( $json );
		}

		return add_query_arg( $args, self::base_url() . '/' );
	}
}
