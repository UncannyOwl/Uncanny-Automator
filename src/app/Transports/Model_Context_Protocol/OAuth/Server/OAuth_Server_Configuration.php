<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\Mcp_Resource_Identifier;

/**
 * OAuth enablement and canonical endpoint policy.
 */
final class OAuth_Server_Configuration {

	/**
	 * Whether the browser OAuth doorway ships in this build.
	 *
	 * Discovery, authorization, registration and token exchange all remain on
	 * the customer WordPress site. Readiness is a separate advisory service.
	 */
	public static function is_browser_flow_available(): bool {
		// Build-level kill switch, not a stub: every browser-flow surface
		// (routes, challenges, settings UI) keys off this one gate, so a
		// release can go dark again by flipping this single line.
		return true;
	}

	private const OPTION_NAME = 'automator_mcp_oauth_server_v1';

	private Mcp_Resource_Identifier $resource_identifier;

	private Site_Address_Settings $address_settings;

	/**
	 * Constructor.
	 */
	public function __construct( ?Mcp_Resource_Identifier $resource_identifier = null, ?Site_Address_Settings $address_settings = null ) {
		$this->resource_identifier = $resource_identifier ?? new Mcp_Resource_Identifier();
		$this->address_settings    = $address_settings ?? new Site_Address_Settings();
	}

	/**
	 * Whether the OAuth doorway is enabled.
	 *
	 * Enabled by default so OAuth-capable clients work alongside manual
	 * bearer tokens; an administrator can still switch it off. New
	 * authorizations remain gated on HTTPS.
	 */
	public function is_enabled(): bool {
		// OAuth is an automatic sign-in capability, not a separate product toggle.
		// Administrators disconnect individual apps on the Connection screen; the
		// authenticated settings route remains an operational kill switch without
		// adding a control that can unexpectedly break every existing connection.
		$settings = $this->get_settings();

		if ( ! array_key_exists( 'enabled', $settings ) ) {
			return true;
		}

		return ! empty( $settings['enabled'] );
	}

	/**
	 * Whether new authorization may begin.
	 */
	public function can_authorize(): bool {
		// A client can reach this site's authorization endpoint only after it has
		// already completed public discovery. The WordPress-side probe is useful
		// troubleshooting evidence, but cron delays or blocked outbound HTTP must
		// not veto a connection that demonstrably completed discovery.
		return $this->is_enabled() && $this->is_https();
	}

	/**
	 * Why new authorization is unavailable, in plain words for an error response.
	 *
	 * @return string Empty when authorization is available.
	 */
	public function get_unavailable_description(): string {
		if ( ! $this->is_enabled() ) {
			$settings_url = $this->resource_identifier->get_rest_url( 'automator/v1/oauth/settings' );
			if ( ! $this->is_customer_https_url( $settings_url ) ) {
				return 'OAuth authorization is disabled on this site. Ask your web host to configure a valid HTTPS site address before using administrator credentials. Then ask your WordPress administrator or host to re-enable app connections through the WordPress REST API: POST to the /automator/v1/oauth/settings route with {"enabled":true}.';
			}
			return sprintf(
				'OAuth authorization is disabled on this site. Ask your WordPress administrator or web host to re-enable app connections using the WordPress REST API: send POST to %s with {"enabled":true} using an administrator account.',
				$settings_url
			);
		}
		if ( ! $this->is_https() ) {
			return 'OAuth authorization requires HTTPS. ' . $this->get_https_instruction();
		}

		return '';
	}

	/**
	 * How to move this site's address to HTTPS, wherever it can be changed.
	 */
	private function get_https_instruction(): string {
		switch ( $this->address_settings->location() ) {
			case Site_Address_Settings::CONFIGURATION:
				return 'Ask your host or developer to update the site address in its configuration and confirm that HTTPS is working.';
			case Site_Address_Settings::NETWORK_EDIT_SITE:
				return 'A network administrator can change Site Address (URL) to start with https:// in Network Admin › Sites › Edit site.';
			case Site_Address_Settings::HOST:
				return "This is the network's main site, so its address can't be changed in the dashboard. Ask the host to switch the network to https://.";
			default:
				return 'Update the WordPress Address and Site Address to https:// under Settings > General.';
		}
	}

	/**
	 * Whether already-issued OAuth MCP access/refresh credentials remain usable.
	 */
	public function can_use_existing_credentials(): bool {
		// Existing credentials authenticate directly against this WordPress site.
		// A later readiness failure is troubleshooting evidence, not a reason to
		// revoke credentials that the site can still validate itself.
		return $this->is_enabled() && $this->has_secure_customer_endpoints();
	}

	/**
	 * Enable or disable the OAuth doorway.
	 */
	public function set_enabled( bool $enabled ): bool {
		$settings            = $this->get_settings();
		$settings['enabled'] = $enabled;
		$settings['updated'] = time();

		return update_option( self::OPTION_NAME, $settings, true ); // Autoloaded: is_enabled() runs on init on every page load.
	}

	/**
	 * Canonical MCP protected resource identifier.
	 */
	public function get_resource(): string {
		return $this->resource_identifier->get();
	}

	/**
	 * Canonical authorization-server issuer.
	 */
	public function get_issuer(): string {
		// The WordPress site owns OAuth. Keeping the issuer beside its authorize,
		// token and registration endpoints gives clients one consistent authority.
		return untrailingslashit( $this->resource_identifier->get_rest_url( 'automator/v1/oauth' ) );
	}

	/**
	 * OAuth authorization endpoint.
	 */
	public function get_authorization_endpoint(): string {
		return $this->resource_identifier->get_rest_url( 'automator/v1/oauth/authorize' );
	}

	/**
	 * OAuth token endpoint.
	 */
	public function get_token_endpoint(): string {
		return $this->resource_identifier->get_rest_url( 'automator/v1/oauth/token' );
	}

	/** OAuth cancellation for one grant, not the entire client. */
	public function get_revocation_endpoint(): string {
		return $this->resource_identifier->get_rest_url( 'automator/v1/oauth/revoke' );
	}

	/**
	 * RFC 7591 dynamic client registration endpoint.
	 */
	public function get_registration_endpoint(): string {
		return $this->resource_identifier->get_rest_url( 'automator/v1/oauth/register' );
	}

	/** Public readiness check for the fixed hosted connection contract. */
	public function get_connection_check_url(): string {
		return $this->resource_identifier->get_rest_url( 'automator/v1/oauth/connection' );
	}

	/**
	 * Whether every public OAuth URL uses HTTPS.
	 */
	public function is_https(): bool {
		foreach (
			array(
				$this->get_resource(),
				$this->get_issuer(),
				$this->get_connection_check_url(),
				$this->get_authorization_endpoint(),
				$this->get_token_endpoint(),
				$this->get_registration_endpoint(),
			) as $url
		) {
			if ( ! $this->is_oauth_https_url( $url ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether the customer-owned OAuth endpoints use HTTPS.
	 */
	private function has_secure_customer_endpoints(): bool {
		foreach ( array( $this->get_resource(), $this->get_authorization_endpoint(), $this->get_token_endpoint() ) as $url ) {
			if ( ! $this->is_customer_https_url( $url ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Existing credentials may use WordPress's query-based REST routing. They
	 * still require an unambiguous HTTPS destination, but not a browser-safe path.
	 */
	private function is_customer_https_url( string $url ): bool {
		if ( '' === $url || trim( $url ) !== $url || 1 === preg_match( '/[\x00-\x20\x7F"\\\\]/', $url ) ) {
			return false;
		}

		$parts = wp_parse_url( $url );

		return is_array( $parts )
			&& 'https' === strtolower( (string) ( $parts['scheme'] ?? '' ) )
			&& ! empty( $parts['host'] )
			&& ! isset( $parts['user'] )
			&& ! isset( $parts['pass'] )
			&& ! isset( $parts['fragment'] );
	}

	/**
	 * Require an unambiguous HTTPS URL before publishing it in OAuth metadata or
	 * a quoted WWW-Authenticate parameter.
	 */
	private function is_oauth_https_url( string $url ): bool {
		if ( ! $this->is_customer_https_url( $url ) ) {
			return false;
		}

		$parts = wp_parse_url( $url );
		if ( empty( $parts['path'] ) ) {
			return false;
		}
		if ( ! isset( $parts['query'] ) ) {
			return true;
		}

		// Plain permalinks carry exactly one REST route. Reject extra or duplicate
		// parameters rather than allowing PHP and a client to choose differently.
		$query = explode( '=', (string) $parts['query'], 2 );
		return 2 === count( $query )
			&& 'rest_route' === $query[0]
			&& 1 === preg_match( '#^/automator/v1/(?:mcp|oauth(?:/(?:connection|authorize|token|register|revoke))?)$#D', rawurldecode( $query[1] ) );
	}

	/**
	 * Return a stable secret-backed fingerprint for exact metadata documents.
	 */
	public function fingerprint( array $metadata ): string {
		unset( $metadata['automator_metadata_fingerprint'] );
		ksort( $metadata, SORT_STRING );

		// Domain-separate this public metadata authenticator from every other
		// consumer of WordPress's auth salt. The derived key stays stable for the
		// site without using the raw salt as a public-input HMAC key.
		$key = hash_hmac( 'sha256', 'uncanny-automator:mcp:oauth-metadata-fingerprint:v1', wp_salt( 'auth' ), true );

		return hash_hmac( 'sha256', (string) wp_json_encode( $metadata ), $key );
	}

	/**
	 * Read normalized settings.
	 */
	private function get_settings(): array {
		$settings = get_option( self::OPTION_NAME, array() );

		return is_array( $settings ) ? $settings : array();
	}
}
